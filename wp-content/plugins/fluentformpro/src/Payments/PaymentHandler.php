<?php

namespace FluentFormPro\Payments;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Acl\Acl;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\FormBuilder\ShortCodeParser;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\Classes\PaymentAction;
use FluentFormPro\Payments\Classes\PaymentEntries;
use FluentFormPro\Payments\Classes\PaymentReceipt;
use FluentFormPro\Payments\Components\Coupon;
use FluentFormPro\Payments\Components\CustomPaymentComponent;
use FluentFormPro\Payments\Components\ItemQuantity;
use FluentFormPro\Payments\Components\MultiPaymentComponent;
use FluentFormPro\Payments\Components\PaymentMethods;
use FluentFormPro\Payments\Components\PaymentSummaryComponent;
use FluentFormPro\Payments\Components\Subscription;
use FluentFormPro\Payments\Orders\OrderData;
use FluentFormPro\Payments\PaymentMethods\Mollie\MollieHandler;
use FluentFormPro\Payments\PaymentMethods\Offline\OfflineHandler;
use FluentFormPro\Payments\PaymentMethods\PayPal\PayPalHandler;
use FluentFormPro\Payments\PaymentMethods\Paystack\PaystackHandler;
use FluentFormPro\Payments\PaymentMethods\RazorPay\RazorPayHandler;
use FluentFormPro\Payments\PaymentMethods\Stripe\Components\StripeInline;
use FluentFormPro\Payments\PaymentMethods\Stripe\ConnectConfig;
use FluentFormPro\Payments\PaymentMethods\Stripe\StripeHandler;
use FluentFormPro\Payments\PaymentMethods\Stripe\StripeSettings;

