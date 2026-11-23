<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Journal / Contra Voucher<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $opt = select_options($ledgers, null, '— Ledger —');
$row = static fn ($i) => '<tr><td><select name="lines[' . $i . '][ledger_id]" class="form-select form-select-sm">' . $opt . '</select></td><td><input type="number" step="0.01" min="0" name="lines[' . $i . '][debit]" class="form-control form-control-sm jv-dr"></td><td><input type="number" step="0.01" min="0" name="lines[' . $i . '][credit]" class="form-control form-control-sm jv-cr"></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x"></i></button></td></tr>'; ?>
<form method="post" action="<?= site_url('admin/accounts/journal') ?>" class="card card-body" style="max-width:900px">
    <?= csrf_field() ?>
    <div class="mb-2"><span class="badge text-bg-light border fs-6">Voucher no: <?= esc($nextNo) ?></span> <small class="text-muted">(given automatically when you save)</small></div>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><label class="form-label">Type</label><select name="voucher_type" class="form-select"><option value="Journal">Journal</option><option value="Contra">Contra (bank ↔ cash)</option></select></div>
        <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="voucher_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Narration *</label><input name="narration" class="form-control" required></div>
    </div>
    <table class="table table-sm line-items" id="jv" data-template="#jv-tpl"><thead><tr><th>Ledger</th><th style="width:150px">Debit</th><th style="width:150px">Credit</th><th style="width:40px"></th></tr></thead>
        <tbody><?= $row(0) . $row(1) ?></tbody>
        <tfoot><tr class="fw-semibold"><td class="text-end">Total</td><td id="jv-dr">0.00</td><td id="jv-cr">0.00</td><td></td></tr></tfoot></table>
    <template id="jv-tpl"><?= $row('__i__') ?></template>
    <div><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#jv"><i class="bi bi-plus"></i> Add line</button></div>
    <div class="mt-3"><button class="btn btn-primary">Save voucher</button></div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.addEventListener('input', function () {
    let d = 0, c = 0;
    document.querySelectorAll('.jv-dr').forEach(e => d += parseFloat(e.value) || 0);
    document.querySelectorAll('.jv-cr').forEach(e => c += parseFloat(e.value) || 0);
    document.getElementById('jv-dr').textContent = d.toFixed(2);
    document.getElementById('jv-cr').textContent = c.toFixed(2);
    document.getElementById('jv-cr').className = Math.abs(d - c) > 0.005 ? 'text-danger' : '';
});
</script>
<?= $this->endSection() ?>
