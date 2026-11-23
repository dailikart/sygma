<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= $row && isset($row['id']) ? 'Edit Customer' : 'New Customer' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $v = static fn ($f, $d = '') => old($f, $row[$f] ?? $d); ?>
<form method="post" action="<?= site_url('admin/customers/save' . ($row ? '/' . $row['id'] : '')) ?>" class="card card-body" style="max-width:900px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Customer type *</label>
            <select name="type" class="form-select" required><?= select_options(['b2c' => 'B2C (home / individual)', 'b2b' => 'B2B (business / institution)'], $v('type', 'b2c')) ?></select></div>
        <div class="col-md-8"><label class="form-label">Name * <small class="text-muted">(also the party ledger name in Tally)</small></label><input name="name" class="form-control" required value="<?= esc($v('name')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Company</label><input name="company" class="form-control" value="<?= esc($v('company')) ?>"></div>
        <div class="col-md-6"><label class="form-label">GSTIN <small class="text-muted">(B2B)</small></label><input name="gstin" maxlength="15" class="form-control text-uppercase" value="<?= esc($v('gstin')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Phone *</label><input name="phone" class="form-control" required value="<?= esc($v('phone')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Email <small class="text-muted">(receipts are sent here)</small></label><input type="email" name="email" class="form-control" value="<?= esc($v('email')) ?>"></div>
        <div class="col-md-4"><label class="form-label">EB service / consumer no.</label><input name="eb_service_no" class="form-control" value="<?= esc($v('eb_service_no')) ?>"></div>
        <div class="col-md-5"><label class="form-label">EB / meter charges paid by</label><select name="eb_charges_by" class="form-select eb-by"><?= select_options(eb_charges_options(), $v('eb_charges_by')) ?></select>
            <div class="form-text">Default for this customer's invoices.</div></div>
        <div class="col-md-3 eb-amt"><label class="form-label">Meter charges collected from customer (₹)</label><input type="number" step="0.01" min="0" name="eb_charges_amount" class="form-control" value="<?= esc((string) ($v('eb_charges_amount') ?: '5000')) ?>"></div>
        <div class="col-md-4"></div>
        <div class="col-12"><label class="form-label">Site / billing address</label><textarea name="address" class="form-control" rows="2"><?= esc($v('address')) ?></textarea></div>
        <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= esc($v('city')) ?>"></div>
        <div class="col-md-4"><label class="form-label">State</label><input name="state" class="form-control" value="<?= esc($v('state', setting('Solar.companyState'))) ?>"></div>
        <div class="col-md-4"><label class="form-label">PIN code</label><input name="pincode" class="form-control" value="<?= esc($v('pincode')) ?>"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= esc($v('notes')) ?></textarea></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save</button> <a href="<?= site_url('admin/customers') ?>" class="btn btn-light">Cancel</a></div>
</form>
<script>
document.querySelectorAll('.eb-by').forEach(function (sel) {
    const amt = sel.closest('form').querySelector('.eb-amt');
    const sync = () => { amt.hidden = sel.value !== 'customer'; };
    sel.addEventListener('change', sync); sync();
});
</script>
<?= $this->endSection() ?>
