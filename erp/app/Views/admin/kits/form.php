<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= $kit ? 'Edit Item Group' : 'New Item Group' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$opt = static function ($items, $sel = null) {
    $h = '<option value="">— Item —</option>'; $last = null;
    foreach ($items as $i) {
        if ($i['category'] !== $last) { $h .= ($last !== null ? '</optgroup>' : '') . '<optgroup label="' . esc($i['category'] ?? 'Other') . '">'; $last = $i['category']; }
        $h .= '<option value="' . $i['id'] . '"' . ((string) $sel === (string) $i['id'] ? ' selected' : '') . '>' . esc($i['name']) . ' (' . esc($i['unit']) . ')</option>';
    }
    return $h . ($last !== null ? '</optgroup>' : '');
};
$row = static fn ($i, $r) => '<tr><td><select name="items[' . $i . '][item_id]" class="form-select form-select-sm">' . $opt($items, $r['item_id'] ?? null) . '</select></td>'
    . '<td><input type="number" step="0.01" min="0" name="items[' . $i . '][qty]" class="form-control form-control-sm" value="' . esc($r['qty'] ?? '') . '"></td>'
    . '<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x"></i></button></td></tr>';
?>
<form method="post" action="<?= site_url('admin/kits/save' . ($kit ? '/' . $kit['id'] : '')) ?>" class="card card-body" style="max-width:900px">
    <?= csrf_field() ?>
    <div class="row g-3 mb-3">
        <div class="col-md-5"><label class="form-label">Name * (e.g. 3 kW On-grid kit)</label><input name="name" class="form-control" required value="<?= esc(old('name', $kit['name'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Installation type</label><select name="installation_type_id" class="form-select"><?= select_options($types, old('installation_type_id', $kit['installation_type_id'] ?? ''), 'All types') ?></select></div>
        <div class="col-md-3"><label class="form-label">Capacity (kW) *</label><input type="number" step="0.01" name="capacity_kw" class="form-control" required value="<?= esc(old('capacity_kw', $kit['capacity_kw'] ?? '')) ?>"></div>
        <div class="col-12"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= esc(old('description', $kit['description'] ?? '')) ?>"></div>
    </div>
    <table class="table table-sm line-items" id="kit-lines" data-template="#kit-tpl">
        <thead><tr><th>Item</th><th style="width:140px">Qty</th><th style="width:40px"></th></tr></thead>
        <tbody><?php foreach ($lines ?: [[]] as $i => $r) { echo $row($i, $r); } ?></tbody>
    </table>
    <template id="kit-tpl"><?= $row('__i__', []) ?></template>
    <div><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#kit-lines"><i class="bi bi-plus"></i> Add item</button></div>
    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ka" <?= ($kit['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="ka">Active</label></div>
    <div class="mt-3"><button class="btn btn-primary">Save</button> <a href="<?= site_url('admin/kits') ?>" class="btn btn-light">Cancel</a></div>
</form>
<?= $this->endSection() ?>
