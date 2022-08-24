<?php

namespace FluentFormPro\Payments\PaymentMethods\Paystack;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentMethods\BasePaymentMethod;

class PaystackHandler extends BasePaymentMethod
{
    public function __construct()
    {
        parent::__construct('paystack');
    }

    public function init()
    {
        add_filter('fluentform_payment_method_settings_validation_'.$this->key, array($this, 'validateSettings'), 10, 2);

        if(!$this->isEnabled()) {
            return;
        }

        add_filter('fluentform_transaction_data_' . $this->key, array($this, 'modifyTransaction'), 10, 1);

        add_filter('fluentformpro_available_payment_methods', [$this, 'pushPaymentMethodToForm']);

        (new PaystackProcessor())->init();
    }

    public function pushPaymentMethodToForm($methods)
    {
        $methods[$this->key] = [
            'title' => __('Paystack', 'fluentformpro'),
            'enabled' => 'yes',
            'method_value' => $this->key,
            'settings' => [
                'option_label' => [
                    'type' => 'text',
                    'template' => 'inputText',
                    'value' => 'Pay with Paystack',
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
            $transaction->action_url =  'https://dashboard.paystack.com/#/transactions/'.$transaction->charge_id;
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
            'label' => 'Paystack',
            'fields' => [
                [
                    'settings_key' => 'is_active',
                    'type' => 'yes-no-checkbox',
                    'label' => 'Status',
                    'checkbox_label' => 'Enable Paystack Payment Method'
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
                    'html' => '<p>  <a target="_blank" rel="noopener" href="https://wpmanageninja.com/docs/fluent-form/payment-settings/how-to-integrate-paystack-with-wp-fluent-forms/#additional-settings-per-form
">Please read the documentation</a> to learn how to setup <b>PayStack Payment </b> Gateway. </p>'
                ],
//                [
//                    'settings_key' => 'payment_channels',
//                    'type' => 'input-checkboxes',
//                    'label' => 'Payment Channels',
//                    'options' => [
//                        'card' => 'Card',
//                        'bank' => 'Bank',
//                        'ussd' => 'USSD',
//                        'qr' => 'QR',
//                        'mobile_money' => 'Mobile Money',
//                        'bank_transfer' => 'Bank Transfer',
//                    ],
//                    'info_help' => '',
//                    'check_status' => 'yes'
//                ]
            ]
        ];
    }

    public function getGlobalSettings()
    {
        return PaystackSettings::getSettings();
    }
}
