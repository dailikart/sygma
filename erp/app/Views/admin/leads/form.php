<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= $row ? 'Edit Lead' : 'New Lead' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $v = static fn ($f, $d = '') => old($f, $row[$f] ?? $d); $wf = config('Workflow'); ?>
<form method="post" action="<?= site_url('admin/leads/save' . ($row ? '/' . $row['id'] : '')) ?>" class="card card-body" style="max-width:900px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name *</label><input name="name" class="form-control" required value="<?= esc($v('name')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Phone *</label><input name="phone" class="form-control" required value="<?= esc($v('phone')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= esc($v('email')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= esc($v('address')) ?></textarea></div>
        <div class="col-md-3"><label class="form-label">City</label><input name="city" class="form-control" value="<?= esc($v('city')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Source</label><select name="source" class="form-select"><?= select_options(array_combine($wf->leadSources, $wf->leadSources), $v('source'), '— Select —') ?></select></div>
        <div class="col-md-3"><label class="form-label">Customer type *</label><select name="customer_type" class="form-select"><?= select_options(['b2c' => 'B2C (home / individual)', 'b2b' => 'B2B (business / institution)'], $v('customer_type', 'b2c')) ?></select></div>
        <div class="col-md-3"><label class="form-label">Required kW</label><input type="number" step="0.01" name="required_kw" class="form-control" value="<?= esc($v('required_kw')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Monthly EB bill (₹)</label><input type="number" step="0.01" name="monthly_bill" class="form-control" value="<?= esc($v('monthly_bill')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Assigned to</label><select name="assigned_to" class="form-select"><?= select_options($staff, $v('assigned_to', $row ? '' : auth()->id()), '— Nobody —') ?></select></div>
        <?php if ($row && $row['status'] !== 'confirmed'): ?>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><?= select_options(array_diff_key($wf->leadStatuses, ['confirmed' => 1]), $v('status')) ?></select></div>
        <?php endif ?>
        <div class="col-md-3"><label class="form-label">Next follow-up</label><input type="date" name="next_followup_date" class="form-control" value="<?= esc($v('next_followup_date', $row ? '' : date('Y-m-d', strtotime('+1 day')))) ?>"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= esc($v('notes')) ?></textarea></div>
        <?php if (! $row): ?><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="allow_duplicate" value="1" id="dup"><label class="form-check-label small" for="dup">Save anyway if the phone number already exists</label></div><?php endif ?>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save</button> <a href="<?= site_url('admin/leads') ?>" class="btn btn-light">Cancel</a></div>
</form>
<?= $this->endSection() ?>
