<?php
$company = (string) setting('Solar.companyName');
$modeTxt = ['bank' => 'Bank transfer', 'upi' => 'UPI', 'cheque' => 'Cheque', 'cash' => 'Cash', 'loan' => 'Loan disbursement'][$pay['mode']] ?? ucfirst((string) $pay['mode']);
if ($pay['mode'] === 'loan' && ! empty($invoice['loan_bank'])) { $modeTxt .= ' from ' . $invoice['loan_bank']; }
if ($pay['mode'] !== 'cash' && ! empty($account['name'])) { $modeTxt .= ' – ' . $account['name']; }
if (! empty($pay['reference'])) { $modeTxt .= ' (Ref: ' . $pay['reference'] . ')'; }
$amount  = (float) $pay['amount'];
$rupees  = (fmod($amount, 1.0) == 0.0 ? money($amount, false) : money($amount, false));
$rupees  = preg_replace('/\.00$/', '', $rupees);
$words   = preg_replace(['/^Rupees /', '/ and (.+) Paise Only$/', '/ Only$/'], ['', ' Rupees and $1 Paise Only', ' Rupees Only'], amount_in_words($amount));
if (str_contains($words, 'Paise Only') && str_contains($words, 'Rupees Rupees')) { $words = str_replace('Rupees Rupees', 'Rupees', $words); }
$purpose = 'For Installation of Solar ' . qty($invoice['capacity_kw'] ?? 0) . ' kW' . (! empty($invoice['invoice_no']) ? ' (Invoice ' . $invoice['invoice_no'] . ')' : '');
$d       = date('d / m / Y', strtotime($pay['payment_date']));
$contact = trim((string) setting('Solar.companyPhone'));
?>
<style>
body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111; }
table { border-collapse: collapse; width: 100%; }
.c { text-align: center; } .lbl { width: 38%; font-weight: bold; padding: 9px 6px 9px 18px; vertical-align: top; } .val { padding: 9px 6px; vertical-align: top; }
.muted { color: #444; font-size: 8pt; }
</style>
<div class="c">
    <?= brand_img('logo', 'max-height:70px;max-width:160px') ?>
    <div style="font-size:14pt;font-weight:bold;margin-top:6px"><?= esc(strtoupper($company)) ?></div>
    <div class="muted"><?= esc(str_replace("\n", ', ', (string) setting('Solar.companyAddress'))) ?></div>
    <div class="muted"><?= setting('Solar.companyGstin') ? 'GSTIN NO – ' . esc(setting('Solar.companyGstin')) : '' ?><?= $contact ? ' &nbsp;&nbsp; Contact No - ' . esc($contact) : '' ?><?= setting('Solar.companyEmail') ? ' &nbsp;&nbsp; ' . esc(setting('Solar.companyEmail')) : '' ?></div>
</div>
<hr style="margin:14px 50px;color:#333">
<div class="c" style="font-size:11pt;font-weight:bold;margin:8px 0 22px">PAYMENT RECEIPT</div>
<table style="width:82%;margin:0 auto"><tr><td class="lbl" style="padding-bottom:4px">Receipt No:</td><td class="val" style="padding-bottom:4px"><?= esc($pay['receipt_no']) ?></td></tr></table>
<table style="width:82%;margin:0 auto;border:0.8px solid #333">
    <tr><td class="lbl">Date:</td><td class="val"><?= $d ?></td></tr>
    <tr><td class="lbl">Received with thanks from:</td><td class="val"><?= esc((string) ($party['name'] ?? '')) ?><?= ! empty($party['address']) ? '<br><span class="muted">' . nl2br(esc($party['address'])) . '</span>' : '' ?></td></tr>
    <tr><td class="lbl">Amount Received:</td><td class="val">Rs. <?= $rupees ?>/-</td></tr>
    <tr><td class="lbl">Amount in Words:</td><td class="val"><?= esc($words) ?></td></tr>
    <tr><td class="lbl">Payment Mode:</td><td class="val"><?= esc($modeTxt) ?><?= $pay['mode'] === 'cheque' ? '<br><span class="muted"><i>Subject to realisation</i></span>' : '' ?></td></tr>
</table>
<table style="width:82%;margin:0 auto">
    <tr><td class="lbl">Purpose:</td><td class="val"><?= esc($purpose) ?></td></tr>
    <?php if (! empty($invoice)): $paidTill = (float) db_connect()->table('payments')->selectSum('amount')->where('invoice_id', $invoice['id'])->where('id <=', $pay['id'])->get()->getRow()->amount; ?>
    <tr><td class="lbl">Balance due:</td><td class="val">Rs. <?= money(max(0, (float) $invoice['total'] - $paidTill), false) ?>/- <span class="muted">(invoice total Rs. <?= money($invoice['total'], false) ?>/-, paid till this receipt Rs. <?= money($paidTill, false) ?>/-)</span></td></tr><?php endif ?>
    <tr><td class="lbl">Received By:</td><td class="val"><?= esc($company) ?></td></tr>
</table>
<br><br>
<table style="width:82%;margin:0 auto"><tr>
    <td style="vertical-align:bottom;padding-left:18px">Date: <?= $d ?></td>
    <td style="text-align:right;vertical-align:bottom">
        <table style="width:auto;margin-left:auto"><tr>
            <td style="vertical-align:middle;padding-right:10px"><?= brand_img('seal', 'max-height:70px;max-width:80px') ?></td>
            <td style="text-align:center;vertical-align:bottom">For <?= esc(strtoupper($company)) ?><br><?= brand_img('signature', 'max-height:45px;max-width:150px') ?: '<br><br><br>' ?><br><span class="muted">Authorised Signatory</span></td>
        </tr></table>
    </td>
</tr></table>
<div class="c muted" style="margin-top:30px">This is a computer generated receipt.</div>
