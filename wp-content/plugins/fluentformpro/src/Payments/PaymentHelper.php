<?php

namespace FluentFormPro\Payments;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Modules\Form\FormHandler;
use FluentForm\App\Services\FormBuilder\ShortCodeParser;
use FluentForm\Framework\Helpers\ArrayHelper;

class PaymentHelper
{
    public static function getFormCurrency($formId)
    {
        $settings = self::getFormSettings($formId, 'public');
        return $settings['currency'];
    }

    public static function formatMoney($amountInCents, $currency)
    {
        $currencySettings = self::getCurrencyConfig(false, $currency);
        $symbol = \html_entity_decode($currencySettings['currency_sign']);
        $position = $currencySettings['currency_sign_position'];
        $decimalSeparator = '.';
        $thousandSeparator = ',';
        if ($currencySettings['currency_separator'] != 'dot_comma') {
            $decimalSeparator = ',';
            $thousandSeparator = '.';
        }
        $decimalPoints = 2;
        if ($amountInCents % 100 == 0 && $currencySettings['decimal_points'] == 0) {
            $decimalPoints = 0;
        }

        $amount = number_format($amountInCents / 100, $decimalPoints, $decimalSeparator, $thousandSeparator);

        if ('left' === $position) {
            return $symbol . $amount;
        } elseif ('left_space' === $position) {
            return $symbol . ' ' . $amount;
        } elseif ('right' === $position) {
            return $amount . $symbol;
        } elseif ('right_space' === $position) {
            return $amount . ' ' . $symbol;
        }
        return $amount;
    }

    public static function getFormSettings($formId, $scope = 'public')
    {
        static $cachedSettings = [];

        if (isset($cachedSettings[$scope . '_' . $formId])) {
            return $cachedSettings[$scope . '_' . $formId];
        }

        $defaults = [
            'currency'                       => '',
            'push_meta_to_stripe'            => 'no',
            'receipt_email'                  => '',
            'customer_name'                  => '',
            'transaction_type'               => 'product',
            'stripe_checkout_methods'        => ['card'],
            'stripe_meta_data'               => [
                [
                    'item_value' => '',
                    'label'      => ''
                ]
            ],
            'stripe_account_type'            => 'global',
            'disable_stripe_payment_receipt' => 'no',
            'stripe_custom_config'           => [
                'payment_mode'    => 'live',
                'publishable_key' => '',
                'secret_key'      => ''
            ],
            'custom_paypal_id'               => '',
            'custom_paypal_mode'             => 'live',
            'paypal_account_type'            => 'global'
        ];

        $settings = Helper::getFormMeta($formId, '_payment_settings', []);
        $settings = wp_parse_args($settings, $defaults);

        $globalSettings = self::getPaymentSettings();

        if (!$settings['currency']) {
            $settings['currency'] = $globalSettings['currency'];
        }

        if ($scope == 'public') {
            $settings = wp_parse_args($settings, $globalSettings);
        }


        $cachedSettings[$scope . '_' . $formId] = $settings;

        return $settings;

    }

    public static function getCurrencyConfig($formId = false, $currency = false)
    {
        if ($formId) {
            $settings = self::getFormSettings($formId, 'public');
        } else {
            $settings = self::getPaymentSettings();
        }

        if ($currency) {
            $settings['currency'] = $currency;
        }

        $settings = ArrayHelper::only($settings, ['currency', 'currency_sign_position', 'currency_separator', 'decimal_points']);

        $settings['currency_sign'] = self::getCurrencySymbol($settings['currency']);
        return $settings;
    }

    public static function getPaymentSettings()
    {
        static $paymentSettings;
        if ($paymentSettings) {
            return $paymentSettings;
        }

        $paymentSettings = get_option('__fluentform_payment_module_settings');
        $defaults = [
            'status'                       => 'no',
            'currency'                     => 'USD',
            'currency_sign_position'       => 'left',
            'currency_separator'           => 'dot_comma',
            'decimal_points'               => "2",
            'business_name'                => '',
            'business_logo'                => '',
            'business_address'             => '',
            'debug_log'                    => 'no',
            'all_payments_page_id'         => '',
            'receipt_page_id'              => '',
            'user_can_manage_subscription' => 'yes'
        ];

        $paymentSettings = wp_parse_args($paymentSettings, $defaults);

        return $paymentSettings;
    }