class PaymentHandler
{
    public function init()
    {
        add_filter('fluentform_global_settings_components', [$this, 'pushGlobalSettings'], 1, 1);

        add_action('fluentform_global_settings_component_payment_settings', [$this, 'renderPaymentSettings']);

        add_action('wp_ajax_fluentform_handle_payment_ajax_endpoint', [$this, 'handleAjaxEndpoints']);

        if (!$this->isEnabled()) {
            return;
        }

        add_filter('fluentform_show_payment_entries', '__return_true');

        add_filter('fluentform_form_settings_menu', array($this, 'maybeAddPaymentSettings'), 10, 2);
        // Let's load Payment Methods here
        (new StripeHandler())->init();
        (new PayPalHandler())->init();
        (new OfflineHandler())->init();
        (new MollieHandler())->init();
        (new RazorPayHandler())->init();
        (new PaystackHandler())->init();

        // Let's load the payment method component here
        new MultiPaymentComponent();
        new Subscription();
        new CustomPaymentComponent();
        new ItemQuantity();
        new PaymentMethods();
        new PaymentSummaryComponent();
        new Coupon();
        new StripeInline();

        add_action('fluentform_before_insert_payment_form', array($this, 'maybeHandlePayment'), 10, 3);

        add_filter('fluentform_submission_order_data', function ($data, $submission, $form) {
            return OrderData::getSummary($submission, $form);
        }, 10, 3);

        add_filter('fluent_form_entries_vars', function ($vars, $form) {
            if ($form->has_payment) {
                $vars['has_payment'] = $form->has_payment;
                $vars['currency_config'] = PaymentHelper::getCurrencyConfig($form->id);
                $vars['currency_symbols'] = PaymentHelper::getCurrencySymbols();
                $vars['payment_statuses'] = PaymentHelper::getPaymentStatuses();
            }
            return $vars;
        }, 10, 2);

        add_filter('fluentform_submission_entry_labels_with_payment', array($this, 'modifySingleEntryLabels'), 10, 3);

        add_filter('fluentform_all_entry_labels_with_payment', array($this, 'modifySingleEntryLabels'), 10, 3);

        add_action('fluentform_rendering_payment_form', function ($form) {
            wp_enqueue_script('fluentform-payment-handler', FLUENTFORMPRO_DIR_URL . 'public/js/payment_handler.js', array('jquery'), FLUENTFORM_VERSION, true);
            
            wp_enqueue_style(
                'fluentform-payment-skin',
                FLUENTFORMPRO_DIR_URL . 'public/css/payment_skin.css',
                array(),
                FLUENTFORM_VERSION
            );

            wp_localize_script('fluentform-payment-handler', 'fluentform_payment_config', [
                'i18n' => [
                    'item'            => __('Item', 'fluentformpro'),
                    'price'           => __('Price', 'fluentformpro'),
                    'qty'             => __('Qty', 'fluentformpro'),
                    'line_total'      => __('Line Total', 'fluentformpro'),
                    'total'           => __('Total', 'fluentformpro'),
                    'not_found'       => __('No payment item selected yet', 'fluentformpro'),
                    'discount:'       => __('Discount:', 'fluentformpro'),
                    'processing_text' => __('Processing payment. Please wait...', 'fluentformpro'),
                    'confirming_text' => __('Confirming payment. Please wait...', 'fluentformpro'),
                    'Signup Fee for'  => __('Signup Fee for', 'fluentformpro')
                ]
            ]);

            $publishableKey = apply_filters('fluentform-payment_stripe_publishable_key', StripeSettings::getPublishableKey($form->id), $form->id);

            wp_localize_script('fluentform-payment-handler', 'fluentform_payment_config_' . $form->id, [
                'currency_settings' => PaymentHelper::getCurrencyConfig($form->id),
                'stripe'            => [
                    'publishable_key' => $publishableKey,
                    'inlineConfig'    => PaymentHelper::getStripeInlineConfig($form->id)
                ],
                'stripe_app_info'   => array(
                    'name'       => 'Fluent Forms',
                    'version'    => FLUENTFORMPRO_VERSION,
                    'url'        => site_url(),
                    'partner_id' => 'pp_partner_FN62GfRLM2Kx5d'
                )
            ]);

        });

        if (isset($_GET['fluentform_payment']) && isset($_GET['payment_method'])) {
            add_action('wp', function () {
                $data = $_GET;

                $type = sanitize_text_field($_GET['fluentform_payment']);

                if ($type == 'view' && $route = ArrayHelper::get($data, 'route')) {
                    do_action('fluent_payment_view_' . $route, $data);
                }

                $this->validateFrameLessPage($data);
                $paymentMethod = sanitize_text_field($_GET['payment_method']);
                do_action('fluent_payment_frameless_' . $paymentMethod, $data);
            });
        }

        if (isset($_REQUEST['fluentform_payment_api_notify'])) {
            add_action('wp', function () {
                $paymentMethod = sanitize_text_field($_REQUEST['payment_method']);
                do_action('fluentform_ipn_endpoint_' . $paymentMethod);
            });
        }

        add_filter('fluentform_editor_vars', function ($vars) {
            $settings = PaymentHelper::getCurrencyConfig($vars['form_id']);
            $vars['payment_settings'] = $settings;
            $vars['has_payment_features'] = !!$settings;
            return $vars;
        });

        add_filter('fluentform/admin_i18n', array($this, 'paymentTranslations'), 10, 1);

        add_filter('fluentform_payment_smartcode', array($this, 'paymentReceiptView'), 10, 3);

        add_action('user_register', array($this, 'maybeAssignTransactions'), 99, 1);

        (new PaymentEntries())->init();

        /*
         * Transactions and subscriptions Shortcode
         */
        (new TransactionShortcodes())->init();

    }

    public function pushGlobalSettings($components)
    {
        $components['payment_settings'] = [
            'hash'  => '',
            'title' => 'Payment Settings',
            'query' => [
                'component' => 'payment_settings'
            ]
        ];
        return $components;
    }

