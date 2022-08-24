<?php

namespace FluentFormPro\Payments\Classes;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Modules\Acl\Acl;
use FluentFormPro\Payments\PaymentHelper;
use FluentForm\Framework\Helpers\ArrayHelper;

class PaymentEntries
{

    public function init()
    {
        add_action('flunetform_render_payment_entries', array($this, 'loadApp'));
        
        add_action('wp_ajax_fluentform_get_payments', array($this, 'getPayments'));
        add_action('wp_ajax_fluentform-do_entry_bulk_actions_payment', array($this, 'handleBulkAction'));
        add_action('wp_ajax_fluentform_get_all_payments_entries_filters', array($this, 'getFilters'));

    }

    public function loadApp()
    {
        wp_enqueue_style('ff-payment-entries', FLUENTFORMPRO_DIR_URL.'public/css/payment_entries.css', [], FLUENTFORMPRO_VERSION);
        wp_enqueue_script('ff-payment-entries', FLUENTFORMPRO_DIR_URL . 'public/js/payment-entries.js', ['jquery'], FLUENTFORMPRO_VERSION, true);
        $settingsUrl = admin_url('admin.php?page=fluent_forms_settings&component=payment_settings');
        do_action('fluentform_global_menu');
        echo '<div id="ff_payment_entries"><ff-payment-entries settings_url="'.$settingsUrl.'"></ff-payment-entries></div>';
    }

    public function getPayments()
    {
        Acl::verify('fluentform_view_payments');
        $perPage = intval($_REQUEST['per_page']);
        if(!$perPage) {
            $perPage = 10;
        }
        $page = intval($_REQUEST['current_page']);
        if(!$page) {
            $page = 1;
        }

        $offset = ($page - 1) * $perPage;
        $paymentsQuery = wpFluent()->table('fluentform_transactions')
            ->select([
                'fluentform_transactions.id',
                'fluentform_transactions.form_id',
                'fluentform_transactions.submission_id',
                'fluentform_transactions.transaction_type',
                'fluentform_transactions.payment_method',
                'fluentform_transactions.payment_mode',
                'fluentform_transactions.charge_id',
                'fluentform_transactions.card_brand',
                'fluentform_transactions.payment_total',
                'fluentform_transactions.created_at',
                'fluentform_transactions.payer_name',
                'fluentform_transactions.status',
                'fluentform_transactions.currency',
                'fluentform_forms.title'
            ])
            ->join('fluentform_forms', 'fluentform_forms.id', '=', 'fluentform_transactions.form_id')
            ->limit($perPage)
            ->offset($offset)
            ->orderBy('fluentform_transactions.id', 'DESC');

        if ($selectedFormId = ArrayHelper::get($_REQUEST, 'form_id')) {
            $paymentsQuery = $paymentsQuery->where('fluentform_transactions.form_id', intval($selectedFormId));
        }
        if ($paymentStatus = ArrayHelper::get($_REQUEST, 'payment_statuses')) {
            $paymentsQuery = $paymentsQuery->where('fluentform_transactions.status', sanitize_text_field($paymentStatus));
        }
        if ($paymentMethods = ArrayHelper::get($_REQUEST, 'payment_methods')) {
            $paymentsQuery = $paymentsQuery->where('fluentform_transactions.payment_method', sanitize_text_field($paymentMethods));
        }

        $total = $paymentsQuery->count();

        $payments = $paymentsQuery->get();

        foreach ($payments as $payment) {
            $payment->formatted_payment_total = PaymentHelper::formatMoney($payment->payment_total, $payment->currency);
            $payment->entry_url = admin_url('admin.php?page=fluent_forms&route=entries&form_id='.$payment->form_id.'#/entries/'.$payment->submission_id);
            if($payment->payment_method == 'test') {
                $payment->payment_method = 'offline';
            }
            if(apply_filters('ff_payment_entries_human_date', true)){
                $payment->created_at = human_time_diff(strtotime($payment->created_at), strtotime(current_time('mysql')));

            }
        }

        wp_send_json_success([
            'payments' => $payments,
            'total' => $total,
            'last_page' => ceil($total/$perPage)
        ]);

    }
    
