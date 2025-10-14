<div class="payment-form-block">
    <form action='https://auth.robokassa.ru/Merchant/Index.aspx' method=POST>
        <input type=hidden name=MrchLogin value=<?php echo $options['login'] ?>>
        <input type=hidden name=OutSum value=<?php echo $orderSumm; ?>>
        <input type=hidden name=InvId value=<?php echo $fakeUniqOrderId; ?>>
        <input type=hidden name=<?php echo $orderIdKey;?> value=<?php echo $orderId; ?>>
	<input type=hidden name='Shp_partner' value='all2crm'>
        <input type=hidden name=Desc value='Оплата заказа <?php echo $order['number']; ?>'>
        <input type=hidden name=Receipt value="<?php echo $receipt; ?>" />
        <input type=hidden name=SignatureValue value=<?php echo $sign; ?>>
        <input type=hidden name=IncCurrLabel value="">
        <input type=hidden name=Culture value="ru">
        <input type=hidden name=Email value=<?php echo $order['user_email']; ?>>
        <?php if ($testMode) : ?>
            <input type="hidden" name="IsTest" value="1">
        <?php endif; ?>
        <input id="robokassa" class="button" style="margin: 0 auto" type=submit value='<?php echo lang('paymentPay'); ?>'>
    </form>
</div>

<script>
window.onload = function() {
  setTimeout(function() {
    document.getElementById('robokassa').click();
  }, 1000);
};
</script>