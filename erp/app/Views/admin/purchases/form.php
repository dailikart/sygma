<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>New <?= esc($c['single']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/' . $c['uri']) ?>">
<?= csrf_field() ?>
<?php if (! empty($head['purchase_order_id'])): ?><input type="hidden" name="purchase_order_id" value="<?= (int) $head['purchase_order_id'] ?>"><div class="alert alert-info">Receiving against purchase order <strong><?= esc($head['po_no']) ?></strong>. Change quantities if only part has arrived.</div><?php endif ?>
<?php if ($c['table'] === 'purchase_returns'): ?>
<?= view('admin/purchases/return_lines', ['head' => $head, 'rows' => $rows]) ?>
<?php else: ?>
<div class="card card-body mb-3"><div class="row g-3">
    <div class="col-md-5"><label class="form-label">Supplier *</label><select name="supplier_id" class="form-select" required><option value="">— Select —</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int) old('supplier_id', $head['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?><?= $s['state'] ? ' (' . esc($s['state']) . ')' : '' ?></option><?php endforeach ?></select>
        <?php if (! $suppliers): ?><div class="form-text text-danger">Add a supplier first.</div><?php endif ?></div>
    <?php if ($c['table'] === 'purchases'): ?>
    <div class="col-md-4"><label class="form-label">Supplier invoice no. *</label><input name="bill_no" class="form-control" required maxlength="50" placeholder="As printed on the supplier's invoice" value="<?= esc(old('bill_no')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Supplier invoice date *</label><input type="date" name="doc_date" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= esc(old('doc_date', date('Y-m-d'))) ?>"></div>
    <?php else: ?>
    <div class="col-md-3"><label class="form-label">Date *</label><input type="date" name="doc_date" class="form-control" required value="<?= esc(old('doc_date', date('Y-m-d'))) ?>"></div>
    <?php endif ?>
    <?php if ($c['table'] === 'purchase_orders'): ?><div class="col-md-4"><label class="form-label">Expected delivery</label><input type="date" name="expected_date" class="form-control" value="<?= esc(old('expected_date')) ?>"></div><?php endif ?>
    <?php if ($c['hasLocation']): ?><div class="col-md-4"><label class="form-label"><?= $c['table'] === 'purchases' ? 'Receive into' : 'Return from' ?> location *</label><select name="location_id" class="form-select" required><?= select_options($locations, old('location_id', $head['location_id'] ?? (count($locations) === 1 ? array_key_first($locations) : '')), '— Select —') ?></select></div><?php endif ?>
</div></div>
<div class="card card-body mb-3"><?= view('admin/partials/line_items', ['items' => $items, 'price' => 'purchase_price', 'rows' => $rows]) ?></div>
<?php endif ?>
<div class="card card-body mb-3"><label class="form-label"><?= $c['table'] === 'purchase_returns' ? 'Reason for return' : 'Notes' ?></label><textarea name="notes" class="form-control" rows="2"><?= esc(old('notes')) ?></textarea></div>
<button class="btn btn-primary">Save</button> <a href="<?= site_url('admin/' . $c['uri']) ?>" class="btn btn-light">Cancel</a>
</form>
<?= $this->endSection() ?>