    public function handleBulkAction()
    {
        Acl::verify('fluentform_forms_manager');
        
        $entries    = wp_unslash($_REQUEST['entries']);
        $actionType = sanitize_text_field($_REQUEST['action_type']);
        if (!$actionType || !count($entries)) {
            wp_send_json_error([
                'message' => __('Please select entries & action first', 'fluentformpro')
            ], 400);
        }
        
        $message = "Invalid action";
        $statusCode = 400;
        // permanently delete payment entries from transactions
        if ($actionType == 'delete_items') {
    
            
            // get submission ids to delete order items
            $transactionData =  wpFluent()->table('fluentform_transactions')
                                          ->select(['form_id','submission_id'])
                                          ->whereIn ('fluentform_transactions.id',$entries)
                                          ->get();

            $submission_ids = [];

            foreach ($transactionData as $transactionDatum) {
                $submission_ids[] = $transactionDatum->submission_id;
            }

            try {
                if( !$submission_ids || !$transactionData ){
                    throw new \Exception('Invalid transaction id');
                }
                do_action('fluentform_before_entry_payment_deleted', $entries, $transactionData);
    
                //delete data from transaction table
                wpFluent()->table('fluentform_transactions')
                          ->whereIn('id', $entries)->delete();
                
                //delete data from order table
                wpFluent()->table('fluentform_order_items')
                          ->whereIn('submission_id', $submission_ids)->delete();

                // delete data from subscriptions table
	            wpFluent()->table('fluentform_subscriptions')
		            ->whereIn('submission_id', $submission_ids)->delete();
                
                //add log in each form that payment record has been deleted
                foreach ($transactionData as $data){
                    do_action('ff_log_data', [
                        'parent_source_id' => $data->form_id,
                        'source_type'      => 'submission_item',
                        'source_id'        => $data->submission_id,
                        'component'        => 'payment',
                        'status'           => 'info',
                        'title'            => 'Payment data successfully deleted',
                        'description'      => 'Payment record cleared from transaction history and order items'
                    ]);
                }
                do_action('fluentform_after_entry_payment_deleted', $entries, $transactionData);
                $message = __('Selected entries successfully deleted', 'fluentformpro');
                $statusCode = 200;
        
            } catch (\Exception $exception) {
                $message = $exception->getMessage();
                $statusCode = 400;
            }
        }
        
        wp_send_json_success([
            'message' => $message
        ], $statusCode);
    }

    public function getFilters()
    {
        $statuses = wpFluent()->table('fluentform_transactions')
            ->select('status')
            ->groupBy('status')
            ->get();

        $formattedStatuses = [];
        foreach ($statuses as $status) {
            $formattedStatuses[] = $status->status;
        }
        $forms = wpFluent()->table('fluentform_transactions')
            ->select('fluentform_transactions.form_id', 'fluentform_forms.title')
            ->groupBy('fluentform_transactions.form_id')
            ->orderBy('fluentform_transactions.form_id', 'DESC')
            ->join('fluentform_forms', 'fluentform_forms.id', '=', 'fluentform_transactions.form_id')
            ->get();

        $formattedForms = [];
        foreach ($forms as $form) {
            $formattedForms[] = [
                'form_id' => $form->form_id,
                'title'   => $form->title
            ];
        }

        $paymentMethods = wpFluent()->table('fluentform_transactions')
            ->select('payment_method')
            ->groupBy('payment_method')
            ->get();

        $formattedMethods = [];
        foreach ($paymentMethods as $method) {
            $formattedMethods[] = $method->payment_method;
        }

        wp_send_json_success([
            'available_statuses'   => $formattedStatuses,
            'available_forms'      => $formattedForms,
            'available_methods'    => $formattedMethods,
        ]);

    }
}