    public static function updatePaymentSettings($data)
    {
        $existingSettings = self::getPaymentSettings();
        $settings = wp_parse_args($data, $existingSettings);
        update_option('__fluentform_payment_module_settings', $settings, 'yes');

        return self::getPaymentSettings();
    }

    /**
     * https://support.stripe.com/questions/which-currencies-does-stripe-support
     */
    public static function getCurrencies()
    {
        return apply_filters('fluentform_accepted_currencies', array(
            'AED' => 'United Arab Emirates Dirham',
            'AFN' => 'Afghan Afghani',
            'ALL' => 'Albanian Lek',
            'AMD' => 'Armenian Dram',
            'ANG' => 'Netherlands Antillean Gulden',
            'AOA' => 'Angolan Kwanza',
            'ARS' => 'Argentine Peso', // non amex
            'AUD' => 'Australian Dollar',
            'AWG' => 'Aruban Florin',
            'AZN' => 'Azerbaijani Manat',
            'BAM' => 'Bosnia & Herzegovina Convertible Mark',
            'BBD' => 'Barbadian Dollar',
            'BDT' => 'Bangladeshi Taka',
            'BIF' => 'Burundian Franc',
            'BGN' => 'Bulgarian Lev',
            'BMD' => 'Bermudian Dollar',
            'BND' => 'Brunei Dollar',
            'BOB' => 'Bolivian Boliviano',
            'BRL' => 'Brazilian Real',
            'BSD' => 'Bahamian Dollar',
            'BWP' => 'Botswana Pula',
            'BZD' => 'Belize Dollar',
            'CAD' => 'Canadian Dollar',
            'CDF' => 'Congolese Franc',
            'CHF' => 'Swiss Franc',
            'CLP' => 'Chilean Peso',
            'CNY' => 'Chinese Renminbi Yuan',
            'COP' => 'Colombian Peso',
            'CRC' => 'Costa Rican Colón',
            'CVE' => 'Cape Verdean Escudo',
            'CZK' => 'Czech Koruna',
            'DJF' => 'Djiboutian Franc',
            'DKK' => 'Danish Krone',
            'DOP' => 'Dominican Peso',
            'DZD' => 'Algerian Dinar',
            'EGP' => 'Egyptian Pound',
            'ETB' => 'Ethiopian Birr',
            'EUR' => 'Euro',
            'FJD' => 'Fijian Dollar',
            'FKP' => 'Falkland Islands Pound',
            'GBP' => 'British Pound',
            'GEL' => 'Georgian Lari',
            'GHS' => 'Ghanaian Cedi',
            'GIP' => 'Gibraltar Pound',
            'GMD' => 'Gambian Dalasi',
            'GNF' => 'Guinean Franc',
            'GTQ' => 'Guatemalan Quetzal',
            'GYD' => 'Guyanese Dollar',
            'HKD' => 'Hong Kong Dollar',
            'HNL' => 'Honduran Lempira',
            'HRK' => 'Croatian Kuna',
            'HTG' => 'Haitian Gourde',
            'HUF' => 'Hungarian Forint',
            'IDR' => 'Indonesian Rupiah',
            'ILS' => 'Israeli New Sheqel',
            'INR' => 'Indian Rupee',
            'ISK' => 'Icelandic Króna',
            'JMD' => 'Jamaican Dollar',
            'JPY' => 'Japanese Yen',
            'KES' => 'Kenyan Shilling',
            'KGS' => 'Kyrgyzstani Som',
            'KHR' => 'Cambodian Riel',
            'KMF' => 'Comorian Franc',
            'KRW' => 'South Korean Won',
            'KYD' => 'Cayman Islands Dollar',
            'KZT' => 'Kazakhstani Tenge',
            'LAK' => 'Lao Kip',
            'LBP' => 'Lebanese Pound',
            'LKR' => 'Sri Lankan Rupee',
            'LRD' => 'Liberian Dollar',
            'LSL' => 'Lesotho Loti',
            'MAD' => 'Moroccan Dirham',
            'MDL' => 'Moldovan Leu',
            'MGA' => 'Malagasy Ariary',
            'MKD' => 'Macedonian Denar',
            'MNT' => 'Mongolian Tögrög',
            'MOP' => 'Macanese Pataca',
            'MRO' => 'Mauritanian Ouguiya',
            'MUR' => 'Mauritian Rupee',
            'MVR' => 'Maldivian Rufiyaa',
            'MWK' => 'Malawian Kwacha',
            'MXN' => 'Mexican Peso',
            'MYR' => 'Malaysian Ringgit',
            'MZN' => 'Mozambican Metical',
            'NAD' => 'Namibian Dollar',
            'NGN' => 'Nigerian Naira',
            'NIO' => 'Nicaraguan Córdoba',
            'NOK' => 'Norwegian Krone',
            'NPR' => 'Nepalese Rupee',
            'NZD' => 'New Zealand Dollar',
            'PAB' => 'Panamanian Balboa',
            'PEN' => 'Peruvian Nuevo Sol',
            'PGK' => 'Papua New Guinean Kina',
            'PHP' => 'Philippine Peso',
            'PKR' => 'Pakistani Rupee',
            'PLN' => 'Polish Złoty',
            'PYG' => 'Paraguayan Guaraní',
            'QAR' => 'Qatari Riyal',
            'RON' => 'Romanian Leu',
            'RSD' => 'Serbian Dinar',
            'RUB' => 'Russian Ruble',
            'RWF' => 'Rwandan Franc',
            'SAR' => 'Saudi Riyal',
            'SBD' => 'Solomon Islands Dollar',
            'SCR' => 'Seychellois Rupee',
            'SEK' => 'Swedish Krona',
            'SGD' => 'Singapore Dollar',
            'SHP' => 'Saint Helenian Pound',
            'SLL' => 'Sierra Leonean Leone',
            'SOS' => 'Somali Shilling',
            'SRD' => 'Surinamese Dollar',
            'STD' => 'São Tomé and Príncipe Dobra',
            'SVC' => 'Salvadoran Colón',
            'SZL' => 'Swazi Lilangeni',
            'THB' => 'Thai Baht',
            'TJS' => 'Tajikistani Somoni',
            'TOP' => 'Tongan Paʻanga',
            'TRY' => 'Turkish Lira',
            'TTD' => 'Trinidad and Tobago Dollar',
            'TWD' => 'New Taiwan Dollar',
            'TZS' => 'Tanzanian Shilling',
            'UAH' => 'Ukrainian Hryvnia',
            'UGX' => 'Ugandan Shilling',
            'USD' => 'United States Dollar',
            'UYU' => 'Uruguayan Peso',
            'UZS' => 'Uzbekistani Som',
            'VND' => 'Vietnamese Đồng',
            'VUV' => 'Vanuatu Vatu',
            'WST' => 'Samoan Tala',
            'XAF' => 'Central African Cfa Franc',
            'XCD' => 'East Caribbean Dollar',
            'XOF' => 'West African Cfa Franc',
            'XPF' => 'Cfp Franc',
            'YER' => 'Yemeni Rial',
            'ZAR' => 'South African Rand',
            'ZMW' => 'Zambian Kwacha',
        ));
    }