    public function renderPaymentSettings()
    {

        if (isset($_GET['ff_stripe_connect'])) {
            $data = ArrayHelper::only($_GET, ['ff_stripe_connect', 'mode', 'state', 'code']);
            ConnectConfig::verifyAuthorizeSuccess($data);
        }

        $paymentSettings = PaymentHelper::getPaymentSettings();
        $isSettingsAvailable = !!get_option('__fluentform_payment_module_settings');

        $nav = 'general';

        if (isset($_REQUEST['nav'])) {
            $nav = sanitize_text_field($_REQUEST['nav']);
        }

        $data = [
            'is_setup'                  => $isSettingsAvailable,
            'general'                   => $paymentSettings,
            'payment_methods'           => apply_filters('fluentformpro_available_payment_methods', []),
            'available_payment_methods' => apply_filters('fluentformpro_payment_methods_global_settings', []),
            'currencies'                => PaymentHelper::getCurrencies(),
            'active_nav'                => $nav,
            'stripe_webhook_url'        => add_query_arg([
                'fluentform_payment_api_notify' => '1',
                'payment_method'                => 'stripe'
            ], site_url('index.php')),
            'paypal_webhook_url'        => add_query_arg([
                'fluentform_payment_api_notify' => '1',
                'payment_method'                => 'paypal'
            ], site_url('index.php'))
        ];

        wp_enqueue_script('ff-payment-settings', FLUENTFORMPRO_DIR_URL . 'public/js/payment-settings.js', ['jquery'], FLUENTFORMPRO_VERSION, true);
        wp_enqueue_style('ff-payment-settings', FLUENTFORMPRO_DIR_URL . 'public/css/payment_settings.css', [], FLUENTFORMPRO_VERSION);

        wp_enqueue_media();

        wp_localize_script('ff-payment-settings', 'ff_payment_settings', $data);

        echo '<div id="ff-payment-settings"></div>';
    }

    public function handleAjaxEndpoints()
    {
        if (isset($_REQUEST['form_id'])) {
            Acl::verify('fluentform_forms_manager');
        } else {
            Acl::verify('fluentform_settings_manager');
        }

        $route = sanitize_text_field($_REQUEST['route']);
        (new AjaxEndpoints())->handleEndpoint($route);
    }

    public function maybeHandlePayment($insertData, $data, $form)
    {
        // Let's get selected Payment Method
        if (!FormFieldsParser::hasPaymentFields($form)) {
            return;
        }

        $paymentAction = new PaymentAction($form, $insertData, $data);

        if (!$paymentAction->getSubscriptionItems() && !$paymentAction->getCalculatedAmount()) {
            return;
        }

        /*
         * We have to check if
         * 1. has payment method
         * 2. if user selected payment method
         * 3. or maybe has a conditional logic on it
         */
        if ($paymentAction->isConditionPass()) {
            if (FormFieldsParser::hasElement($form, 'payment_method') &&
                !$paymentAction->selectedPaymentMethod
            ) {
                wp_send_json([
                    'errors' => [__('Sorry! No selected payment method found. Please select a valid payment method', 'fluentformpro')]
                ], 423);
            }
        }

        $paymentAction->draftFormEntry();
    }

    public function isEnabled()
    {
        $paymentSettings = PaymentHelper::getPaymentSettings();
        return $paymentSettings['status'] == 'yes';
    }

    public function modifySingleEntryLabels($labels, $submission, $form)
    {
        $formFields = FormFieldsParser::getPaymentFields($form);
        if ($formFields && is_array($formFields)) {
            $labels = ArrayHelper::except($labels, array_keys($formFields));
        }
        return $labels;
    }

    public function maybeAddPaymentSettings($menus, $formId)
    {
        $form = wpFluent()->table('fluentform_forms')->find($formId);
        if ($form->has_payment) {
            $menus = array_merge(array_slice($menus, 0, 1), array(
                'payment_settings' => [
                    'title' => __('Payment Settings', 'fluentformpro'),
                    'slug'  => 'form_settings',
                    'hash'  => 'payment_settings',
                    'route' => '/payment-settings',
                ]
            ), array_slice($menus, 1));
        }
        return $menus;
    }


    /**
     * @param $html     string
     * @param $property string
     * @param $instance ShortCodeParser
     * @return false|string
     */
    public function paymentReceiptView($html, $property, $instance)
    {
        $entry = $instance::getEntry();
        $receiptClass = new PaymentReceipt($entry);
        return $receiptClass->getItem($property);
    }

