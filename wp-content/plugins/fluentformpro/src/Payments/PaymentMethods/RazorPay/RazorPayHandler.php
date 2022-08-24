<?php

namespace FluentFormPro\Payments\PaymentMethods\RazorPay;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentMethods\BasePaymentMethod;

class RazorPayHandler extends BasePaymentMethod
{
    public function __construct()
    {
        parent::__construct('razorpay');
    }

    public function init()
    {
        add_filter('fluentform_payment_method_settings_validation_'.$this->key, array($this, 'validateSettings'), 10, 2);

        if(!$this->isEnabled()) {
            return;
        }

        add_filter('fluentform_transaction_data_' . $this->key, array($this, 'modifyTransaction'), 10, 1);

        add_filter('fluentformpro_available_payment_methods', [$this, 'pushPaymentMethodToForm']);

        (new RazorPayProcessor())->init();
    }

    public function pushPaymentMethodToForm($methods)
    {
        $methods[$this->key] = [
            'title' => __('RazorPay', 'fluentformpro'),
            'enabled' => 'yes',
            'method_value' => $this->key,
            'settings' => [
                'option_label' => [
                    'type' => 'text',
                    'template' => 'inputText',
                    'value' => 'Pay with RazorPay',
                    'label' => 'Method Label'
                ]
            ]
        ];

        return $methods;
    }

    public function validateSettings($errors, $settings)
    {
        if(ArrayHelper::get($settings, 'is_active') == 'no') {
            return [];
        }

        $mode = ArrayHelper::get($settings, 'payment_mode');
        if(!$mode) {
            $errors['payment_mode'] = __('Please select Payment Mode', 'fluentformpro');
        }

        if($mode == 'test') {
            if(!ArrayHelper::get($settings, 'test_api_key') || !ArrayHelper::get($settings, 'test_api_secret')) {
                $errors['test_api_key'] = __('Please provide test API key and secret', 'fluentformpro');
            }
        } else if($mode == 'live') {
            if(!ArrayHelper::get($settings, 'live_api_key') || !ArrayHelper::get($settings, 'live_api_secret')) {
                $errors['live_api_key'] = __('Please provide live API key and secret', 'fluentformpro');
            }
        }

        return $errors;
    }

    public function modifyTransaction($transaction)
    {
        if ($transaction->charge_id) {
            $transaction->action_url =  'https://dashboard.razorpay.com/app/payments/'.$transaction->charge_id;
        }

        return $transaction;
    }

    public function isEnabled()
    {
        $settings = $this->getGlobalSettings();
        return $settings['is_active'] == 'yes';
    }

    public function getGlobalFields()
    {
        return [
            'label' => 'RazorPay',
            'fields' => [
                [
                    'settings_key' => 'is_active',
                    'type' => 'yes-no-checkbox',
                    'label' => 'Status',
                    'checkbox_label' => 'Enable RazorPay Payment Method'
                ],
                [
                    'settings_key' => 'payment_mode',
                    'type' => 'input-radio',
                    'label' => 'Payment Mode',
                    'options' => [
                        'test' => 'Test Mode',
                        'live' => 'Live Mode'
                    ],
                    'info_help' => 'Select the payment mode. for testing purposes you should select Test Mode otherwise select Live mode.',
                    'check_status' => 'yes'
                ],
                [
                    'settings_key' => 'checkout_type',
                    'type' => 'input-radio',
                    'label' => 'Checkout Style Type',
                    'options' => [
                        'modal' => 'Modal Checkout Style',
                        'hosted' => 'Hosted to razorpay.com'
                    ],
                    'info_help' => 'Select which type of checkout style you want.',
                    'check_status' => 'yes'
                ],
                [
                    'type' => 'html',
                    'html' => '<h2>Your Test API Credentials</h2><p>If you use the test mode</p>'
                ],
                [
                    'settings_key' => 'test_api_key',
                    'type' => 'input-text',
                    'data_type' => 'password',
                    'placeholder' => 'Test API Key',
                    'label' => 'Test API Key',
                    'inline_help' => 'Provide your test api key for your test payments',
                    'check_status' => 'yes'
                ],
                [
                    'settings_key' => 'test_api_secret',
                    'type' => 'input-text',
                    'data_type' => 'password',
                    'placeholder' => 'Test API Secret',
                    'label' => 'Test API Secret',
                    'inline_help' => 'Provide your test api secret for your test payments',
                    'check_status' => 'yes'
                ],
                [
                    'type' => 'html',
                    'html' => '<h2>Your Live API Credentials</h2><p>If you use the test mode</p>'
                ],
                [
                    'settings_key' => 'live_api_key',
                    'type' => 'input-text',
                    'data_type' => 'password',
                    'label' => 'Live API Key',
                    'placeholder' => 'Live API Key',
                    'inline_help' => 'Provide your live api key for your live payments',
                    'check_status' => 'yes'
                ],
                [
                    'settings_key' => 'live_api_secret',
                    'type' => 'input-text',
                    'data_type' => 'password',
                    'placeholder' => 'Live API Secret',
                    'label' => 'Live API Secret',
                    'inline_help' => 'Provide your live api secret for your live payments',
                    'check_status' => 'yes'
                ],
                [
                    'type' => 'html',
                    'html' => '<h2>RazorPay Notifications (For hosted Checkout)</h2><p>Select if you want to enable SMS and Email Notification from razorpay</p>'
                ],
                [
                    'settings_key' => 'notifications',
                    'type' => 'input-checkboxes',
                    'label' => 'RazorPay Notifications',
                    'options' => [
                        'sms' => 'SMS',
                        'email' => 'Email'
                    ],
                    'info_help' => '',
                    'check_status' => 'yes'
                ],
                [
                    'type' => 'html',
                    'html' => '<p>  <a target="_blank" rel="noopener" href="https://wpmanageninja.com/docs/fluent-form/payment-settings/how-to-integrate-razorpay-with-wp-fluent-forms/">Please read the documentation</a> to learn how to setup <b>RazorPay Payment </b> Gateway. </p>'
                ]
            ]
        ];
    }

    public function getGlobalSettings()
    {
        return RazorPaySettings::getSettings();
    }
}
