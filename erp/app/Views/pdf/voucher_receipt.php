<div class="frame">
<?= view('pdf/_voucher_head', ['v' => $v, 'docTitle' => 'Receipt']) ?>
<table>
    <tr><td class="lbl">Received with thanks from</td><td class="val">: <?= esc($party['name']) ?><?= $party['address'] ? '<br><span style="font-weight:normal">' . nl2br(esc($party['address'])) . '</span>' : '' ?></td></tr>
    <tr><td class="lbl">The sum of</td><td class="val">: <?= esc(str_replace('Rupees', 'Indian Rupees', amount_in_words((float) $v['amount']))) ?></td></tr>
    <tr><td class="lbl">By</td><td class="val" style="font-weight:normal"><table><tr>
        <td>: <?php foreach ($by as $b): ?><?= esc($b['name']) ?><?php endforeach ?><?= $mode ? '; ' . esc($mode) : '' ?><?= $reference ? ' &nbsp; ' . esc($reference) : '' ?> &nbsp; <?= date('j-M-Y', strtotime($v['voucher_date'])) ?></td>
        <td class="r" style="width:30%"><strong><?= money($v['amount'], false) ?></strong></td></tr></table></td></tr>
    <?php if (! empty($against)): ?>
    <tr><td class="lbl">Against bill</td><td class="val" style="font-weight:normal">: <?= esc($against['invoice_no']) ?> dated <?= date('j-M-Y', strtotime($against['invoice_date'])) ?> (Bill ₹<?= money($against['total'], false) ?>, balance now ₹<?= money($against['total'] - $against['amount_paid'], false) ?>)</td></tr>
    <?php endif ?>
    <?php if (! empty($party['account'])): ?><tr><td class="lbl">Account</td><td class="val" style="font-weight:normal">: <?= esc($party['account']) ?></td></tr><?php endif ?>
    <?php if (trim((string) $v['narration']) !== ''): ?><tr><td class="lbl">Remarks</td><td class="val">: <?= esc($v['narration']) ?></td></tr><?php endif ?>
</table>
<br><br><br>
<table><tr>
    <td style="width:50%"><strong style="border-top:1px solid #333;border-bottom:1px solid #333;padding:2px 6px">**In ₹ <?= money($v['amount'], false) ?>/-</strong>
        <?= $cheque ? '<br><span class="muted" style="font-style:italic;font-size:8pt">**Subject to Realisation</span>' : '' ?></td>
    <td class="r">For <?= esc((string) setting('Solar.companyName')) ?><br><?= brand_img('signature', 'max-height:40px;max-width:150px') ?: '<br><br>' ?><br>Authorised Signatory</td>
</tr></table>
</div>
