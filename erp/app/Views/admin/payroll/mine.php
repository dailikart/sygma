<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>My Payslips<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if (! $emp): ?><div class="alert alert-info">Your login is not linked to an employee record. Ask the office to link it (Payroll → Employees → Edit → ERP login).</div>
<?php else: ?>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Month</th><th class="text-end">Paid days</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net pay</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= date('F Y', strtotime($r['month'])) ?></td><td class="text-end"><?= qty($r['paid_days']) ?></td><td class="text-end"><?= money($r['gross']) ?></td><td class="text-end"><?= money($r['total_deductions']) ?></td><td class="text-end fw-semibold"><?= money($r['net']) ?></td>
        <td class="text-end"><a href="<?= site_url('admin/payroll/' . $r['run_id'] . '/payslip/' . $r['employee_id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> Payslip</a></td></tr><?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No payslips yet.</td></tr><?php endif ?>
</tbody></table></div></div>
<?php endif ?>
<?= $this->endSection() ?>
