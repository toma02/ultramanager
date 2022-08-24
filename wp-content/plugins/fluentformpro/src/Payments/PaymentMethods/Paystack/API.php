<?php

namespace FluentFormPro\Payments\PaymentMethods\Paystack;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper;

class API
{
    public function verifyIPN()
    {
        // Check the request method is POST
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
            return;
        }

        // Set initial post data to empty string
        $post_data = '';

        // Fallback just in case post_max_size is lower than needed
        if (ini_get('allow_url_fopen')) {
            $post_data = file_get_contents('php://input');
        } else {
            // If allow_url_fopen is not enabled, then make sure that post_max_size is large enough
            ini_set('post_max_size', '12M');
        }
        // Start the encoded data collection with notification command
        $encoded_data = 'cmd=_notify-validate';

        // Get current arg separator
        $arg_separator = ini_get('arg_separator.output');

        // Verify there is a post_data
        if ($post_data || strlen($post_data) > 0) {
            // Append the data
            $encoded_data .= $arg_separator . $post_data;
        } else {
            // Check if POST is empty
            if (empty($_POST)) {
                // Nothing to do
                return;
            } else {
                // Loop through each POST
                foreach ($_POST as $key => $value) {
                    // Encode the value and append the data
                    $encoded_data .= $arg_separator . "$key=" . urlencode($value);
                }
            }
        }

        // Convert collected post data to an array
        parse_str($encoded_data, $encoded_data_array);

        foreach ($encoded_data_array as $key => $value) {
            if (false !== strpos($key, 'amp;')) {
                $new_key = str_replace('&amp;', '&', $key);
                $new_key = str_replace('amp;', '&', $new_key);
                unset($encoded_data_array[$key]);
                $encoded_data_array[$new_key] = $value;
            }
        }

        $defaults = $_REQUEST;
        $encoded_data_array = wp_parse_args($encoded_data_array, $defaults);
        $this->handleIpn($encoded_data_array);
        exit(200);
    }

    protected function handleIpn($data)
    {
        $submissionId = intval(ArrayHelper::get($data, 'submission_id'));
        if (!$submissionId || empty($data['id'])) {
            return;
        }
        $submission = wpFluent()->table('fluentform_submissions')->where('id', $submissionId)->first();
        if (!$submission) {
            return;
        }
        $vendorTransaction = $this->makeApiCall('payments/' . $data['id'], [], $submission->form_id, 'GET');

        if (is_wp_error($vendorTransaction)) {
            do_action('ff_log_data', [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => 'Mollie Payment Webhook Error',
                'description'      => $vendorTransaction->get_error_message()
            ]);
        }

        $status = $vendorTransaction['status'];

        if ($status == 'captured') {
            $status = 'paid';
        }

        do_action('fluentform_ipn_razorpay_action_' . $status, $submission, $vendorTransaction, $data);

        if ($refundAmount = ArrayHelper::get($vendorTransaction, 'amountRefunded.value')) {
            $refundAmount = intval($refundAmount * 100); // in cents
            do_action('fluentform_ipn_razorpay_action_refunded', $refundAmount, $submission, $vendorTransaction, $data);
        }
    }

    public function makeApiCall($path, $args, $formId, $method = 'GET')
    {
        $keys = PaystackSettings::getApiKeys($formId);

        $headers = [
            'Authorization' => 'Bearer ' . $keys['api_secret'],
            'Content-type'  => 'application/json'
        ];

        if ($method == 'POST') {
            $response = wp_remote_post('https://api.paystack.co/' . $path, [
                'headers' => $headers,
                'body'    => json_encode($args)
            ]);
        } else {
            $response = wp_remote_get('https://api.paystack.co/' . $path, [
                'headers' => $headers,
                'body'    => $args
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $responseData = json_decode($body, true);

        if(!$responseData) {
            return new \WP_Error(423, 'Unknown Paystack API request error', $responseData);
        }

        if (!empty($responseData['error'])) {
            $message = ArrayHelper::get($responseData, 'error.description');
            if (!$message) {
                $message = 'Unknown Paystack API request error';
            }
            return new \WP_Error(423, $message, $responseData);
        } else if (empty($responseData['status']) && !$responseData['status']) {
            // this is an error
            $message = 'Paystack API Error';
            if (!empty($responseData['message'])) {
                $message = $responseData['message'];
            }

            return new \WP_Error(423, $message, $responseData);
        }

        return $responseData;
    }
}