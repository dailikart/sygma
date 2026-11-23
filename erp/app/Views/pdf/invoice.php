<?= view('pdf/_style', ['docTitle' => 'TAX INVOICE']) ?>
<table><tr>
    <td style="width:55%" class="box"><span class="muted">Bill To</span><br><strong><?= esc($customer['name']) ?></strong><br>
        <?= nl2br(esc((string) $customer['address'])) ?><br><?= esc(trim(($customer['city'] ?? '') . ' ' . ($customer['state'] ?? '') . ' ' . ($customer['pincode'] ?? ''))) ?>
        <?= $customer['phone'] ? '<br>Ph: ' . esc($customer['phone']) : '' ?><?= $customer['gstin'] ? '<br>GSTIN: ' . esc($customer['gstin']) : '' ?></td>
    <td style="width:45%" class="box">Invoice No: <strong><?= esc($inv['invoice_no']) ?></strong><br>Invoice Date: <?= fdate($inv['invoice_date']) ?><br>
        Due Date: <?= fdate($inv['due_date']) ?><br>System capacity: <?= qty($inv['capacity_kw']) ?> kW<br>
        <?= $inv['subsidy_type'] === 'subsidy' ? 'Scheme: PM Surya Ghar (subsidy)<br>' : '' ?><?= ($inv['finance_mode'] ?? '') === 'loan' ? 'Payment: Bank loan – ' . esc((string) $inv['loan_bank']) : (($inv['finance_mode'] ?? '') === 'cash' ? 'Payment: Cash / self-funded' : '') ?><?= $customer['eb_service_no'] ? '<br>EB Service No: ' . esc($customer['eb_service_no']) : '' ?></td>
</tr></table>
<br>
<table class="items">
    <thead><tr><th>#</th><th>Item</th><th>HSN</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">GST %</th><th class="r">Amount</th></tr></thead>
    <tbody>
    <?php foreach (\App\Libraries\InvoiceFormat::lines($inv, $lines, $type ?? '') as $n => $l): ?>
        <tr><td><?= $n + 1 ?></td><td><?= esc($l['description']) ?></td><td><?= esc($l['hsn'] ?? '') ?></td><td class="r"><?= qty($l['qty']) ?></td><td class="r"><?= money($l['rate'], false) ?></td><td class="r"><?= esc(str_replace('GST ', '', $l['tax_label'])) ?></td><td class="r"><?= money($l['amount'], false) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php $gs = \App\Libraries\InvoiceFormat::gstSummary($lines, $inv['gst_type']); ?>
<table class="items" style="width:70%;margin-top:6px"><thead><tr><th>GST rate</th><th class="r">Taxable</th><?= $inv['gst_type'] === 'inter' ? '<th class="r">IGST</th>' : '<th class="r">CGST</th><th class="r">SGST</th>' ?></tr></thead><tbody>
<?php foreach ($gs as $rate => $s): ?><tr><td><?= esc($rate) ?>%</td><td class="r"><?= money($s['taxable'], false) ?></td><?= $inv['gst_type'] === 'inter' ? '<td class="r">' . money($s['igst'], false) . '</td>' : '<td class="r">' . money($s['cgst'], false) . '</td><td class="r">' . money($s['sgst'], false) . '</td>' ?></tr><?php endforeach ?>
</tbody></table>
<table class="tot" style="margin-top:8px"><tr><td style="width:55%" class="muted"><?= amount_in_words((float) $inv['total']) ?></td><td>
    <table>
        <tr><td class="r">Sub Total</td><td class="r"><?= money($inv['subtotal']) ?></td></tr>
        <?php if ($inv['gst_type'] === 'inter'): ?><tr><td class="r">IGST</td><td class="r"><?= money($inv['tax_total']) ?></td></tr>
        <?php else: ?><tr><td class="r">CGST</td><td class="r"><?= money(round($inv['tax_total'] / 2, 2)) ?></td></tr><tr><td class="r">SGST</td><td class="r"><?= money($inv['tax_total'] - round($inv['tax_total'] / 2, 2)) ?></td></tr><?php endif ?>
        <tr><td class="r"><strong>Total</strong></td><td class="r"><strong><?= money($inv['total']) ?></strong></td></tr>
        <tr><td class="r">Paid</td><td class="r"><?= money($inv['amount_paid']) ?></td></tr>
        <tr><td class="r"><strong>Amount Due</strong></td><td class="r"><strong><?= money($inv['total'] - $inv['amount_paid']) ?></strong></td></tr>
    </table></td></tr></table>
<?php if ($inv['notes']): ?><p class="muted"><?= nl2br(esc($inv['notes'])) ?></p><?php endif ?>
<br><br><table><tr><td class="muted">This is a computer generated invoice.</td><td class="r">For <?= esc(setting('Solar.companyName')) ?><br><?= brand_img('signature', 'max-height:40px;max-width:150px') ?: '<br><br>' ?><br>Authorised Signatory</td></tr></table>
