<div class="ff_transactions">
    <?php do_action('fluentform_transactions_before_table', $transactions); ?>
    <table style="width: 100%;border: 1px solid #cbcbcb;margin-top: 0;" class="table ffp_order_items_table ffp_table table_bordered">
        <thead>
            <tr>
                <th class="ff_th_id"><?php _e('ID', 'fluentformpro'); ?></th>
                <th class="ff_th_amount"><?php _e('Amount', 'fluentformpro'); ?></th>
                <th class="ff_th_status"><?php _e('Status', 'fluentformpro'); ?></th>
                <th class="ff_th_payment_method"><?php _e('Payment Method', 'fluentformpro'); ?></th>
                <th class="ff_th_date"><?php _e('Date', 'fluentformpro'); ?></th>
                <th class="ff_th_action"><?php _e('Action', 'fluentformpro'); ?></th>
                <?php do_action('fluentform_transaction_table_thead_row', $transactions); ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $transaction): ?>
            <tr class="ff_row_status_<?php echo $transaction->status; ?>">
                <td class="ff_td_id">#<?php echo $transaction->id;?></td>
                <td class="ff_td_amount"><?php echo $transaction->formatted_amount; ?></td>
                <td class="ff_td_status"><span class="ff_pay_status ff_pay_status_<?php echo $transaction->status; ?>"><?php echo ucfirst($transaction->status); ?></span></td>
                <td class="ff_td_payment_method"><span class="ff_pay_method ff_pay_method_<?php echo $transaction->payment_method; ?>"><?php echo ucfirst($transaction->payment_method); ?></span></td>
                <td class="ff_td_date"><?php echo $transaction->formatted_date; ?></td>
                <td class="ff_td_action">
                    <a class="ff_pat_action_view" href="<?php echo $transaction->view_url ?>"><?php echo $config['view_text']; ?></a>
                    <?php do_action('fluentform_transactions_actions', $transaction); ?>
                </td>
                <?php do_action('fluentform_transaction_table_tbody_row', $transaction, $transactions); ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php do_action('fluentform_transactions_before_table_close', $transactions); ?>
    </table>
    <?php do_action('fluentform_transactions_after_table', $transactions); ?>
</div>