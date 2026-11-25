<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Stock Lots: <?= esc($item['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $left = 0; $value = 0; foreach ($lots as $l) { $left += $l['qty_left']; $value += $l['qty_left'] * $l['rate']; } ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <span>In store: <strong><?= qty($left) ?> <?= esc($item['unit']) ?></strong> · Value (FIFO) <strong><?= money($value) ?></strong></span>
    <a href="<?= site_url('admin/stock/item/' . $item['id']) ?>" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-list-ul"></i> Stock ledger</a>
    <a href="<?= site_url('admin/stock') ?>" class="btn btn-sm btn-outline-secondary">Stock balance</a>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Received</th><th>From (purchase · supplier bill · PO)</th><th>Supplier</th><th>Store</th><th class="text-end">Rate</th><th class="text-end">In</th><th class="text-end">Used</th><th class="text-end">Left</th><th style="min-width:260px">Went to</th></tr></thead><tbody>
    <?php foreach ($lots as $l): $used = $l['qty_in'] - $l['qty_left']; ?>
        <tr class="<?= $l['qty_left'] > 0 ? '' : 'text-muted' ?>">
            <td class="text-nowrap"><?= fdate($l['received_on']) ?></td>
            <td><?= view('admin/stock/_lot_source', ['lot' => $l]) ?></td>
            <td><small><?= esc((string) $l['supplier']) ?></small></td>
            <td><small><?= esc((string) $l['location']) ?></small></td>
            <td class="text-end"><?= money($l['rate'], false) ?></td>
            <td class="text-end"><?= qty($l['qty_in']) ?></td>
            <td class="text-end"><?= $used > 0 ? qty($used) : '' ?></td>
            <td class="text-end fw-semibold"><?= $l['qty_left'] > 0 ? qty($l['qty_left']) : '<span class="badge text-bg-secondary">used up</span>' ?></td>
            <td><?= view('admin/stock/_lot_uses', ['used' => $l['used']]) ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $lots): ?><tr><td colspan="9" class="text-center text-muted py-4">No stock received for this item yet.</td></tr><?php endif ?>
</tbody></table></div></div>
<p class="small text-muted mt-2">Every purchase (goods received), opening stock or stock added by adjustment makes a <b>lot</b>. Dispatches take from the oldest lot first (FIFO); a purchase return takes from the lots of the purchase it returns. So each piece can be traced back to its PO and supplier bill, and the project cost uses the real purchase rate.</p>
<?= $this->endSection() ?>
