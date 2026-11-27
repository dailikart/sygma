<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Salary Runs<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="<?= site_url('admin/employees') ?>" class="btn btn-outline-secondary"><i class="bi bi-people"></i> Employees</a>
    <a href="<?= site_url('admin/attendance') ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-check"></i> Attendance</a>
    <?php if (can('payroll.manage')): ?>
    <form method="post" action="<?= site_url('admin/payroll/create') ?>" class="d-flex gap-2 ms-auto"><?= csrf_field() ?>
        <input type="month" name="month" value="<?= esc($suggest) ?>" class="form-control" required>
        <button class="btn btn-primary text-nowrap"><i class="bi bi-calculator"></i> Calculate salaries</button>
    </form>
    <?php endif ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Month</th><th>Run</th><th class="text-end">Staff</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net pay</th><th class="text-end">Employer PF + ESI</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr onclick="location.href='<?= site_url('admin/payroll/' . $r['id']) ?>'" style="cursor:pointer">
            <td class="fw-semibold"><a href="<?= site_url('admin/payroll/' . $r['id']) ?>"><?= date('F Y', strtotime($r['month'])) ?></a></td><td><small><?= esc($r['run_no']) ?></small></td>
            <td class="text-end"><?= (int) $r['staff'] ?></td><td class="text-end"><?= money($r['gross']) ?></td><td class="text-end"><?= money($r['deductions']) ?></td>
            <td class="text-end fw-semibold"><?= money($r['net']) ?></td><td class="text-end"><?= money($r['employer_pf'] + $r['employer_esi']) ?></td>
            <td><?= status_badge($r['status']) ?><?= $r['paid_on'] ? ' <small class="text-muted">' . fdate($r['paid_on']) . '</small>' : '' ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No salary runs yet. Add employees, fill the attendance, then choose the month and click <b>Calculate salaries</b>.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
