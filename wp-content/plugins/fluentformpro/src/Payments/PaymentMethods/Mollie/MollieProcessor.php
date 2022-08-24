<?php

namespace FluentFormPro\Payments\PaymentMethods\Mollie;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;
use FluentFormPro\Payments\PaymentMethods\Mollie\API\IPN;

class MollieProcessor extends BaseProcessor
{
    public $method = 'mollie';

    protected $form;

    public function init()
    {
        add_action('fluentform_process_payment_' . $this->method, array($this, 'handlePaymentAction'), 10, 6);
        add_action('fluent_payment_frameless_' . $this->method, array($this, 'handleSessionRedirectBack'));

        add_action('fluentform_ipn_endpoint_' . $this->method, function () {
            (new IPN())->verifyIPN();
            exit(200);
        });

        add_action('fluentform_ipn_mollie_action_paid', array($this, 'handlePaid'), 10, 2);
        add_action('fluentform_ipn_mollie_action_refunded', array($this, 'handleRefund'), 10, 3);

	    add_filter(
		    'fluentform_submitted_payment_items_' . $this->method,
		    [$this, 'validateSubmittedItems'], 10, 4
	    );
    }

    public function handlePaymentAction($submissionId, $submissionData, $form, $methodSettings, $hasSubscriptions, $totalPayable)
    {
        $this->setSubmissionId($submissionId);
        $this->form = $form;
        $submission = $this->getSubmission();

        $uniqueHash = md5($submission->id . '-' . $form->id . '-' . time() . '-' . mt_rand(100, 999));

        $transactionId = $this->insertTransaction([
            'transaction_type' => 'onetime',
            'transaction_hash' => $uniqueHash,
            'payment_total'    => $this->getAmountTotal(),
            'status'           => 'pending',
            'currency'         => PaymentHelper::getFormCurrency($form->id),
            'payment_mode'     => $this->getPaymentMode()
        ]);

        $transaction = $this->getTransaction($transactionId);

        $this->handleRedirect($transaction, $submission, $form, $methodSettings);
    }

    public function handleRedirect($transaction, $submission, $form, $methodSettings)
    {
        $successUrl = add_query_arg(array(
            'fluentform_payment' => $submission->id,
            'payment_method'     => $this->method,
            'transaction_hash'   => $transaction->transaction_hash,
            'type'               => 'success'
        ), site_url('/'));

        $ipnDomain = site_url('index.php');
        if(defined('FLUENTFORM_PAY_IPN_DOMAIN') && FLUENTFORM_PAY_IPN_DOMAIN) {
            $ipnDomain = FLUENTFORM_PAY_IPN_DOMAIN;
        }

        $listener_url = add_query_arg(array(
            'fluentform_payment_api_notify' => 1,
            'payment_method'                => $this->method,
            'submission_id'                 => $submission->id,
            'transaction_hash'              => $transaction->transaction_hash,
        ), $ipnDomain);

        $paymentArgs = array(
            'amount' => [
                'currency' => $transaction->currency,
                'value' => number_format((float) $transaction->payment_total / 100, 2, '.', '')
            ],
            'description' => $form->title,
            'redirectUrl' => $successUrl,
            'webhookUrl' => $listener_url,
            'metadata' => json_encode([
                'form_id' => $form->id,
                'submission_id' => $submission->id
            ]),
            'sequenceType' => 'oneoff'
        );

        $paymentArgs = apply_filters('fluentform_mollie_payment_args', $paymentArgs, $submission, $transaction, $form);
        $paymentIntent = (new IPN())->makeApiCall('payments', $paymentArgs, $form->id, 'POST');

        if(is_wp_error($paymentIntent)) {
            do_action('ff_log_data', [
                'parent_source_id' => $submission->form_id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => 'Mollie Payment Redirect Error',
                'description'      => $paymentIntent->get_error_message()
            ]);
            wp_send_json_success([
                'message'      => $paymentIntent->get_error_message()
            ], 423);
        }

        Helper::setSubmissionMeta($submission->id, '_mollie_payment_id', $paymentIntent['id']);

        do_action('ff_log_data', [
            'parent_source_id' => $submission->form_id,
            'source_type'      => 'submission_item',
            'source_id'        => $submission->id,
            'component'        => 'Payment',
            'status'           => 'info',
            'title'            => 'Redirect to Mollie',
            'description'      => 'User redirect to Mollie for completing the payment'
        ]);

        wp_send_json_success([
            'nextAction'   => 'payment',
            'actionName'   => 'normalRedirect',
            'redirect_url' => $paymentIntent['_links']['checkout']['href'],
            'message'      => __('You are redirecting to Mollie.com to complete the purchase. Please wait while you are redirecting....', 'fluentformpro'),
            'result'       => [
                'insert_id' => $submission->id
            ]
        ], 200);
    }

    protected function getPaymentMode($formId = false)
    {
        $isLive = MollieSettings::isLive($formId);
        if($isLive) {
            return 'live';
        }
        return 'test';
    }

    public function handlePaid($submission, $vendorTransaction)
    {
        $this->setSubmissionId($submission->id);
        $transaction = $this->getLastTransaction($submission->id);

        if (!$transaction || $transaction->payment_method != $this->method) {
            return;
        }

        // Check if actions are fired
        if ($this->getMetaData('is_form_action_fired') == 'yes') {
            return;
        }

        $status = 'paid';

        // Let's make the payment as paid
        $updateData = [
            'payment_note'     => maybe_serialize($vendorTransaction),
            'charge_id'        => sanitize_text_field($vendorTransaction['id']),
        ];

        $this->updateTransaction($transaction->id, $updateData);
        $this->changeSubmissionPaymentStatus($status);
        $this->changeTransactionStatus($transaction->id, $status);
        $this->recalculatePaidTotal();
        $this->completePaymentSubmission(false);
        $this->setMetaData('is_form_action_fired', 'yes');
    }

    public function handleRefund($refundAmount, $submission, $vendorTransaction)
    {
        $this->setSubmissionId($submission->id);
        $transaction = $this->getLastTransaction($submission->id);
        $this->updateRefund($refundAmount, $transaction, $submission, $this->method);
    }

	public function validateSubmittedItems($paymentItems, $form, $formData, $subscriptionItems)
	{
		if (count($subscriptionItems)) {
			wp_send_json([
				'errors' => __('Mollie Error: Mollie does not support subscriptions right now!', 'fluentformpro')
			], 423);
		}
    }
}
