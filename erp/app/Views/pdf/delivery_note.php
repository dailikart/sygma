<?php
$c   = $customer;
$cmp = [
    'name' => (string) setting('Solar.companyName'), 'address' => (string) setting('Solar.companyAddress'),
    'gstin' => (string) setting('Solar.companyGstin'), 'state' => (string) setting('Solar.companyState'),
];
$cmpCode = gst_state_code($cmp['state'], $cmp['gstin']);
$cCode   = gst_state_code($c['state'] ?? '', $c['gstin'] ?? '');
$taxable = 0; $taxTot = 0; $qtyTot = 0; $byHsn = [];
foreach ($dlines as $l) {
    $taxable += $l['amount']; $taxTot += $l['tax_amount']; $qtyTot += (float) $l['qty'];
    $k = ($l['hsn'] ?: '-') . '|' . qty($l['tax_percent']);
    $byHsn[$k] ??= ['hsn' => $l['hsn'] ?: '-', 'rate' => (float) $l['tax_percent'], 'taxable' => 0, 'tax' => 0];
    $byHsn[$k]['taxable'] += $l['amount']; $byHsn[$k]['tax'] += $l['tax_amount'];
}
$inter = $gstType === 'inter';
$half  = round($taxTot / 2, 2);
$gross = round($taxable + $taxTot, 2);
$total = round($gross);
$round = round($total - $gross, 2);
$fmt   = static fn ($n) => money($n, false);
$mode  = ['cash' => 'Cash', 'loan' => 'Bank loan' . (! empty($inv['loan_bank']) ? ' – ' . $inv['loan_bank'] : '')][$inv['finance_mode'] ?? ''] ?? '';
$addr  = static fn ($x) => nl2br(esc(trim(($x['address'] ?? '') . "\n" . trim(($x['city'] ?? '') . (! empty($x['pincode']) ? ' - ' . $x['pincode'] : '')))));
?>
<style>
body { font-family: dejavusans, sans-serif; font-size: 7.5pt; color: #000; }
table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: top; }
.b { border-top: 0.6px solid #000; } .bl { border-left: 0.6px solid #000; } .bt { border-top: 0.6px solid #000; } .bb { border-bottom: 0.6px solid #000; } .br { border-right: 0.6px solid #000; }
.r { text-align: right; } .c { text-align: center; } .s { font-size: 6.5pt; } .bold { font-weight: bold; } .i { font-style: italic; }
.cell { padding: 2px 3px; }
</style>
<div class="c bold" style="font-size:10pt;margin-bottom:4px">DELIVERY NOTE</div>
<div style="border:0.8px solid #000">
<table>
<tr>
    <td style="width:50%;border-right:0.6px solid #000;padding:0">
        <table>
            <tr><td class="cell" style="border-bottom:0.6px solid #000"><?= brand_img('logo', 'max-height:28px;max-width:110px') ?><?= setting('Solar.logoPath') ? '<br>' : '' ?><span class="bold"><?= esc(strtoupper($cmp['name'])) ?></span><br><?= nl2br(esc($cmp['address'])) ?>
                <?= $cmp['gstin'] ? '<br>GSTIN/UIN: ' . esc($cmp['gstin']) : '' ?><br>State Name : <?= esc($cmp['state']) ?><?= $cmpCode ? ', Code : ' . $cmpCode : '' ?></td></tr>
            <tr><td class="cell" style="border-bottom:0.6px solid #000"><span class="s">Consignee (Ship to)</span><br><span class="bold"><?= esc(strtoupper($c['name'])) ?></span><br><?= $addr(['address' => $project['site_address'] ?: $c['address']] + $c) ?>
                <?= ! empty($c['gstin']) ? '<br>GSTIN/UIN: ' . esc($c['gstin']) : '' ?><br>State Name : <?= esc((string) $c['state']) ?><?= $cCode ? ', Code : ' . $cCode : '' ?></td></tr>
            <tr><td class="cell"><span class="s">Buyer (Bill to)</span><br><span class="bold"><?= esc(strtoupper($c['name'])) ?></span><?= ! empty($c['company']) ? '<br>' . esc($c['company']) : '' ?><br><?= $addr($c) ?>
                <?= ! empty($c['gstin']) ? '<br>GSTIN/UIN: ' . esc($c['gstin']) : '' ?><br>State Name : <?= esc((string) $c['state']) ?><?= $cCode ? ', Code : ' . $cCode : '' ?></td></tr>
        </table>
    </td>
    <td style="width:50%;padding:0">
        <table>
            <tr><td class="cell br bb" style="width:50%"><span class="s">Delivery Note No.</span><br><b><?= esc($d['dispatch_no']) ?></b></td><td class="cell bb"><span class="s">Dated</span><br><b><?= date('j-M-y', strtotime($d['dispatched_at'] ?? $d['created_at'])) ?></b></td></tr>
            <tr><td class="cell br bb"><span class="s">Reference No. &amp; Date.</span><br><?= esc($project['project_no']) ?></td><td class="cell bb"><span class="s">Mode/Terms of Payment</span><br><?= esc($mode) ?></td></tr>
            <tr><td class="cell br bb"><span class="s">Buyer's Order No.</span><br><b><?= esc($inv['invoice_no'] ?? '') ?></b></td><td class="cell bb"><span class="s">Dated</span><br><b><?= ! empty($inv['invoice_date']) ? date('j-M-y', strtotime($inv['invoice_date'])) : '' ?></b></td></tr>
            <tr><td class="cell br bb"><span class="s">Dispatch Doc No.</span><br><b><?= esc($d['dispatch_no']) ?></b></td><td class="cell bb"><span class="s">Other References</span><br><?= esc($location['name'] ?? '') ?></td></tr>
            <tr><td class="cell br bb"><span class="s">Dispatched through</span><br><b><?= esc(strtoupper((string) ($d['dispatched_through'] ?? ''))) ?></b><?= $d['driver_name'] ? '<br>' . esc($d['driver_name']) : '' ?></td><td class="cell bb"><span class="s">Destination</span><br><b><?= esc(strtoupper((string) ($d['destination'] ?: ($c['city'] ?? '')))) ?></b></td></tr>
            <tr><td class="cell br bb"><span class="s">Bill of Lading/LR-RR No.</span><br>&nbsp;</td><td class="cell bb"><span class="s">Motor Vehicle No.</span><br><b><?= esc(strtoupper((string) $d['vehicle_no'])) ?></b></td></tr>
            <tr><td class="cell" colspan="2" style="height:60px"><span class="s">Terms of Delivery</span><br><?= esc((string) ($d['terms_of_delivery'] ?? '')) ?></td></tr>
        </table>
    </td>
</tr>
</table>
<table class="b">
    <tr class="bb"><th class="cell br s" style="width:5%">Sl<br>No.</th><th class="cell br s c" style="width:40%">Description of Goods</th><th class="cell br s c" style="width:11%">HSN/SAC</th><th class="cell br s c" style="width:10%">Quantity</th><th class="cell br s c" style="width:11%">Rate</th><th class="cell br s c" style="width:6%">per</th><th class="cell s c">Amount</th></tr>
    <?php foreach ($dlines as $n => $l): ?>
    <tr><td class="cell br r"><?= $n + 1 ?></td>
        <td class="cell br"><b><?= esc(strtoupper($l['name'])) ?></b><?= $l['serial_nos'] ? '<br><span class="i s">S.No: ' . esc(str_replace("\n", ', ', $l['serial_nos'])) . '</span>' : '' ?></td>
        <td class="cell br"><?= esc((string) $l['hsn']) ?></td><td class="cell br r"><b><?= qty($l['qty']) ?> <?= esc($l['unit']) ?></b></td>
        <td class="cell br r"><?= $fmt($l['rate']) ?></td><td class="cell br"><?= esc($l['unit']) ?></td><td class="cell r"><b><?= $fmt($l['amount']) ?></b></td></tr>
    <?php endforeach ?>
    <tr><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="cell r bt"><?= $fmt($taxable) ?></td></tr>
    <?php if ($inter): ?>
        <tr><td class="br"></td><td class="cell br r i bold">IGST</td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="cell r"><?= $fmt($taxTot) ?></td></tr>
    <?php else: ?>
        <tr><td class="br"></td><td class="cell br r i bold">SGST</td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="cell r"><?= $fmt($taxTot - $half) ?></td></tr>
        <tr><td class="br"></td><td class="cell br r i bold">CGST</td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="cell r"><?= $fmt($half) ?></td></tr>
    <?php endif ?>
    <?php if ($round != 0.0): ?><tr><td class="br"></td><td class="cell br r i">Rounded Off</td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="cell r"><?= $round < 0 ? '(-)' . $fmt(-$round) : $fmt($round) ?></td></tr><?php endif ?>
    <tr><td class="br" style="height:40px"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td class="br"></td><td></td></tr>
    <tr class="bt"><td class="br"></td><td class="cell br r">Total</td><td class="br"></td><td class="cell br r bold"><?= qty($qtyTot) ?><?= count(array_unique(array_column($dlines, 'unit'))) === 1 ? ' ' . esc($dlines[0]['unit']) : '' ?></td><td class="br"></td><td class="br"></td><td class="cell r bold">₹ <?= $fmt($total) ?></td></tr>
</table>
<table class="b"><tr><td class="cell"><table><tr><td class="s">Amount Chargeable (in words)</td><td class="s i r">E. &amp; O.E</td></tr></table><b><?= esc(str_replace(['Rupees', ' Only'], ['INR', ' Only'], amount_in_words($total))) ?></b></td></tr></table>
<table class="b">
    <tr class="bb s"><th class="cell br c" rowspan="2">HSN/SAC</th><th class="cell br c" rowspan="2">Taxable<br>Value</th>
        <?php if ($inter): ?><th class="cell br c" colspan="2">IGST</th><?php else: ?><th class="cell br c" colspan="2">CGST</th><th class="cell br c" colspan="2">SGST/UTGST</th><?php endif ?><th class="cell c" rowspan="2">Total<br>Tax Amount</th></tr>
    <tr class="bb s"><?php for ($k = 0; $k < ($inter ? 1 : 2); $k++): ?><th class="cell br c">Rate</th><th class="cell br c">Amount</th><?php endfor ?></tr>
    <?php $tt = 0; $th = 0; foreach ($byHsn as $h): $hh = round($h['tax'] / 2, 2); $tt += $h['taxable']; $th += $h['tax']; ?>
    <tr class="s"><td class="cell br"><?= esc($h['hsn']) ?></td><td class="cell br r"><?= $fmt($h['taxable']) ?></td>
        <?php if ($inter): ?><td class="cell br r"><?= qty($h['rate']) ?>%</td><td class="cell br r"><?= $fmt($h['tax']) ?></td>
        <?php else: ?><td class="cell br r"><?= qty($h['rate'] / 2) ?>%</td><td class="cell br r"><?= $fmt($hh) ?></td><td class="cell br r"><?= qty($h['rate'] / 2) ?>%</td><td class="cell br r"><?= $fmt($h['tax'] - $hh) ?></td><?php endif ?>
        <td class="cell r"><?= $fmt($h['tax']) ?></td></tr>
    <?php endforeach ?>
    <tr class="s bt bold"><td class="cell br r">Total</td><td class="cell br r"><?= $fmt($tt) ?></td>
        <?php if ($inter): ?><td class="br"></td><td class="cell br r"><?= $fmt($th) ?></td><?php else: ?><td class="br"></td><td class="cell br r"><?= $fmt($half) ?></td><td class="br"></td><td class="cell br r"><?= $fmt($th - $half) ?></td><?php endif ?>
        <td class="cell r"><?= $fmt($th) ?></td></tr>
</table>
<table class="b"><tr><td class="cell" style="height:50px">Tax Amount (in words) : &nbsp; <b><?= esc(str_replace(['Rupees', ' Only'], ['INR', ' Only'], amount_in_words($taxTot))) ?></b></td></tr></table>
<table class="b"><tr>
    <td class="cell br" style="width:50%;height:55px">Recd. in Good Condition<br><br><br><span class="s">Receiver's signature</span></td>
    <td class="cell r"><b>for <?= esc(strtoupper($cmp['name'])) ?></b><br><?= brand_img('signature', 'max-height:32px;max-width:130px') ?: '<br><br>' ?><br>Authorised Signatory</td>
</tr></table>
</div>
<div class="c s" style="margin-top:4px">This is a Computer Generated Document</div>
