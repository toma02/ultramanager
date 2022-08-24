<?php

namespace FluentFormPro\Payments\PaymentMethods\PayPal;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;
use FluentFormPro\Payments\PaymentMethods\PayPal\API\IPN;

class PayPalProcessor extends BaseProcessor
{
    public $method = 'paypal';

    protected $form;

    protected $customerName = '';

    public function init()
    {
        add_action('fluentform_process_payment_' . $this->method, array($this, 'handlePaymentAction'), 10, 6);
        add_action('fluent_payment_frameless_' . $this->method, array($this, 'handleSessionRedirectBack'));

        add_action('fluentform_ipn_endpoint_' . $this->method, function () {
            (new IPN())->verifyIPN();
            exit(200);
        });

        add_action('fluentform_ipn_paypal_action_web_accept', array($this, 'handleWebAcceptPayment'), 10, 3);

        add_filter(
            'fluentform_submitted_payment_items_' . $this->method,
            [$this, 'validateSubmittedItems'],
            10,
            4
        );

    }

    public function handlePaymentAction($submissionId, $submissionData, $form, $methodSettings, $hasSubscriptions, $totalPayable)
    {
        $this->setSubmissionId($submissionId);
        $this->form = $form;
        $submission = $this->getSubmission();
        $paymentTotal = $this->getAmountTotal();

        if (!$paymentTotal && !$hasSubscriptions) {
            return false;
        }

        // Create the initial transaction here
        $transaction = $this->createInitialPendingTransaction($submission, $hasSubscriptions);

        $this->handlePayPalRedirect($transaction, $submission, $form, $methodSettings, $hasSubscriptions);
    }

    public function handlePayPalRedirect($transaction, $submission, $form, $methodSettings, $hasSubscriptions)
    {
        $paymentSettings = PaymentHelper::getPaymentSettings();

        $args = array(
            'fluentform_payment' => $submission->id,
            'payment_method'     => $this->method,
            'transaction_hash'   => $transaction ? $transaction->transaction_hash : '',
            'type'               => 'success'
        );

        if (empty($args['transaction_hash'])) {
            $args['entry_uid'] = Helper::getSubmissionMeta($submission->id, '_entry_uid_hash');
        }

        $successUrl = add_query_arg($args, site_url('index.php'));

        $cancelUrl = $submission->source_url;

        if (!wp_http_validate_url($cancelUrl)) {
            $cancelUrl = home_url($cancelUrl);
        }

        $domain = site_url('index.php');

        if(defined('FF_PAYPAL_IPN_DOMAIN') && FF_PAYPAL_IPN_DOMAIN) {
            $domain = FF_PAYPAL_IPN_DOMAIN;
        }

        $listener_url = add_query_arg(array(
            'fluentform_payment_api_notify' => 1,
            'payment_method'                => $this->method,
            'submission_id'                 => $submission->id
        ), $domain); //

        $customArgs =  array(
            'fs_id'  => $submission->id
        );

        if($transaction) {
            $customArgs['transaction_hash'] = $transaction->transaction_hash;
        } else {
            $customArgs['entry_uid'] = Helper::getSubmissionMeta($submission->id, '_entry_uid_hash');
        }

        $paypal_args = array(
            'cmd'           => '_cart',
            'upload'        => '1',
            'rm'            => is_ssl() ? 2 : 1,
            'business'      => PayPalSettings::getPayPalEmail($form->id),
            'email'         => $transaction->payer_email,
            'no_shipping'   => (ArrayHelper::get($methodSettings, 'settings.require_shipping_address.value') == 'yes') ? '0' : '1',
            'shipping' => (ArrayHelper::get($methodSettings, 'settings.require_shipping_address.value') == 'yes') ? '1' : '0',
            'no_note'       => '1',
            'currency_code' => strtoupper($submission->currency),
            'charset'       => 'UTF-8',
            'custom'        => wp_json_encode($customArgs),
            'return'        => esc_url_raw($successUrl),
            'notify_url'    => $this->limitLength(esc_url_raw($listener_url), 255),
            'cancel_return' => esc_url_raw($cancelUrl)
        );

        if($businessLogo = ArrayHelper::get($paymentSettings, 'business_logo')) {
            $paypal_args['image_url'] = $businessLogo;
        }

        $paypal_args = wp_parse_args($paypal_args, $this->getCartSummery());

        $paypal_args = apply_filters('fluentform_paypal_checkout_args', $paypal_args, $submission, $transaction, $form);

        if ($hasSubscriptions) {
            $this->customerName = PaymentHelper::getCustomerName($submission, $form);
            $paypal_args = $this->processSubscription($paypal_args, $transaction, $hasSubscriptions);
        }

        $redirectUrl = $this->getRedirectUrl($paypal_args, $form->id);

        do_action('ff_log_data', [
            'parent_source_id' => $submission->form_id,
            'source_type'      => 'submission_item',
            'source_id'        => $submission->id,
            'component'        => 'Payment',
            'status'           => 'info',
            'title'            => 'Redirect to PayPal',
            'description'      => 'User redirect to paypal for completing the payment'
        ]);

        wp_send_json_success([
            'nextAction'   => 'payment',
            'actionName'   => 'normalRedirect',
            'redirect_url' => $redirectUrl,
            'message'      => __('You are redirecting to PayPal.com to complete the purchase. Please wait while you are redirecting....', 'fluentformpro'),
            'result'       => [
                'insert_id' => $submission->id
            ]
        ], 200);
    }