    /**
     * Get a specific currency symbol
     *
     * https://support.stripe.com/questions/which-currencies-does-stripe-support
     */
    public static function getCurrencySymbol($currency = '')
    {
        if (!$currency) {
            // If no currency is passed then default it to USD
            $currency = 'USD';
        }
        $currency = strtoupper($currency);

        $symbols = self::getCurrencySymbols();
        $currency_symbol = isset($symbols[$currency]) ? $symbols[$currency] : '';
        return apply_filters('fluentform_currency_symbol', $currency_symbol, $currency);
    }

    public static function getCurrencySymbols()
    {
        return apply_filters('fluentform_currency_symbols', array(
            'AED' => '&#x62f;.&#x625;',
            'AFN' => '&#x60b;',
            'ALL' => 'L',
            'AMD' => 'AMD',
            'ANG' => '&fnof;',
            'AOA' => 'Kz',
            'ARS' => '&#36;',
            'AUD' => '&#36;',
            'AWG' => '&fnof;',
            'AZN' => 'AZN',
            'BAM' => 'KM',
            'BBD' => '&#36;',
            'BDT' => '&#2547;&nbsp;',
            'BGN' => '&#1083;&#1074;.',
            'BHD' => '.&#x62f;.&#x628;',
            'BIF' => 'Fr',
            'BMD' => '&#36;',
            'BND' => '&#36;',
            'BOB' => 'Bs.',
            'BRL' => '&#82;&#36;',
            'BSD' => '&#36;',
            'BTC' => '&#3647;',
            'BTN' => 'Nu.',
            'BWP' => 'P',
            'BYR' => 'Br',
            'BZD' => '&#36;',
            'CAD' => '&#36;',
            'CDF' => 'Fr',
            'CHF' => '&#67;&#72;&#70;',
            'CLP' => '&#36;',
            'CNY' => '&yen;',
            'COP' => '&#36;',
            'CRC' => '&#x20a1;',
            'CUC' => '&#36;',
            'CUP' => '&#36;',
            'CVE' => '&#36;',
            'CZK' => '&#75;&#269;',
            'DJF' => 'Fr',
            'DKK' => 'DKK',
            'DOP' => 'RD&#36;',
            'DZD' => '&#x62f;.&#x62c;',
            'EGP' => 'EGP',
            'ERN' => 'Nfk',
            'ETB' => 'Br',
            'EUR' => '&euro;',
            'FJD' => '&#36;',
            'FKP' => '&pound;',
            'GBP' => '&pound;',
            'GEL' => '&#x10da;',
            'GGP' => '&pound;',
            'GHS' => '&#x20b5;',
            'GIP' => '&pound;',
            'GMD' => 'D',
            'GNF' => 'Fr',
            'GTQ' => 'Q',
            'GYD' => '&#36;',
            'HKD' => '&#36;',
            'HNL' => 'L',
            'HRK' => 'Kn',
            'HTG' => 'G',
            'HUF' => '&#70;&#116;',
            'IDR' => 'Rp',
            'ILS' => '&#8362;',
            'IMP' => '&pound;',
            'INR' => '&#8377;',
            'IQD' => '&#x639;.&#x62f;',
            'IRR' => '&#xfdfc;',
            'ISK' => 'Kr.',
            'JEP' => '&pound;',
            'JMD' => '&#36;',
            'JOD' => '&#x62f;.&#x627;',
            'JPY' => '&yen;',
            'KES' => 'KSh',
            'KGS' => '&#x43b;&#x432;',
            'KHR' => '&#x17db;',
            'KMF' => 'Fr',
            'KPW' => '&#x20a9;',
            'KRW' => '&#8361;',
            'KWD' => '&#x62f;.&#x643;',
            'KYD' => '&#36;',
            'KZT' => 'KZT',
            'LAK' => '&#8365;',
            'LBP' => '&#x644;.&#x644;',
            'LKR' => '&#xdbb;&#xdd4;',
            'LRD' => '&#36;',
            'LSL' => 'L',
            'LYD' => '&#x644;.&#x62f;',
            'MAD' => '&#x62f;. &#x645;.',
            'MDL' => 'L',
            'MGA' => 'Ar',
            'MKD' => '&#x434;&#x435;&#x43d;',
            'MMK' => 'Ks',
            'MNT' => '&#x20ae;',
            'MOP' => 'P',
            'MRO' => 'UM',
            'MUR' => '&#x20a8;',
            'MVR' => '.&#x783;',
            'MWK' => 'MK',
            'MXN' => '&#36;',
            'MYR' => '&#82;&#77;',
            'MZN' => 'MT',
            'NAD' => '&#36;',
            'NGN' => '&#8358;',
            'NIO' => 'C&#36;',
            'NOK' => '&#107;&#114;',
            'NPR' => '&#8360;',
            'NZD' => '&#36;',
            'OMR' => '&#x631;.&#x639;.',
            'PAB' => 'B/.',
            'PEN' => 'S/.',
            'PGK' => 'K',
            'PHP' => '&#8369;',
            'PKR' => '&#8360;',
            'PLN' => '&#122;&#322;',
            'PRB' => '&#x440;.',
            'PYG' => '&#8370;',
            'QAR' => '&#x631;.&#x642;',
            'RMB' => '&yen;',
            'RON' => 'lei',
            'RSD' => '&#x434;&#x438;&#x43d;.',
            'RUB' => '&#8381;',
            'RWF' => 'Fr',
            'SAR' => '&#x631;.&#x633;',
            'SBD' => '&#36;',
            'SCR' => '&#x20a8;',
            'SDG' => '&#x62c;.&#x633;.',
            'SEK' => '&#107;&#114;',
            'SGD' => '&#36;',
            'SHP' => '&pound;',
            'SLL' => 'Le',
            'SOS' => 'Sh',
            'SRD' => '&#36;',
            'SSP' => '&pound;',
            'STD' => 'Db',
            'SYP' => '&#x644;.&#x633;',
            'SZL' => 'L',
            'THB' => '&#3647;',
            'TJS' => '&#x405;&#x41c;',
            'TMT' => 'm',
            'TND' => '&#x62f;.&#x62a;',
            'TOP' => 'T&#36;',
            'TRY' => '&#8378;',
            'TTD' => '&#36;',
            'TWD' => '&#78;&#84;&#36;',
            'TZS' => 'Sh',
            'UAH' => '&#8372;',
            'UGX' => 'UGX',
            'USD' => '&#36;',
            'UYU' => '&#36;',
            'UZS' => 'UZS',
            'VEF' => 'Bs F',
            'VND' => '&#8363;',
            'VUV' => 'Vt',
            'WST' => 'T',
            'XAF' => 'Fr',
            'XCD' => '&#36;',
            'XOF' => 'Fr',
            'XPF' => 'Fr',
            'YER' => '&#xfdfc;',
            'ZAR' => '&#82;',
            'ZMW' => 'ZK',
        ));
    }

