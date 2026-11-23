<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Ledger: <?= esc($ledger['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end"><?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?><span class="ms-auto text-muted"><?= esc($ledger['group_name']) ?></span></div>
<?php $links = ['invoice' => 'invoices', 'purchase' => 'purchases', 'purchase_return' => 'purchase-returns']; ?>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>Type</th><th>Voucher</th><th>Narration</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead><tbody>
    <tr class="table-light"><td colspan="6">Opening balance</td><td class="text-end"><?= \App\Libraries\Ledger::drcr($opening) ?></td></tr>
    <?php $bal = $opening; $dr = 0; $cr = 0; foreach ($rows as $r): $bal += $r['debit'] - $r['credit']; $dr += $r['debit']; $cr += $r['credit']; ?>
        <tr><td><?= fdate($r['voucher_date']) ?></td><td><?= esc($r['voucher_type']) ?></td>
            <td><?php if (isset($links[$r['ref_type']])): ?><a href="<?= site_url('admin/' . $links[$r['ref_type']] . '/' . $r['ref_id']) ?>"><?= esc($r['voucher_no']) ?></a><?php else: ?><a href="<?= site_url('admin/vouchers/' . $r['voucher_id'] . '/print') ?>" target="_blank"><?= esc($r['voucher_no']) ?></a><?php endif ?></td>
            <td><small><?= esc((string) $r['narration']) ?></small></td>
            <td class="text-end"><?= $r['debit'] > 0 ? money($r['debit'], false) : '' ?></td><td class="text-end"><?= $r['credit'] > 0 ? money($r['credit'], false) : '' ?></td>
            <td class="text-end"><?= \App\Libraries\Ledger::drcr($bal) ?></td></tr>
    <?php endforeach ?>
    </tbody><tfoot class="fw-semibold"><tr><td colspan="4" class="text-end">Total / closing</td><td class="text-end"><?= money($dr, false) ?></td><td class="text-end"><?= money($cr, false) ?></td><td class="text-end"><?= \App\Libraries\Ledger::drcr($bal) ?></td></tr></tfoot>
</table></div></div>
<?= $this->endSection() ?>
