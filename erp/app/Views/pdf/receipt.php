<?= view('pdf/_style', ['docTitle' => 'PAYMENT RECEIPT']) ?>
<table><tr><td>Receipt No: <strong><?= esc($pay['receipt_no']) ?></strong></td><td class="r">Date: <strong><?= fdate($pay['payment_date']) ?></strong></td></tr></table>
<br>
<div class="box">
    Received with thanks from <strong><?= esc($customer['name']) ?></strong> the sum of <strong><?= money($pay['amount']) ?></strong><br>
    (<?= amount_in_words((float) $pay['amount']) ?>)<br><br>
    By <?= esc(strtoupper($pay['mode'])) ?><?= $pay['reference'] ? ' – Ref: ' . esc($pay['reference']) : '' ?><?= $pay['account'] ? ' – ' . esc($pay['account']) : '' ?><br>
    Against invoice <strong><?= esc($inv['invoice_no']) ?></strong> dated <?= fdate($inv['invoice_date']) ?> (Total <?= money($inv['total']) ?>)
</div>
<br>
<table class="tot"><tr><td class="r">Invoice total</td><td class="r" style="width:30%"><?= money($inv['total']) ?></td></tr>
<tr><td class="r">Total paid so far</td><td class="r"><?= money($inv['amount_paid']) ?></td></tr>
<tr><td class="r"><strong>Balance due</strong></td><td class="r"><strong><?= money($inv['total'] - $inv['amount_paid']) ?></strong></td></tr></table>
<?php if ($pay['notes']): ?><p class="muted">Note: <?= esc($pay['notes']) ?></p><?php endif ?>
<br><br><table><tr><td class="muted">This is a computer generated receipt.</td><td class="r">For <?= esc(setting('Solar.companyName')) ?><br><?= brand_img('signature', 'max-height:40px;max-width:150px') ?: '<br><br>' ?><br>Authorised Signatory</td></tr></table>
