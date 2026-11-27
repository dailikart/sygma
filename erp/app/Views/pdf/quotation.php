<?= view('pdf/_style', ['docTitle' => 'QUOTATION']) ?>
<table><tr>
    <td style="width:55%" class="box"><span class="muted">To</span><br><strong><?= esc($q['party_name']) ?></strong><br>
        <?= nl2br(esc((string) $q['party_address'])) ?><?= $q['party_address'] ? '<br>' : '' ?><?= esc(trim(($q['party_city'] ?? '') . ' ' . ($q['party_state'] ?? ''))) ?>
        <?= $q['party_phone'] ? '<br>Ph: ' . esc($q['party_phone']) : '' ?><?= $q['party_email'] ? '<br>' . esc($q['party_email']) : '' ?></td>
    <td style="width:45%" class="box">Quotation No: <strong><?= esc($q['quote_no']) ?></strong><br>Date: <?= fdate($q['quote_date']) ?><br>
        <?= $q['valid_until'] ? 'Valid until: ' . fdate($q['valid_until']) . '<br>' : '' ?>System: <strong><?= qty($q['capacity_kw']) ?> kW <?= esc($type) ?></strong><br>
        <?= $q['subsidy_type'] === 'subsidy' ? 'Scheme: PM Surya Ghar (subsidy)' : 'Non-subsidy' ?></td>
</tr></table>
<br>
<table class="items">
    <thead><tr><th>#</th><th>Description</th><th>HSN</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">GST</th><th class="r">Amount</th></tr></thead>
    <tbody>
    <?php foreach (\App\Libraries\InvoiceFormat::lines($q, $lines, $type) as $n => $l): ?>
        <tr><td><?= $n + 1 ?></td><td><?= esc($l['description']) ?></td><td><?= esc((string) ($l['hsn'] ?? '')) ?></td><td class="r"><?= qty($l['qty']) ?></td><td class="r"><?= money($l['rate'], false) ?></td><td class="r"><?= esc($l['tax_label']) ?></td><td class="r"><?= money($l['amount'], false) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<table class="tot" style="margin-top:8px"><tr>
    <td style="width:52%;vertical-align:top">
        <table class="items" style="width:100%">
            <thead><tr><th colspan="2">Your savings</th></tr></thead>
            <tr><td>Generation per year</td><td class="r"><?= number_format($roi['units_year']) ?> units</td></tr>
            <tr><td>Savings per year</td><td class="r"><?= money($roi['savings_year']) ?></td></tr>
            <tr><td><strong>Payback</strong></td><td class="r"><strong><?= $roi['payback_years'] !== null ? $roi['payback_years'] . ' years' : '—' ?></strong></td></tr>
            <tr><td><strong>Savings in 25 years</strong></td><td class="r"><strong><?= money($roi['savings_25']) ?></strong></td></tr>
        </table>
        <div class="muted" style="margin-top:3px">Approximate, based on <?= qty($q['units_per_kw_day']) ?> units per kW per day at ₹<?= qty($q['tariff']) ?> per unit.</div>
    </td>
    <td style="width:48%;vertical-align:top"><table>
        <tr><td class="r">Sub Total</td><td class="r"><?= money($q['subtotal']) ?></td></tr>
        <?php if ($q['gst_type'] === 'inter'): ?><tr><td class="r">IGST</td><td class="r"><?= money($q['tax_total']) ?></td></tr>
        <?php else: ?><tr><td class="r">CGST</td><td class="r"><?= money(round($q['tax_total'] / 2, 2)) ?></td></tr><tr><td class="r">SGST</td><td class="r"><?= money($q['tax_total'] - round($q['tax_total'] / 2, 2)) ?></td></tr><?php endif ?>
        <tr><td class="r"><strong>Total</strong></td><td class="r"><strong><?= money($q['total']) ?></strong></td></tr>
        <?php if ($roi['extra'] > 0): ?><tr><td class="r">EB / meter charges (paid separately)</td><td class="r"><?= money($roi['extra']) ?></td></tr><?php endif ?>
        <?php if ((float) $q['subsidy_amount'] > 0): ?><tr><td class="r">Less: expected subsidy</td><td class="r">− <?= money($q['subsidy_amount']) ?></td></tr><?php endif ?>
        <tr><td class="r"><strong>Net cost to you</strong></td><td class="r"><strong><?= money($roi['net_cost']) ?></strong></td></tr>
    </table></td>
</tr></table>
<p class="muted"><?= amount_in_words((float) $q['total']) ?></p>
<?php if ($q['design_path'] && preg_match('/\.(jpe?g|png)$/i', $q['design_path']) && is_file(upload_path() . $q['design_path'])): ?>
    <p><strong>Proposed layout</strong><br><img src="<?= esc(upload_path() . $q['design_path']) ?>" style="max-width:100%;max-height:260px"></p>
<?php endif ?>
<?php if ($q['notes']): ?><p><strong>Terms</strong><br><span class="muted"><?= nl2br(esc($q['notes'])) ?></span></p><?php endif ?>
<br><table><tr><td class="muted">We look forward to powering your home with the sun.</td><td class="r">For <?= esc(setting('Solar.companyName')) ?><br><?= brand_img('signature', 'max-height:40px;max-width:150px') ?: '<br><br>' ?><br>Authorised Signatory</td></tr></table>
