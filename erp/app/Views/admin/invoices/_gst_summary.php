<?php $sum = \App\Libraries\InvoiceFormat::gstSummary($lines, $inv['gst_type']); ?>
<div class="table-responsive"><table class="table table-sm table-bordered small mb-3" style="max-width:640px">
    <thead><tr><th>GST rate</th><th class="text-end">Taxable value</th><?php if ($inv['gst_type'] === 'inter'): ?><th class="text-end">IGST</th><?php else: ?><th class="text-end">CGST</th><th class="text-end">SGST</th><?php endif ?><th class="text-end">Total tax</th></tr></thead>
    <tbody><?php foreach ($sum as $rate => $s): ?>
        <tr><td><?= esc($rate) ?>%</td><td class="text-end"><?= money($s['taxable'], false) ?></td>
            <?php if ($inv['gst_type'] === 'inter'): ?><td class="text-end"><?= money($s['igst'], false) ?></td><?php else: ?><td class="text-end"><?= money($s['cgst'], false) ?></td><td class="text-end"><?= money($s['sgst'], false) ?></td><?php endif ?>
            <td class="text-end"><?= money($s['cgst'] + $s['sgst'] + $s['igst'], false) ?></td></tr>
    <?php endforeach ?></tbody>
</table></div>
