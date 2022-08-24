<?php

namespace FluentFormPro\Payments\PaymentMethods\Stripe\API;

use FluentFormPro\Payments\PaymentMethods\Stripe\StripeSettings;

if (!defined('ABSPATH')) {
	exit;
}

class Customer
{
	use RequestProcessor;

	public static function createCustomer($customerArgs, $formId)
	{
		$errors = static::validate($customerArgs);

		if ($errors) {
			return static::errorHandler('validation_failed', __('Payment data validation failed', 'fluentformpro'), $errors);
		}

		try {
			$secretKey = apply_filters('fluentform-payment_stripe_secret_key', StripeSettings::getSecretKey($formId), $formId);

			ApiRequest::set_secret_key($secretKey);

			$response = ApiRequest::request($customerArgs, 'customers');

			$response = static::processResponse($response);

			do_action('fluentform_stripe_customer_created', $response, $customerArgs);

			return $response;
		} catch (\Exception $e) {
			// Something else happened, completely unrelated to Stripe
			return static::errorHandler('non_stripe', esc_html__('General Error', 'fluentformpro') . ': ' . $e->getMessage());
		}
	}

	public static function validate($args)
	{
		$errors = [];

		if (empty($args['source']) && empty($args['payment_method'])) {
			$errors['source'] = __('Stripe token/payment_method is required', 'fluentformpro');
		}

		return $errors;
	}
}
