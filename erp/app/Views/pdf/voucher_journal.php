<div class="frame">
<?= view('pdf/_voucher_head', ['v' => $v, 'docTitle' => $v['voucher_type'] . ' Voucher']) ?>
<table class="lines">
    <thead><tr><th>Particulars</th><th class="r" style="width:22%">Debit (₹)</th><th class="r" style="width:22%">Credit (₹)</th></tr></thead>
    <tbody>
    <?php $dr = 0; $cr = 0; foreach ($entries as $e): $dr += $e['debit']; $cr += $e['credit']; ?>
        <tr><td><?= $e['debit'] > 0 ? 'Dr ' : '&nbsp;&nbsp;&nbsp;&nbsp;To ' ?><?= esc($e['name']) ?> <span class="muted" style="font-size:8pt">(<?= esc($e['group_name']) ?>)</span></td>
            <td class="r"><?= $e['debit'] > 0 ? money($e['debit'], false) : '' ?></td><td class="r"><?= $e['credit'] > 0 ? money($e['credit'], false) : '' ?></td></tr>
    <?php endforeach ?>
    <tr><td class="r"><strong>Total</strong></td><td class="r"><strong><?= money($dr, false) ?></strong></td><td class="r"><strong><?= money($cr, false) ?></strong></td></tr>
    </tbody>
</table>
<?php if (trim((string) $v['narration']) !== ''): ?><p><em>Narration:</em> <?= esc($v['narration']) ?></p><?php endif ?>
<p class="muted" style="font-size:8.5pt"><?= esc(amount_in_words((float) $v['amount'])) ?></p>
<br><br>
<table><tr><td>Prepared by: <?= esc(user_name($v['created_by'])) ?></td><td class="c">Checked by</td><td class="r"><?= brand_img('signature', 'max-height:40px;max-width:150px') ?: '<br><br>' ?><br>Authorised Signatory</td></tr></table>
</div>