    public static function zeroDecimalCurrencies()
    {
        return apply_filters('fluentform_zero_decimal_currencies', array(
            'BIF' => esc_html__('Burundian Franc', 'fluentformpro'),
            'CLP' => esc_html__('Chilean Peso', 'fluentformpro'),
            'DJF' => esc_html__('Djiboutian Franc', 'fluentformpro'),
            'GNF' => esc_html__('Guinean Franc', 'fluentformpro'),
            'JPY' => esc_html__('Japanese Yen', 'fluentformpro'),
            'KMF' => esc_html__('Comorian Franc', 'fluentformpro'),
            'KRW' => esc_html__('South Korean Won', 'fluentformpro'),
            'MGA' => esc_html__('Malagasy Ariary', 'fluentformpro'),
            'PYG' => esc_html__('Paraguayan Guaraní', 'fluentformpro'),
            'RWF' => esc_html__('Rwandan Franc', 'fluentformpro'),
            'VND' => esc_html__('Vietnamese Dong', 'fluentformpro'),
            'VUV' => esc_html__('Vanuatu Vatu', 'fluentformpro'),
            'XAF' => esc_html__('Central African Cfa Franc', 'fluentformpro'),
            'XOF' => esc_html__('West African Cfa Franc', 'fluentformpro'),
            'XPF' => esc_html__('Cfp Franc', 'fluentformpro'),
        ));
    }