    private function getCartSummery()
    {
        $items = $this->getOrderItems();
        $paypal_args = array();
        if ($items) {
            $counter = 1;
            foreach ($items as $item) {
                if (!$item->item_price) {
                    continue;
                }

                $amount = PaymentHelper::floatToString((float)round($item->item_price / 100, 2));
                $itemName = PaymentHelper::formatPaymentItemString($item->item_name, 127);

                $paypal_args['item_name_' . $counter] = PaymentHelper::limitLength($itemName, 127);
                $paypal_args['quantity_' . $counter] = (int)$item->quantity;
                $paypal_args['amount_' . $counter] = $amount;
                $counter = $counter + 1;
            }
        }

        $discountItems = $this->getDiscountItems();
        if ($discountItems) {
            $discountTotal = 0;
            foreach ($discountItems as $discountItem) {
                $discountTotal += $discountItem->line_total;
            }
            $paypal_args['discount_amount_cart'] = round($discountTotal / 100, 2);
        }

        return $paypal_args;
    }

    private function getRedirectUrl($args, $formId = false)
    {
        if ($this->getPaymentMode($formId) == 'test') {
            $paypal_redirect = 'https://www.sandbox.paypal.com/cgi-bin/webscr/?test_ipn=1&';
        } else {
            $paypal_redirect = 'https://www.paypal.com/cgi-bin/webscr/?';
        }

        return $paypal_redirect . http_build_query($args, '', '&');
    }

    public function handleSessionRedirectBack($data)
    {
        $type = sanitize_text_field($data['type']);
        $submissionId = intval($data['fluentform_payment']);
        $this->setSubmissionId($submissionId);

        $submission = $this->getSubmission();

        if (!$submission) {
            return;
        }

        $isNew = false;

        if ($type == 'success' && $submission->payment_status === 'paid') {
            $isNew = $this->getMetaData('is_form_action_fired') != 'yes';
            $returnData = $this->getReturnData();
        } else if ($type == 'success') {
            $transaction = $this->getLastTransaction($submission->id);
            $messageTxt = __('Sometimes, PayPal payments take a few moments to mark as paid. We are trying to process your payment. Please do not close or refresh the window.', 'fluentformpro');
            $message = "<div class='ff_paypal_delay_loader_check'>{$messageTxt}</div>";
            $enableSandboxMode = apply_filters('fluentform/enable-paypal-sandbox-mode', true);
            $loader = true;
            if($transaction && $transaction->payment_mode != 'live' && !$enableSandboxMode) {
                $message = 'Looks like you are using sandbox mode. PayPal does not send instant payment notification while using sandbox mode';
                $loader = false;
            }
            $message = apply_filters('fluentform_paypal_pending_message', $message, $submission);
            $this->addDelayedCheck($submissionId);
            $returnData = [
                'insert_id' => $submission->id,
                'title'     => __('Payment is not marked as paid yet. ', 'fluentformpro'),
                'result'    => false,
                'error'     => $message,
                'loader'    => $loader
            ];
        } else {
            $returnData = [
                'insert_id' => $submission->id,
                'title'     => __('Payment Cancelled', 'fluentformpro'),
                'result'    => false,
                'error'     => __('Looks like you have cancelled the payment', 'fluentformpro')
            ];
        }

        $returnData['type'] = $type;
        $returnData['is_new'] = $isNew;

        $this->showPaymentView($returnData);
    }

