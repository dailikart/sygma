<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Dispatch List – <?= esc($project['project_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$itemMap = array_column($items, null, 'id');
$locId   = (int) (old('location_id') ?: (count($locations) === 1 ? array_key_first($locations) : 0));
$opt = static function ($sel = null) use ($items) {
    $h = '<option value="">— Item —</option>';
    foreach ($items as $i) { $h .= '<option value="' . $i['id'] . '" data-unit="' . esc($i['unit']) . '"' . ((string) $sel === (string) $i['id'] ? ' selected' : '') . '>' . esc($i['name']) . '</option>'; }
    return $h;
};
$row = static fn ($i, $r) => '<tr><td class="sno"></td><td><select name="items[' . $i . '][item_id]" class="form-select form-select-sm dsp-item">' . $opt($r['item_id'] ?? null) . '</select></td>'
    . '<td><div class="d-flex align-items-center gap-2"><input type="number" step="0.01" min="0" name="items[' . $i . '][qty]" class="form-control form-control-sm dsp-qty" style="max-width:110px" value="' . esc($r['qty'] ?? '') . '"><span class="small dsp-stock text-nowrap"></span></div></td>'
    . '<td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row><i class="bi bi-trash"></i> Remove</button></td></tr>';
?>
<div class="card card-body mb-3">
    <div class="row g-2 small">
        <div class="col-md-4"><strong><?= esc($customer['name']) ?></strong><br><?= esc((string) $project['site_address']) ?><br><?= esc((string) $customer['phone']) ?></div>
        <div class="col-md-4">System: <strong><?= qty($project['capacity_kw']) ?> kW</strong><br>Customer: <?= status_badge($project['customer_type']) ?> <?= status_badge($project['subsidy_type']) ?></div>
        <div class="col-md-4"><form method="get" class="d-flex gap-2"><select name="kit" class="form-select form-select-sm"><?php foreach ($kits as $k): ?><option value="<?= $k['id'] ?>" <?= (int) $k['id'] === $kitId ? 'selected' : '' ?>><?= esc($k['name']) ?> (<?= qty($k['capacity_kw']) ?> kW)</option><?php endforeach ?></select><button class="btn btn-sm btn-outline-secondary text-nowrap">Load group</button></form>
            <?php if (! $kits): ?><span class="text-danger">No item groups yet – create one under Inventory → Item Groups.</span><?php endif ?></div>
    </div>
</div>
<form method="post" action="<?= site_url('admin/projects/' . $project['id'] . '/dispatch') ?>">
<?= csrf_field() ?>
<div class="card card-body">
    <div class="row g-2 mb-3"><div class="col-md-4"><label class="form-label">Dispatch from store *</label><select name="location_id" id="loc" class="form-select" required><?= select_options($locations, $locId, '— Select —') ?></select></div></div>
    <table class="table align-middle line-items" id="dsp" data-template="#dsp-tpl">
        <thead><tr><th style="width:50px">S.No</th><th>Item name</th><th>Quantity, unit &amp; stock</th><th style="width:110px">Action</th></tr></thead>
        <tbody><?php foreach ($rows ?: [[]] as $i => $r) { echo $row($i, $r); } ?></tbody>
    </table>
    <template id="dsp-tpl"><?= $row('__i__', []) ?></template>
    <div><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#dsp"><i class="bi bi-plus"></i> Add item</button></div>
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Save dispatch list</button><a href="<?= site_url('admin/projects/' . $project['id']) ?>" class="btn btn-light">Cancel</a></div>
</div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const STOCK = <?= json_encode($balances) ?>;
function refreshStock() {
    const loc = document.getElementById('loc').value;
    document.querySelectorAll('#dsp tbody tr').forEach(function (tr, n) {
        tr.querySelector('.sno').textContent = n + 1;
        const sel = tr.querySelector('.dsp-item'), out = tr.querySelector('.dsp-stock'), q = parseFloat(tr.querySelector('.dsp-qty').value) || 0;
        if (!sel.value) { out.innerHTML = ''; return; }
        const unit = sel.selectedOptions[0].dataset.unit || '';
        const have = loc ? ((STOCK[sel.value] || {})[loc] || 0) : null;
        out.innerHTML = unit + (have === null ? ' <span class="text-muted">(choose store)</span>' : ' <span class="text-primary">(Stock: ' + have + ')</span>' + (have <= 0 ? ' <strong class="text-danger">Out of Stock</strong>' : (q > have ? ' <strong class="text-danger">Not enough</strong>' : '')));
    });
}
document.addEventListener('change', refreshStock); document.addEventListener('input', refreshStock); document.addEventListener('click', () => setTimeout(refreshStock, 0));
refreshStock();
</script>
<?= $this->endSection() ?>
