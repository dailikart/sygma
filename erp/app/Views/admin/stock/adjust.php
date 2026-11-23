<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Opening Stock / Adjustment<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$opt = '<option value="">— Item —</option>';
foreach ($items as $i) { $opt .= '<option value="' . $i['id'] . '">' . esc($i['name']) . ' (' . esc($i['unit']) . ', in stock ' . qty(array_sum($balances[$i['id']] ?? [])) . ')</option>'; }
$row = static fn ($i) => '<tr><td><select name="items[' . $i . '][item_id]" class="form-select form-select-sm">' . $opt . '</select></td><td><input type="number" step="0.01" name="items[' . $i . '][qty]" class="form-control form-control-sm" placeholder="+10 or -2"></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x"></i></button></td></tr>';
?>
<form method="post" action="<?= site_url('admin/stock/adjust') ?>" class="card card-body" style="max-width:860px">
    <?= csrf_field() ?>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label">Location *</label><select name="location_id" class="form-select" required><?= select_options($locations, null, '— Select —') ?></select>
            <?php if (! $locations): ?><div class="form-text text-danger">Add a location first.</div><?php endif ?></div>
        <div class="col-md-3"><label class="form-label">Type</label><select name="type" class="form-select"><option value="opening">Opening stock</option><option value="adjustment">Adjustment</option></select></div>
        <div class="col-md-5"><label class="form-label">Reason *</label><input name="note" class="form-control" required placeholder="e.g. Opening stock as on 01-04 / physical count"></div>
    </div>
    <table class="table table-sm line-items" id="adj" data-template="#adj-tpl"><thead><tr><th>Item</th><th style="width:160px">Qty (+ add / − remove)</th><th></th></tr></thead><tbody><?= $row(0) ?></tbody></table>
    <template id="adj-tpl"><?= $row('__i__') ?></template>
    <div><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#adj"><i class="bi bi-plus"></i> Add line</button></div>
    <div class="mt-3"><button class="btn btn-primary">Save</button></div>
</form>
<?= $this->endSection() ?>