    public function handleWebAcceptPayment($data, $submissionId)
    {

        $this->setSubmissionId($submissionId);
        $submission = $this->getSubmission();

        if (!$submission) {
            return;
        }


        $payment_status = strtolower($data['payment_status']);

        if ($payment_status == 'refunded' || $payment_status == 'reversed') {
            // Process a refund
            $this->processRefund($data, $submission);
            return;
        }

        $transaction = $this->getLastTransaction($submissionId);

        if (!$transaction || $transaction->payment_method != $this->method) {
            return;
        }

        if ($data['txn_type'] != 'web_accept' && $data['txn_type'] != 'cart' && $data['payment_status'] != 'Refunded') {
            return;
        }

        // Check if actions are fired
        if ($this->getMetaData('is_form_action_fired') == 'yes') {
            return;
        }

        $business_email = isset($data['business']) && is_email($data['business']) ? trim($data['business']) : trim($data['receiver_email']);

        $this->setMetaData('paypal_receiver_email', $business_email);

        if ('completed' == $payment_status || 'pending' == $payment_status) {
            $status = 'paid';

            if ($payment_status == 'pending') {
                $status = 'processing';
            }
            // Let's make the payment as paid
            $updateData = [
                'payment_note'     => maybe_serialize($data),
                'charge_id'        => sanitize_text_field($data['txn_id']),
                'payer_email'      => sanitize_text_field($data['payer_email']),
                'payer_name'       => ArrayHelper::get($data, 'first_name') . ' ' . ArrayHelper::get($data, 'last_name'),
                'shipping_address' => $this->getAddress($data)
            ];

            $this->updateTransaction($transaction->id, $updateData);
            $this->changeSubmissionPaymentStatus($status);
            $this->changeTransactionStatus($transaction->id, $status);
            $this->recalculatePaidTotal();
            $returnData = $this->completePaymentSubmission(false);
            $this->setMetaData('is_form_action_fired', 'yes');

            if (isset($data['pending_reason'])) {
                // Log Processing Reason
                do_action('ff_log_data', [
                    'parent_source_id' => $submission->form_id,
                    'source_type'      => 'submission_item',
                    'source_id'        => $submission->id,
                    'component'        => 'Payment',
                    'status'           => 'info',
                    'title'            => 'PayPal Payment Pending',
                    'description'      => $this->getPendingReason($data)
                ]);
            }
        }
    }

    private function processRefund($data, $submission)
    {
        if ($submission->payment_status == 'refunded') {
            return;
        }

        if ($submission->payment_status == 'refunded') {
            return;
        }

        // check if already refunded
        $refundExist = $this->getTransactionByChargeId($data['txn_id']);

        if ($refundExist) {
            return;
        }

        $transaction = $this->getTransactionByChargeId($data['parent_txn_id']);

        if (!$transaction) {
            return;
        }

        $refund_amount = $data['mc_gross'] * -100;

        $this->refund($refund_amount, $transaction, $submission, 'paypal', $data['txn_id'], 'Refund From PayPal');

    }