    public static function isZeroDecimal($currencyCode)
    {
        $currencyCode = strtoupper($currencyCode);
        $zeroDecimals = self::zeroDecimalCurrencies();
        return isset($zeroDecimals[$currencyCode]);
    }

    public static function getPaymentStatuses()
    {
        return apply_filters('fluentform_available_payment_statuses', array(
            'paid'               => __('Paid', 'fluentformpro'),
            'processing'         => __('Processing', 'fluentformpro'),
            'pending'            => __('Pending', 'fluentformpro'),
            'failed'             => __('Failed', 'fluentformpro'),
            'refunded'           => __('Refunded', 'fluentformpro'),
            'partially-refunded' => __('Partial Refunded', 'fluentformpro'),
            'cancelled'          => __('Cancelled', 'fluentformpro')
        ));
    }

    public static function getFormPaymentMethods($formId)
    {
        $inputs = FormFieldsParser::getInputs($formId, ['element', 'settings']);
        foreach ($inputs as $field) {
            if ($field['element'] == 'payment_method') {
                $methods = ArrayHelper::get($field, 'settings.payment_methods');
                if (is_array($methods)) {
                    return array_filter($methods, function ($method) {
                        return $method['enabled'] == 'yes';
                    });
                }
            }
        }
        return [];
    }

