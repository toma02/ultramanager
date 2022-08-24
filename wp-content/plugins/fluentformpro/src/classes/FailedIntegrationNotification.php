<?php

namespace FluentFormPro\classes;


if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * Trigger Email Notification when integration fails to run
 */
class FailedIntegrationNotification
{
    private $key = '_fluentform_failed_integration_notification';

    /**
     * Registers actions
     */
    public function init()
    {
        add_action('fluentform_saving_global_settings_with_key_method', [$this, 'saveEmailConfig'], 10, 1);
        if ( ! $this->isEnabled()) {
            return;
        }
        add_action('ff_integration_action_result', [$this, 'sendEmail'], 10, 3);
    }

    /**
     * Send Email if status is failed
     *
     * @param $feed
     * @param $status
     * @param $message
     *
     * @return void
     * @throws \WpFluent\Exception
     */
    public function sendEmail($feed, $status, $message)
    {
        if ($status != 'failed') {
            return;
        }

        $settings = $this->getEmailConfig();

        $feedData = wpFluent()->table('ff_scheduled_actions')
                              ->where('feed_id', $feed['id'])
                              ->get();
        $feedData = array_pop($feedData);
        if (empty($feedData)) {
            return;
        }

        if ($settings['send_to_type'] == 'admin_email') {
            $email = get_option('admin_email');
        } else {
            $email = $settings['custom_recipients'];
        }

        $sub = sprintf(
            "%s - Form ID : %s - Entry ID : %s: Integration Failed to Run",
            get_bloginfo('name'),
            $feedData->form_id,
            $feedData->origin_id
        );

        $emails = $this->getSendAddresses($email);
        if ( ! $emails) {
            return;
        }
        $data = [
            'email'   => $emails,
            'subject' => $sub,
            'body'    => ! empty($message) ? $message : 'Integration Failed to Run , please check your cronjob status.'
        ];

        $this->broadCast($data);
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        $settings = $this->getEmailConfig();

        return $settings['status'] == 'yes';
    }

    /**
     * @return false|mixed|string[]|void
     */
    public function getEmailConfig()
    {
        $settings = [
            'status'            => 'yes',
            'send_to_type'      => 'admin_email',
            'custom_recipients' => '',
        ];
        if (get_option($this->key)) {
            $settings = get_option($this->key);
        }

        return $settings;
    }

    /**
     * @param $request
     *
     * @return void
     */
    public function saveEmailConfig($request)
    {
        if ($request->get('key') != 'failedIntegrationNotification') {
            return;
        }
        $defaults = [
            'status'            => 'yes',
            'send_to_type'      => 'admin_email',
            'custom_recipients' => '',
        ];
        $settings = $request->get('value');
        $settings = json_decode($settings, true);

        $settings = wp_parse_args($settings, $defaults);

        update_option($this->key, $settings, false);

        wp_send_json_success();
    }

    /**
     * @param $data
     *
     * @return bool|mixed|void
     */
    private function broadCast($data)
    {
        $headers = [
            'Content-Type: text/html; charset=utf-8'
        ];
        $data = apply_filters('ff_failed_integration_notify_email_data', $data);

        return wp_mail(
            $data['email'],
            $data['subject'],
            $data['body'],
            $headers,
            ''
        );
    }

    /**
     * Get Send Email Addresses
     *
     * @param $email
     *
     * @return array
     */
    private function getSendAddresses($email)
    {
        $sendEmail = explode(',', $email);
        if (count($sendEmail) > 1) {
            $email = $sendEmail;
        } else {
            $email = [$email];
        }

        return array_filter($email, 'is_email');
    }


}