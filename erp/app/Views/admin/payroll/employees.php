<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Employees<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <form class="d-flex flex-wrap gap-2" method="get">
        <input name="q" class="form-control form-control-sm" style="max-width:220px" placeholder="Name, code, phone" value="<?= esc((string) service('request')->getGet('q')) ?>">
        <select name="department" class="form-select form-select-sm" style="max-width:170px"><?= select_options(array_combine(\App\Controllers\Admin\Employees::DEPARTMENTS, \App\Controllers\Admin\Employees::DEPARTMENTS), (string) service('request')->getGet('department'), 'All departments') ?></select>
        <select name="show" class="form-select form-select-sm" style="max-width:150px"><?= select_options(['active' => 'Working', 'left' => 'Left', 'all' => 'All'], $show) ?></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <div class="ms-auto d-flex gap-2">
        <a href="<?= site_url('admin/attendance') ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-check"></i> Attendance</a>
        <a href="<?= site_url('admin/payroll') ?>" class="btn btn-outline-secondary"><i class="bi bi-cash-stack"></i> Salary runs</a>
        <?php if (can('payroll.manage')): ?><a href="<?= site_url('admin/employees/new') ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add employee</a><?php endif ?>
    </div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Phone</th><th>Pay</th><th class="text-end">Monthly gross / day wage</th><th>PF · ESI</th><th>Login</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $gross = $r['basic'] + $r['hra'] + $r['conveyance'] + $r['special']; ?>
        <tr onclick="location.href='<?= site_url('admin/employees/' . $r['id']) ?>'" style="cursor:pointer" class="<?= $r['status'] === 'left' ? 'text-muted' : '' ?>">
            <td><?= esc($r['emp_code']) ?></td>
            <td><a href="<?= site_url('admin/employees/' . $r['id']) ?>" class="fw-semibold"><?= esc($r['name']) ?></a><div class="small text-muted"><?= esc((string) $r['designation']) ?><?= $r['status'] === 'left' ? ' · left ' . fdate($r['exit_date']) : '' ?></div></td>
            <td><small><?= esc((string) $r['department']) ?></small></td>
            <td><small><?= esc((string) $r['phone']) ?></small></td>
            <td><small><?= $r['pay_type'] === 'daily' ? 'Daily wage' : 'Monthly' ?></small></td>
            <td class="text-end"><?= $r['pay_type'] === 'daily' ? money($r['daily_wage']) . ' <small class="text-muted">/ day</small>' : money($gross) ?></td>
            <td><small><?= $r['pf_applicable'] ? 'PF' : '' ?><?= $r['pf_applicable'] && $r['esi_applicable'] ? ' · ' : '' ?><?= $r['esi_applicable'] ? 'ESI' : '' ?></small></td>
            <td><small><?= esc((string) $r['username']) ?></small></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No employees yet.<?= can('payroll.manage') ? ' <a href="' . site_url('admin/employees/new') . '">Add the first one</a>.' : '' ?></td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