    private function getAddress($data)
    {
        $address = array();
        if (!empty($data['address_street'])) {
            $address['address_line1'] = sanitize_text_field($data['address_street']);
        }
        if (!empty($data['address_city'])) {
            $address['address_city'] = sanitize_text_field($data['address_city']);
        }
        if (!empty($data['address_state'])) {
            $address['address_state'] = sanitize_text_field($data['address_state']);
        }
        if (!empty($data['address_zip'])) {
            $address['address_zip'] = sanitize_text_field($data['address_zip']);
        }
        if (!empty($data['address_state'])) {
            $address['address_country'] = sanitize_text_field($data['address_country_code']);
        }
        return implode(', ', $address);
    }

    public function getPaymentMode($formId = false)
    {
        $isLive = PayPalSettings::isLive($formId);
        if ($isLive) {
            return 'live';
        }
        return 'test';
    }

    private function getPendingReason($data)
    {
        $note = 'Payment marked as pending';
        switch (strtolower($data['pending_reason'])) {
            case 'echeck' :
                $note = __('Payment made via eCheck and will clear automatically in 5-8 days', 'easy-digital-downloads');
                break;
            case 'address' :
                $note = __('Payment requires a confirmed customer address and must be accepted manually through PayPal', 'easy-digital-downloads');
                break;
            case 'intl' :
                $note = __('Payment must be accepted manually through PayPal due to international account regulations', 'easy-digital-downloads');
                break;
            case 'multi-currency' :
                $note = __('Payment received in non-shop currency and must be accepted manually through PayPal', 'easy-digital-downloads');
                break;
            case 'paymentreview' :
            case 'regulatory_review' :
                $note = __('Payment is being reviewed by PayPal staff as high-risk or in possible violation of government regulations', 'easy-digital-downloads');
                break;
            case 'unilateral' :
                $note = __('Payment was sent to non-confirmed or non-registered email address.', 'easy-digital-downloads');
                break;
            case 'upgrade' :
                $note = __('PayPal account must be upgraded before this payment can be accepted', 'easy-digital-downloads');
                break;

            case 'verify' :
                $note = __('PayPal account is not verified. Verify account in order to accept this payment', 'easy-digital-downloads');
                break;
            case 'other' :
                $note = __('Payment is pending for unknown reasons. Contact PayPal support for assistance', 'easy-digital-downloads');
                break;
        }
        return $note;
    }

    public function validateSubmittedItems($paymentItems, $form, $formData, $subscriptionItems)
    {
        $singleItemTotal = 0;

        foreach ($paymentItems as $paymentItem) {
            if ($paymentItem['line_total']) {
                $singleItemTotal += $paymentItem['line_total'];
            }
        }

        $validSubscriptions = [];

        foreach ($subscriptionItems as $subscriptionItem) {
            if ($subscriptionItem['recurring_amount']) {
                $validSubscriptions[] = $subscriptionItem;
            }
        }

        if ($singleItemTotal && count($validSubscriptions)) {
            wp_send_json([
                'errors' => __('PayPal Error: PayPal does not support subscriptions payment and single amount payment at one request', 'fluentformpro')
            ], 423);
        }

        if (count($validSubscriptions) > 2) {
            wp_send_json([
                'errors' => __('PayPal Error: PayPal does not support multiple subscriptions at one request', 'fluentformpro')
            ], 423);
        }

        return $paymentItems;
    }