    public static function getCustomerEmail($submission, $form = false)
    {

        $formSettings = PaymentHelper::getFormSettings($submission->form_id, 'admin');
        $customerEmailField = ArrayHelper::get($formSettings, 'receipt_email');

        if ($customerEmailField) {
            $email = ArrayHelper::get($submission->response, $customerEmailField);
            if ($email) {
                return $email;
            }
        }

        $user = get_user_by('ID', get_current_user_id());

        if ($user) {
            return $user->user_email;
        }

        if (!$form) {
            return '';
        }

        $emailFields = FormFieldsParser::getInputsByElementTypes($form, ['input_email'], ['attributes']);

        foreach ($emailFields as $field) {
            $fieldName = $field['attributes']['name'];
            if (!empty($submission->response[$fieldName])) {
                return $submission->response[$fieldName];
            }
        }

        return '';

    }

    /**
     * Trim a string and append a suffix.
     *
     * @param string $string String to trim.
     * @param integer $chars Amount of characters.
     *                         Defaults to 200.
     * @param string $suffix Suffix.
     *                         Defaults to '...'.
     * @return string
     */
    public static function formatPaymentItemString($string, $chars = 200, $suffix = '...')
    {
        $string = wp_strip_all_tags($string);
        if (strlen($string) > $chars) {
            if (function_exists('mb_substr')) {
                $string = mb_substr($string, 0, ($chars - mb_strlen($suffix))) . $suffix;
            } else {
                $string = substr($string, 0, ($chars - strlen($suffix))) . $suffix;
            }
        }

        return html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * Limit length of an arg.
     *
     * @param string $string Argument to limit.
     * @param integer $limit Limit size in characters.
     * @return string
     */
    public static function limitLength($string, $limit = 127)
    {
        $str_limit = $limit - 3;
        if (function_exists('mb_strimwidth')) {
            if (mb_strlen($string) > $limit) {
                $string = mb_strimwidth($string, 0, $str_limit) . '...';
            }
        } else {
            if (strlen($string) > $limit) {
                $string = substr($string, 0, $str_limit) . '...';
            }
        }
        return $string;
    }

    public static function floatToString($float)
    {
        if (!is_float($float)) {
            return $float;
        }

        $locale = localeconv();
        $string = strval($float);
        $string = str_replace($locale['decimal_point'], '.', $string);

        return $string;
    }

    public static function convertToCents($amount)
    {
        if (!$amount) {
            return 0;
        }

        $amount = floatval($amount);

        return intval(round($amount * 100));
    }

    public static function getCustomerName($submission, $form = false)
    {
        $formSettings = PaymentHelper::getFormSettings($submission->form_id, 'admin');
        $customerNameCode = ArrayHelper::get($formSettings, 'customer_name');
        if ($customerNameCode) {
            $customerName = ShortCodeParser::parse($customerNameCode, $submission->id, $submission->response);
            if ($customerName) {
                return $customerName;
            }
        }

        $user = get_user_by('ID', get_current_user_id());

        if ($user) {
            $customerName = trim($user->first_name . ' ' . $user->last_name);
            if (!$customerName) {
                $customerName = $user->display_name;
            }
            if ($customerName) {
                return $customerName;
            }
        }

        if (!$form) {
            return '';
        }

        $nameFields = FormFieldsParser::getInputsByElementTypes($form, ['input_name'], ['attributes']);

        $fieldName = false;
        foreach ($nameFields as $field) {
            if ($field['element'] === 'input_name') {
                $fieldName = $field['attributes']['name'];
                break;
            }
        }

        $name = '';
        if ($fieldName) {
            if (!empty($submission->response[$fieldName])) {
                $names = array_filter($submission->response[$fieldName]);
                return trim(implode(' ', $names));
            }
        }

        return $name;
    }

    public static function getStripeInlineConfig($formId)
    {
        $methods = static::getFormPaymentMethods($formId);

        $stripe = ArrayHelper::get($methods, 'stripe');

        if ($stripe) {
            return [
                'is_inline' => ArrayHelper::get($stripe, 'settings.embedded_checkout.value') == 'yes',
                'verifyZip' => ArrayHelper::get($methods['stripe'], 'settings.verify_zip_code.value') === 'yes',
            ];
        }

        return [];
    }

    public static function log($data, $submission = false, $forceInsert = false)
    {
        if (!$forceInsert) {
            static $paymentSettings;
            if (!$paymentSettings) {
                $paymentSettings = self::getPaymentSettings();
            }

            if (!isset($paymentSettings['debug_log']) && $paymentSettings['debug_log'] != 'yes') {
                return false;
            }
        }

        $defaults = [
            'component'  => 'Payment',
            'status'     => 'info',
            'created_at' => current_time('mysql')
        ];

        if ($submission) {
            $defaults['parent_source_id'] = $submission->form_id;
            $defaults['source_type'] = 'submission_item';
            $defaults['source_id'] = $submission->id;
        } else {
            $defaults['source_type'] = 'system_log';
        }

        $data = wp_parse_args($data, $defaults);

        return wpFluent()->table('fluentform_logs')
            ->insert($data);

    }

    public static function maybeFireSubmissionActionHok($submission)
    {
        if (Helper::getSubmissionMeta($submission->id, 'is_form_action_fired') == 'yes') {
            return false;
        }

        $form = wpFluent()->table('fluentform_forms')->where('id', $submission->form_id)->first();

        $formData = $submission->response;
        if (!is_array($formData)) {
            $formData = json_decode($formData, true);
        }

        (new FormHandler(wpFluentForm()))->processFormSubmissionData(
            $submission->id, $formData, $form
        );
        Helper::setSubmissionMeta($submission->id, 'is_form_action_fired', 'yes');
        return true;
    }

    public static function loadView($fileName, $data)
    {
        // normalize the filename
        $fileName = str_replace(array('../', './'), '', $fileName);
        $basePath = apply_filters('fluentform_payment_receipt_template_base_path', FLUENTFORMPRO_DIR_PATH . 'src/views/receipt/', $fileName, $data);
        $filePath = $basePath . $fileName . '.php';
        extract($data);
        ob_start();
        include $filePath;
        return ob_get_clean();
    }

    public static function recordSubscriptionCancelled($subscription, $vendorData, $logData = [])
    {
        wpFluent()->table('fluentform_subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'status'     => 'cancelled',
                'updated_at' => current_time('mysql')
            ]);

        $subscription = wpFluent()->table('fluentform_subscriptions')
            ->where('id', $subscription->id)
            ->first();

        $submission = wpFluent()->table('fluentform_submissions')
            ->where('id', $subscription->submission_id)
            ->first();

        $logDefaults = [
            'parent_source_id' => $subscription->form_id,
            'source_type'      => 'submission_item',
            'source_id'        => $subscription->submission_id,
            'component'        => 'Payment',
            'status'           => 'info',
            'title'            => 'Subscription has been cancelled',
            'description'      => 'Subscription has been cancelled from ' . $submission->payment_method
        ];

        do_action('ff_log_data', wp_parse_args($logData, $logDefaults));

        // New Payment Made so we have to fire some events here
        do_action('fluentform_subscription_payment_canceled', $subscription, $submission, $vendorData);
        do_action('fluentform_subscription_payment_canceled_'.$submission->payment_method, $subscription, $submission, $vendorData);
    }