    private function validateFrameLessPage($data)
    {
        // We should verify the transaction hash from the URL
        $transactionHash = sanitize_text_field(ArrayHelper::get($data, 'transaction_hash'));
        $submissionId = intval(ArrayHelper::get($data, 'fluentform_payment'));
        if (!$submissionId) {
            die('Validation Failed');
        }

        if ($transactionHash) {
            $transaction = wpFluent()->table('fluentform_transactions')
                ->where('submission_id', $submissionId)
                ->where('transaction_hash', $transactionHash)
                ->first();
            if ($transaction) {
                return true;
            }

            die('Transaction hash is invalid');
        }

        $uid = sanitize_text_field(ArrayHelper::get($data, 'entry_uid'));
        if (!$uid) {
            die('Validation Failed');
        }

        $originalUid = Helper::getSubmissionMeta($submissionId, '_entry_uid_hash');

        if ($originalUid != $uid) {
            die('Transaction UID is invalid');
        }

        return true;
    }

    public function maybeAssignTransactions($userId)
    {
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return false;
        }
        $userEmail = $user->user_email;

        $transactions = wpFluent()->table('fluentform_transactions')
            ->where('payer_email', $userEmail)
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', '');
            })
            ->get();

        if (!$transactions) {
            return false;
        }

        $submissionIds = [];
        $transactionIds = [];
        foreach ($transactions as $transaction) {
            $submissionIds[] = $transaction->submission_id;
            $transactionIds[] = $transaction->id;
        }

        $submissionIds = array_unique($submissionIds);
        $transactionIds = array_unique($transactionIds);

        wpFluent()->table('fluentform_submissions')
            ->whereIn('id', $submissionIds)
            ->update([
                'user_id'    => $userId,
                'updated_at' => current_time('mysql')
            ]);

        wpFluent()->table('fluentform_transactions')
            ->whereIn('id', $transactionIds)
            ->update([
                'user_id'    => $userId,
                'updated_at' => current_time('mysql')
            ]);

        return true;
    }

    public function paymentTranslations($i18n)
    {
        $paymentI18n = array(
            'Order Details' => __('Order Details', 'fluentformpro'),
            'Product' => __('Product', 'fluentformpro'),
            'Qty' => __('Qty', 'fluentformpro'),
            'Unit Price' => __('Unit Price', 'fluentformpro'),
            'Total' => __('Total', 'fluentformpro'),
            'Sub-Total' => __('Sub-Total', 'fluentformpro'),
            'Discount' => __('Discount', 'fluentformpro'),
            'Price' => __('Price', 'fluentformpro'),
            'Payment Details' => __('Payment Details', 'fluentformpro'),
            'From Subscriptions' => __('From Subscriptions', 'fluentformpro'),
            'Card Last 4' => __('Card Last 4', 'fluentformpro'),
            'Payment Total' => __('Payment Total', 'fluentformpro'),
            'Payment Status' => __('Payment Status', 'fluentformpro'),
            'Transaction ID' => __('Transaction ID', 'fluentformpro'),
            'Payment Method' => __('Payment Method', 'fluentformpro'),
            'Transaction' => __('Transaction', 'fluentformpro'),
            'Refunds' => __('Refunds', 'fluentformpro'),
            'Refund' => __('Refund', 'fluentformpro'),
            'at' => __('at', 'fluentformpro'),
            'View' => __('View', 'fluentformpro'),
            'has been refunded via' => __('has been refunded via', 'fluentformpro'),
            'Note' => __('Note', 'fluentformpro'),
            'Edit Transaction' => __('Edit Transaction', 'fluentformpro'),
            'Billing Name' => __('Billing Name', 'fluentformpro'),
            'Billing Email' => __('Billing Email', 'fluentformpro'),
            'Billing Address' => __('Billing Address', 'fluentformpro'),
            'Shipping Address' => __('Shipping Address', 'fluentformpro'),
            'Reference ID' => __('Reference ID', 'fluentformpro'),
            'refunds-to-be-handled-from-provider-text' => __('Please note that, Actual Refund needs to be handled in your Payment Service Provider.', 'fluentformpro'),
            'Please Provide new refund amount only.' => __('Please Provide new refund amount only.', 'fluentformpro'),
            'Refund Note' => __('Refund Note', 'fluentformpro'),
            'Cancel' => __('Cancel', 'fluentformpro'),
            'Confirm' => __('Confirm', 'fluentformpro'),
        );
        return array_merge($i18n,$paymentI18n);
    }

}
