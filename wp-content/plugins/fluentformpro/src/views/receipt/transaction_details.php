<?php
/** @var object $transaction */
/** @var string $transactionTotal */
/** @var array $items */
/** @var array $discountItems */
/** @var string $subTotal */
/** @var string $orderTotal */
?>
<div class="ff_payment_transaction">
    <?php
    echo \FluentFormPro\Payments\PaymentHelper::loadView('transaction_info', [
        'transaction' => $transaction,
        'transactionTotal' => $transactionTotal
    ]);

    echo \FluentFormPro\Payments\PaymentHelper::loadView('order_items_table', [
        'items' => $items,
        'discount_items' => $discountItems,
        'subTotal' => $subTotal,
        'orderTotal' => $orderTotal
    ]);

    echo \FluentFormPro\Payments\PaymentHelper::loadView('customer_details', [
        'transaction' => $transaction
    ]);

    echo \FluentFormPro\Payments\PaymentHelper::loadView('custom_css', []);

    ?>
</div>