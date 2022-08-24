<div class="ffp_payment_info">
    <div class="ffp_payment_info_item ffp_payment_info_item_order_id">
        <div class="ffp_item_heading"><?php _e('Transaction #', 'fluentformpro');?></div>
        <div class="ffp_item_value">#<?php echo $transaction->id; ?></div>
    </div>
    <div class="ffp_payment_info_item ffp_payment_info_item_date">
        <div class="ffp_item_heading"><?php _e('Date:' ,'fluentformpro');?></div>
        <div class="ffp_item_value"><?php echo date(get_option( 'date_format' ), strtotime($transaction->created_at)); ?></div>
    </div>
    <?php if($transaction->payment_method): ?>
        <div class="ffp_payment_info_item ffp_payment_info_item_payment_method">
            <div class="ffp_item_heading"><?php _e('Payment Method:','fluentformpro');?></div>
            <div class="ffp_item_value"><?php
                echo ucfirst(
                    apply_filters(
                        'fluentform_payment_method_public_name_'.$transaction->payment_method,
                        $transaction->payment_method
                    )
                ); ?></div>
        </div>
    <?php endif; ?>
    <?php if($transaction->status): ?>
        <div class="ffp_payment_info_item ffp_payment_info_item_payment_status">
            <div class="ffp_item_heading"><?php _e('Payment Status:','fluentformpro');?></div>
            <div class="ffp_item_value"><?php echo ucfirst($transaction->status); ?></div>
        </div>
    <?php endif; ?>
</div>
