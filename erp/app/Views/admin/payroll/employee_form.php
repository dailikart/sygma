<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= $e ? 'Edit ' . esc($e['name']) : 'Add employee' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $v = static fn (string $k, $d = '') => esc((string) old($k, $e[$k] ?? $d));
$chk = static fn (string $k, int $d) => old($k, $e[$k] ?? ($e ? 0 : $d)) ? 'checked' : ''; ?>
<form method="post" action="<?= site_url($e ? 'admin/employees/' . $e['id'] . '/save' : 'admin/employees/save') ?>" style="max-width:1000px"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-3">Person</h2>
    <div class="row g-2">
        <div class="col-md-2"><label class="form-label small">Code *</label><input name="emp_code" class="form-control" required value="<?= $v('emp_code', $code) ?>"></div>
        <div class="col-md-5"><label class="form-label small">Name *</label><input name="name" class="form-control" required value="<?= $v('name') ?>"></div>
        <div class="col-md-3"><label class="form-label small">Phone</label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
        <div class="col-md-2"><label class="form-label small">Gender</label><select name="gender" class="form-select"><?= select_options(['M' => 'Male', 'F' => 'Female', 'O' => 'Other'], old('gender', $e['gender'] ?? ''), '—') ?></select></div>
        <div class="col-md-3"><label class="form-label small">Department</label><select name="department" class="form-select"><?= select_options(array_combine(\App\Controllers\Admin\Employees::DEPARTMENTS, \App\Controllers\Admin\Employees::DEPARTMENTS), old('department', $e['department'] ?? ''), '—') ?></select></div>
        <div class="col-md-3"><label class="form-label small">Designation</label><input name="designation" class="form-control" value="<?= $v('designation') ?>" placeholder="e.g. Technician"></div>
        <div class="col-md-3"><label class="form-label small">Email (payslips are emailed here)</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
        <div class="col-md-3"><label class="form-label small">ERP login (sees own payslips)</label><select name="user_id" class="form-select"><?= select_options($users, old('user_id', $e['user_id'] ?? ''), '— none —') ?></select></div>
        <div class="col-md-3"><label class="form-label small">Date of birth</label><input type="date" name="dob" class="form-control" value="<?= $v('dob') ?>"></div>
        <div class="col-md-3"><label class="form-label small">Joining date</label><input type="date" name="join_date" class="form-control" value="<?= $v('join_date', date('Y-m-d')) ?>"></div>
        <div class="col-md-3"><label class="form-label small">Exit date (when leaving)</label><input type="date" name="exit_date" class="form-control" value="<?= $v('exit_date') ?>"></div>
        <div class="col-12"><label class="form-label small">Address</label><textarea name="address" rows="2" class="form-control"><?= $v('address') ?></textarea></div>
    </div>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-3">Salary</h2>
    <div class="mb-2"><?php $pt = old('pay_type', $e['pay_type'] ?? 'monthly'); ?>
        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="pay_type" id="pt_m" value="monthly" <?= $pt === 'monthly' ? 'checked' : '' ?>><label class="form-check-label" for="pt_m">Monthly salary</label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="pay_type" id="pt_d" value="daily" <?= $pt === 'daily' ? 'checked' : '' ?>><label class="form-check-label" for="pt_d">Daily wage (helpers, site labour)</label></div>
    </div>
    <div class="row g-2" id="monthly">
        <div class="col-md-3"><label class="form-label small">Basic (+ DA) per month</label><input type="number" step="0.01" min="0" name="basic" class="form-control sal" value="<?= $v('basic', 0) ?>"></div>
        <div class="col-md-3"><label class="form-label small">HRA</label><input type="number" step="0.01" min="0" name="hra" class="form-control sal" value="<?= $v('hra', 0) ?>"></div>
        <div class="col-md-3"><label class="form-label small">Conveyance</label><input type="number" step="0.01" min="0" name="conveyance" class="form-control sal" value="<?= $v('conveyance', 0) ?>"></div>
        <div class="col-md-3"><label class="form-label small">Special allowance</label><input type="number" step="0.01" min="0" name="special" class="form-control sal" value="<?= $v('special', 0) ?>"></div>
        <div class="col-12 small text-muted">Monthly gross: <b id="grossOut">0</b>. Paid in proportion to the days worked.</div>
    </div>
    <div class="row g-2" id="daily">
        <div class="col-md-3"><label class="form-label small">Wage per day</label><input type="number" step="0.01" min="0" name="daily_wage" class="form-control" value="<?= $v('daily_wage', 0) ?>"></div>
        <div class="col-md-9 small text-muted align-self-end">Paid for days present (half for a half day) and paid leave.</div>
    </div>
    <hr>
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="pf_applicable" value="1" id="pf" <?= $chk('pf_applicable', 0) ?>><label class="form-check-label" for="pf">PF (EPF) applicable</label></div></div>
        <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="esi_applicable" value="1" id="esi" <?= $chk('esi_applicable', 0) ?>><label class="form-check-label" for="esi">ESI applicable</label></div></div>
        <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="pt_applicable" value="1" id="ptx" <?= $chk('pt_applicable', 1) ?>><label class="form-check-label" for="ptx">Professional tax</label></div></div>
        <div class="col-md-3"><label class="form-label small">TDS per month (₹)</label><input type="number" step="0.01" min="0" name="tds_monthly" class="form-control" value="<?= $v('tds_monthly', 0) ?>"></div>
    </div>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-3">Bank &amp; statutory</h2>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label small">Bank</label><input name="bank_name" class="form-control" value="<?= $v('bank_name') ?>"></div>
        <div class="col-md-4"><label class="form-label small">Account number</label><input name="bank_account" class="form-control" value="<?= $v('bank_account') ?>"></div>
        <div class="col-md-4"><label class="form-label small">IFSC</label><input name="ifsc" class="form-control text-uppercase" value="<?= $v('ifsc') ?>"></div>
        <div class="col-md-3"><label class="form-label small">PAN</label><input name="pan" class="form-control text-uppercase" maxlength="10" value="<?= $v('pan') ?>"></div>
        <div class="col-md-3"><label class="form-label small">Aadhaar</label><input name="aadhaar" class="form-control" maxlength="14" value="<?= $v('aadhaar') ?>"></div>
        <div class="col-md-3"><label class="form-label small">UAN (PF)</label><input name="uan" class="form-control" value="<?= $v('uan') ?>"></div>
        <div class="col-md-3"><label class="form-label small">ESI number</label><input name="esi_no" class="form-control" value="<?= $v('esi_no') ?>"></div>
        <div class="col-12"><label class="form-label small">Notes</label><textarea name="notes" rows="2" class="form-control"><?= $v('notes') ?></textarea></div>
    </div>
</div></div>
<button class="btn btn-primary">Save employee</button>
<a href="<?= site_url($e ? 'admin/employees/' . $e['id'] : 'admin/employees') ?>" class="btn btn-light">Cancel</a>
</form>
<script>
(() => {
    const sal = [...document.querySelectorAll('.sal')], out = document.getElementById('grossOut');
    const sum = () => out.textContent = sal.reduce((a, i) => a + (parseFloat(i.value) || 0), 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    sal.forEach(i => i.addEventListener('input', sum)); sum();
    const toggle = () => { const d = document.getElementById('pt_d').checked; document.getElementById('daily').style.display = d ? '' : 'none'; document.getElementById('monthly').style.display = d ? 'none' : ''; };
    document.querySelectorAll('[name=pay_type]').forEach(r => r.addEventListener('change', toggle)); toggle();
})();
</script>
<?= $this->endSection() ?>
