<ul class="ffp_payment_info_table">
    <li>
        <b><?php _e('Amount:', 'fluentformpro');?></b> <?php echo $orderTotal; ?></b>
    </li>
    <?php if($submission->payment_method): ?>
        <li>
            <b><?php _e('Payment Method:', 'fluentformpro');?></b> <?php echo ucfirst(
                apply_filters(
                    'fluentform_payment_method_public_name_'.$submission->payment_method,
                    $submission->payment_method
                )
            ); ?></b>
        </li>
    <?php endif; ?>
    <?php if($submission->payment_status): ?>
        <li>
            <b><?php _e('Payment Status:', 'fluentformpro');?></b> <?php echo ucfirst($submission->payment_status); ?></b>
        </li>
    <?php endif; ?>
</ul>
