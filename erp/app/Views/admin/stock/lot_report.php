<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Stock Ageing<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $buckets = ['0–30 days' => 30, '31–90 days' => 90, '91–180 days' => 180, 'Over 180 days' => PHP_INT_MAX];
$sum = array_fill_keys(array_keys($buckets), 0.0); $total = 0;
foreach ($lots as &$l) { $l['value'] = $l['qty_left'] * $l['rate']; $total += $l['value'];
    foreach ($buckets as $k => $max) { if ($l['age'] <= $max) { $sum[$k] += $l['value']; $l['bucket'] = $k; break; } } } unset($l); ?>
<form method="get" class="d-flex flex-wrap gap-2 mb-3">
    <select name="location" class="form-select form-select-sm" style="max-width:220px"><?= select_options($locations, $locId ?: '', 'All locations') ?></select>
    <select name="category" class="form-select form-select-sm" style="max-width:220px"><?= select_options($categories, service('request')->getGet('category'), 'All categories') ?></select>
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
    <a href="<?= site_url('admin/stock') ?>" class="btn btn-sm btn-outline-secondary ms-auto">Stock balance</a>
</form>
<div class="row g-2 mb-3">
    <?php foreach ($sum as $k => $v): ?><div class="col-6 col-md"><div class="card card-body py-2"><div class="small text-muted"><?= $k ?></div><div class="fw-semibold <?= $k === 'Over 180 days' && $v > 0 ? 'text-danger' : '' ?>"><?= money($v) ?></div></div></div><?php endforeach ?>
    <div class="col-12 col-md"><div class="card card-body py-2"><div class="small text-muted">Total stock value (FIFO)</div><div class="fw-semibold"><?= money($total) ?></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
    <thead><tr><th>Item</th><th>Store</th><th>From (purchase · supplier bill · PO)</th><th>Supplier</th><th>Received</th><th class="text-end">Age</th><th class="text-end">Left</th><th class="text-end">Rate</th><th class="text-end">Value</th></tr></thead><tbody>
    <?php foreach ($lots as $l): ?>
        <tr><td><a href="<?= site_url('admin/stock/item/' . $l['item_id'] . '/lots') ?>"><?= esc($l['item']) ?></a><div class="small text-muted"><?= esc((string) $l['category']) ?></div></td>
            <td><small><?= esc((string) $l['location']) ?></small></td>
            <td><?= view('admin/stock/_lot_source', ['lot' => $l]) ?></td>
            <td><small><?= esc((string) $l['supplier']) ?></small></td>
            <td class="text-nowrap"><?= fdate($l['received_on']) ?></td>
            <td class="text-end <?= $l['age'] > 180 ? 'text-danger fw-semibold' : ($l['age'] > 90 ? 'text-warning' : '') ?>"><?= (int) $l['age'] ?> d</td>
            <td class="text-end"><?= qty($l['qty_left']) ?> <small class="text-muted"><?= esc($l['unit']) ?></small></td>
            <td class="text-end"><?= money($l['rate'], false) ?></td>
            <td class="text-end"><?= money($l['value']) ?></td></tr>
    <?php endforeach ?>
    <?php if (! $lots): ?><tr><td colspan="9" class="text-center text-muted py-4">No stock in store.</td></tr><?php endif ?>
</tbody></table></div></div>
<p class="small text-muted mt-2">Each row is stock still in store from one purchase bill (or opening stock). Old stock is sent out first, so lots that keep getting older are slow-moving material.</p>
<?= $this->endSection() ?>