    public function processSubscription($originalArgs, $transaction, $hasSubscriptions)
    {
        $paymentSettings = PaymentHelper::getPaymentSettings();

        if (!$hasSubscriptions || $transaction->transaction_type != 'subscription') {
            return $originalArgs;
        }

        $subscriptions = $this->getSubscriptions();
        $validSubscriptions = [];

        foreach ($subscriptions as $subscriptionItem) {
            if ($subscriptionItem->recurring_amount) {
                $validSubscriptions[] = $subscriptionItem;
            }
        }

        if (!$validSubscriptions || count($validSubscriptions) > 1) {
            // PayPal Standard does not support more than 1 subscriptions
            // We may add paypal express later for this on.
            return $originalArgs;
        }

        // We just need the first subscriptipn
        $subscription = $validSubscriptions[0];

        if (!$subscription->recurring_amount) {
            return $originalArgs;
        }

        // Setup PayPal arguments
        $paypal_args = array(
            'business'      => $originalArgs['business'],
            'email'         => $originalArgs['email'],
            'invoice'       => $transaction->transaction_hash,
            'no_shipping'   => '1',
            'shipping'      => '0',
            'no_note'       => '1',
            'currency_code' => strtoupper($originalArgs['currency_code']),
            'charset'       => 'UTF-8',
            'custom'        => $originalArgs['custom'],
            'rm'            => '2',
            'return'        => $originalArgs['return'],
            'cancel_return' => $originalArgs['cancel_return'],
            'notify_url'    => $originalArgs['notify_url'],
            'cbt'           => $paymentSettings['business_name'],
            'bn'            => 'FluentFormPro_SP',
            'sra'           => '1',
            'src'           => '1',
            'cmd'           => '_xclick-subscriptions'
        );

        $names = explode(' ', $transaction->payer_name, 2);
        if (count($names) == 2) {
            $firstName = $names[0];
            $lastName = $names[1];
        } else {
            $firstName = $transaction->payer_name;
            $lastName = '';
        }

        if($firstName) {
            $paypal_args['first_name'] = $firstName;
        }

        if($lastName) {
            $paypal_args['last_name'] = $lastName;
        }

        $recurring_amount = $subscription->recurring_amount;
        $initial_amount = $transaction->payment_total - $recurring_amount;

        $recurring_amount = round($recurring_amount / 100, 2);
        $initial_amount = round($initial_amount / 100, 2);

        if ($initial_amount) {
            $paypal_args['a1'] = round($initial_amount + $recurring_amount, 2);
            $paypal_args['p1'] = 1;
        } else if ($subscription->trial_days) {
            $paypal_args['a1'] = 0;
            $paypal_args['p1'] = $subscription->trial_days;
            $paypal_args['t1'] = 'D';
        }

        $paypal_args['a3'] = $recurring_amount;

        $paypal_args['item_name'] = $subscription->item_name . ' - ' . $subscription->plan_name;

        $paypal_args['p3'] = 1; // for now it's 1 as 1 times per period

        switch ($subscription->billing_interval) {
            case 'day':
                $paypal_args['t3'] = 'D';
                break;
            case 'week':
                $paypal_args['t3'] = 'W';
                break;
            case 'month':
                $paypal_args['t3'] = 'M';
                break;
            case 'year':
                $paypal_args['t3'] = 'Y';
                break;
        }

        if ($initial_amount) {
            $paypal_args['t1'] = $paypal_args['t3'];
        }

        if ($subscription->bill_times > 1) {
            if ($initial_amount) {
                $subscription->bill_times = $subscription->bill_times - 1;
            }

            $billTimes = $subscription->bill_times <= 52 ? absint($subscription->bill_times) : 52;
            $paypal_args['srt'] = $billTimes;
        }

        foreach ($paypal_args as $argName => $argValue) {
            if($argValue === '') {
                unset($paypal_args[$argName]);
            }
        }

        return $paypal_args;

    }

    public function addDelayedCheck(int $submissionId)
    {
        wp_enqueue_script('ff_paypal', FLUENTFORMPRO_DIR_URL.'public/js/ff_paypal.js', ['jquery'], FLUENTFORM_VERSION, true);
        wp_localize_script('ff_paypal', 'ff_paypal_vars', [
            'ajax_url'        => admin_url('admin-ajax.php'),
            'submission_id'   => $submissionId,
            'onFailedMessage' => __("Sorry! We couldn't mark your payment as paid. Please try again later!", 'fluentformpro')
        ]);
    }

    /**
     * Check if paypal payment is marked paid
     * @return json response
     */
    public function isPaid()
    {
        $submissionId = intval($_REQUEST['submission_id']);

        $this->setSubmissionId($submissionId);

        $submission = $this->getSubmission();

        if (!$submission ) {
            wp_send_json([
                'message' => __('Invalid Payment Transaction', 'fluentformpro'),
            ]);
        }

        if ($submission->payment_status == 'paid') {
            wp_send_json_success([
                'nextAction' => 'reload'
            ]);
        } else {
            wp_send_json_success([
                'nextAction' => 'reCheck'
            ]);
        }
    }
}