    public static function getPaymentSummaryText($plan, $formId, $currency, $withMarkup = true)
    {
        $cases = apply_filters('fluentform_recurring_payment_summary_texts', [
            'has_signup_fee' => __('{first_interval_total} for first {billing_interval} then {subscription_amount} for each {billing_interval}', 'fluentformpro'),
            'has_trial'      => __('Free for {trial_days} days then {subscription_amount} for each {billing_interval}', 'fluentformpro'),
            'onetime_only'   => __('One time payment of {first_interval_total}', 'fluentformpro'),
            'normal'         => __('{subscription_amount} for each {billing_interval}', 'fluentformpro'),
            'bill_times'     => __(', for {bill_times} installments', 'fluentformpro'),
            'single_trial'   => __('Free for {trial_days} days then {subscription_amount} one time')
        ], $plan, $formId);

        // if is trial
        $hasTrial = ArrayHelper::get($plan, 'has_trial_days') == 'yes' && ArrayHelper::get($plan, 'trial_days');
        if ($hasTrial) {
            $plan['signup_fee'] = 0;
        }

        $signupFee = 0;
        $hasSignupFee = ArrayHelper::get($plan, 'has_signup_fee') == 'yes' && ArrayHelper::get($plan, 'signup_fee');
        if ($hasSignupFee) {
            $plan['trial_days'] = 0;
            $signupFee = ArrayHelper::get($plan, 'signup_fee');
        }

        $firstIntervalTotal = PaymentHelper::formatMoney(
            PaymentHelper::convertToCents($signupFee + ArrayHelper::get($plan, 'subscription_amount')),
            $currency
        );

        if ($signupFee) {
            $signupFee = PaymentHelper::formatMoney(
                PaymentHelper::convertToCents($signupFee),
                $currency
            );
        }

        $subscriptionAmount = PaymentHelper::formatMoney(
            PaymentHelper::convertToCents(ArrayHelper::get($plan, 'subscription_amount')),
            $currency
        );

        $billingInterval = $plan['billing_interval'];
        $billingInterval = ArrayHelper::get(self::getBillingIntervals(), $billingInterval, $billingInterval);
        $replaces = array(
            '{signup_fee}'           => '<span class="ff_bs ffbs_signup_fee">' . $signupFee . '</span>',
            '{first_interval_total}' => '<span class="ff_bs ffbs_first_interval_total">' . $firstIntervalTotal . '</span>',
            '{subscription_amount}'  => '<span class="ff_bs ffbs_subscription_amount">' . $subscriptionAmount . '</span>',
            '{billing_interval}'     => '<span class="ff_bs ffbs_billing_interval">' . $billingInterval . '</span>',
            '{trial_days}'           => '<span class="ff_bs ffbs_trial_days">' . $plan['trial_days'] . '</span>',
            '{bill_times}'           => '<span class="ff_bs ffbs_bill_times">' . ArrayHelper::get($plan, 'bill_times') . '</span>'
        );

        if (ArrayHelper::get($plan, 'user_input') == 'yes') {
            $cases['{subscription_amount}'] = '<span class="ff_dynamic_input_amount">' . $subscriptionAmount . '</span>';
        }

        foreach ($cases as $textKey => $text) {
            $cases[$textKey] = str_replace(array_keys($replaces), array_values($replaces), $text);
        }

        $customText = '';
        if ($hasSignupFee) {
            $customText = $cases['has_signup_fee'];
        } else if ($hasTrial) {
            if (ArrayHelper::get($plan, 'bill_times') == 1) {
                $customText = $cases['single_trial'];
            } else {
                $customText = $cases['has_trial'];
            }
        } else if (isset($plan['bill_times']) && $plan['bill_times'] == 1) {
            $customText = $cases['onetime_only'];
        } else {
            $customText = $cases['normal'];
        }

        if (isset($plan['bill_times']) && $plan['bill_times'] > 1) {
            $customText .= $cases['bill_times'];
        }
        if($withMarkup) {
            $class = $plan['is_default'] === 'yes' ? '' : 'hidden_field';
            return '<div class="ff_summary_container ff_summary_container_' . $plan['index'] . ' ' . $class . '">' . $customText . '</div>';
        }
        return $customText;
    }

	public static function getCustomerAddress($submission)
	{
		$formSettings = PaymentHelper::getFormSettings($submission->form_id, 'admin');
		$customerAddressField = ArrayHelper::get($formSettings, 'customer_address');

		if ($customerAddressField) {
            return ArrayHelper::get($submission->response, $customerAddressField);
		}

		return null;
	}

    public static function getBillingIntervals()
    {
        return [
            'day'   => __('day', 'fluentformpro'),
            'week'  => __('week', 'fluentformpro'),
            'month' => __('month', 'fluentformpro'),
            'year'  => __('year', 'fluentformpro')
        ];
    }

    public static function getSubscriptionStatuses()
    {
        return [
            'active'    => __('active', 'fluentformpro'),
            'trialling' => __('trialling', 'fluentformpro'),
            'failing'   => __('failing', 'fluentformpro'),
            'cancelled' => __('cancelled', 'fluentformpro')
        ];
    }
}
