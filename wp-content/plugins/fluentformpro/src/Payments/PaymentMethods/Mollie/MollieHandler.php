<?php

namespace FluentFormPro\Payments\PaymentMethods\Mollie;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentMethods\BasePaymentMethod;

class MollieHandler extends BasePaymentMethod
{
    public function __construct()
    {
        parent::__construct('mollie');
    }

    public function init()
    {
        add_filter('fluentform_payment_method_settings_validation_'.$this->key, array($this, 'validateSettings'), 10, 2);

        if(!$this->isEnabled()) {
            return;
        }

        add_filter('fluentform_transaction_data_' . $this->key, array($this, 'modifyTransaction'), 10, 1);

        add_filter(
            'fluentformpro_available_payment_methods',
            [$this, 'pushPaymentMethodToForm']
        );

        (new MollieProcessor())->init();
    }

    public function pushPaymentMethodToForm($methods)
    {
        $methods[$this->key] = [
            'title' => __('Mollie', 'fluentformpro'),
            'enabled' => 'yes',
            'method_value' => $this->key,
            'settings' => [
                'option_label' => [
                    'type' => 'text',
                    'template' => 'inputText',
                    'value' => 'Pay with Mollie',
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

        if(!ArrayHelper::get($settings, 'test_api_key') && !ArrayHelper::get($settings, 'live_api_key')) {
            $errors['test_api_key'] = __('Mollie API Key is required', 'fluentformpro');
        }

        if(!ArrayHelper::get($settings, 'payment_mode')) {
            $errors['payment_mode'] = __('Please select Payment Mode', 'fluentformpro');
        }

        return $errors;
    }

    public function modifyTransaction($transaction)
    {
        if (is_array($transaction->payment_note) && $transactionUrl = ArrayHelper::get($transaction->payment_note, '_links.dashboard.href')) {
            $transaction->action_url =  $transactionUrl;
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
            'label' => 'Mollie',
            'fields' => [
                [
                    'settings_key' => 'is_active',
                    'type' => 'yes-no-checkbox',
                    'label' => 'Status',
                    'checkbox_label' => 'Enable Mollie Payment Method'
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
                    'settings_key' => 'test_api_key',
                    'type' => 'input-text',
                    'data_type' => 'password',
                    'placeholder' => 'Test API Key',
                    'label' => 'Test API Key',
                    'inline_help' => 'Provide your test api key for your test payments',
                    'check_status' => 'yes'
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
                    'type' => 'html',
                    'html' => '<p>  <a target="_blank" rel="noopener" href="https://wpmanageninja.com/docs/fluent-form/payment-settings/how-to-integrate-mollie-with-wp-fluent-forms/">Please read the documentation</a> to learn how to setup <b>Mollie Payment </b> Gateway. </p>'
                ],
            ]
        ];
    }

    public function getGlobalSettings()
    {
        return MollieSettings::getSettings();
    }
}
