<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Salaries · <?= date('F Y', strtotime($run['month'])) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $draft = $run['status'] === 'draft'; $mg = can('payroll.manage'); $sum = static fn ($k) => array_sum(array_column($lines, $k)); ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <?= status_badge($run['status']) ?> <span class="text-muted"><?= esc($run['run_no']) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <a href="<?= site_url('admin/attendance?month=' . substr($run['month'], 0, 7)) ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-check"></i> Attendance</a>
        <a href="<?= site_url('admin/payroll/' . $run['id'] . '/payslips') ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> All payslips</a>
        <div class="btn-group"><a href="<?= site_url('admin/payroll/' . $run['id'] . '/export/register') ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> Register</a>
            <a href="<?= site_url('admin/payroll/' . $run['id'] . '/export/bank') ?>" class="btn btn-outline-secondary">Bank transfer sheet</a></div>
        <?php if ($mg && $draft): ?>
            <form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/recalc') ?>"><?= csrf_field() ?><button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Recalculate</button></form>
            <form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/approve') ?>"><?= csrf_field() ?><button class="btn btn-success" data-confirm="Approve salaries of <?= date('F Y', strtotime($run['month'])) ?> (net <?= money($run['net']) ?>)? The journal is posted and attendance is locked."><i class="bi bi-check2-circle"></i> Approve</button></form>
            <form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Delete this draft?"><i class="bi bi-trash"></i></button></form>
        <?php elseif ($mg && $run['status'] === 'approved'): ?>
            <form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/email') ?>"><?= csrf_field() ?><button class="btn btn-outline-primary"><i class="bi bi-envelope"></i> Email payslips</button></form>
            <form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/unapprove') ?>"><?= csrf_field() ?><button class="btn btn-outline-warning" data-confirm="Undo the approval? The journal is removed and the run becomes a draft.">Undo approval</button></form>
        <?php elseif ($mg && $run['status'] === 'paid'): ?>
            <form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/email') ?>"><?= csrf_field() ?><button class="btn btn-outline-primary"><i class="bi bi-envelope"></i> Email payslips</button></form>
            <form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/unpay') ?>"><?= csrf_field() ?><button class="btn btn-outline-warning" data-confirm="Undo the payment entry?">Undo payment</button></form>
        <?php endif ?>
    </div>
</div>
<div class="row g-2 mb-3">
    <?php foreach (['Staff' => count($lines), 'Gross' => money($run['gross']), 'Deductions' => money($run['deductions']), 'Net pay' => money($run['net']), 'Employer PF + ESI' => money($run['employer_pf'] + $run['employer_esi']), 'Cost to company' => money($run['gross'] + $run['employer_pf'] + $run['employer_esi'])] as $k => $v): ?>
        <div class="col-6 col-md-2"><div class="card card-body py-2"><div class="small text-muted"><?= $k ?></div><div class="fw-semibold"><?= $v ?></div></div></div>
    <?php endforeach ?>
</div>
<?php if ($draft && $noAtt): ?><div class="alert alert-warning py-2 small"><?= $noAtt ?> employee(s) have no attendance for this month: monthly staff are paid for the full month, daily-wage staff for 0 days. <a href="<?= site_url('admin/attendance?month=' . substr($run['month'], 0, 7)) ?>">Fill the attendance</a>.</div><?php endif ?>
<?php if ($run['status'] === 'approved' && $mg): ?>
<form method="post" action="<?= site_url('admin/payroll/' . $run['id'] . '/pay') ?>" class="card card-body mb-3 d-flex flex-row flex-wrap gap-2 align-items-end"><?= csrf_field() ?>
    <div><label class="form-label small mb-0">Paid from</label><select name="account_id" class="form-select" required><?= select_options($accounts, '', '— Bank / cash —') ?></select></div>
    <div><label class="form-label small mb-0">Date</label><input type="date" name="paid_on" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
    <button class="btn btn-primary"><i class="bi bi-bank"></i> Mark salaries paid (<?= money($run['net']) ?>)</button>
    <span class="small text-muted">After the bank transfer (use the <b>Bank transfer sheet</b>).</span>
</form>
<?php endif ?>
<form method="post" action="<?= site_url('admin/payroll/' . $run['id']) ?>"><?= csrf_field() ?>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
    <thead><tr><th>Employee</th><th class="text-end">Paid days</th><th class="text-end">OT hrs</th><th class="text-end">Earned</th><th class="text-end" style="width:100px">Incentive / bonus</th><th class="text-end">Gross</th>
        <th class="text-end">PF</th><th class="text-end">ESI</th><th class="text-end">PT</th><th class="text-end">TDS</th><th class="text-end">Advance</th><th class="text-end" style="width:100px">Other ded.</th><th class="text-end">Net pay</th><th style="width:150px">Remarks</th><th></th></tr></thead><tbody>
    <?php foreach ($lines as $l): $earned = $l['basic'] + $l['hra'] + $l['conveyance'] + $l['special'] + $l['wages'] + $l['ot_amount']; ?>
        <tr>
            <td class="text-nowrap"><a href="<?= site_url('admin/employees/' . $l['employee_id']) ?>" class="fw-semibold"><?= esc($l['name']) ?></a><div class="text-muted"><?= esc($l['emp_code']) ?> · <?= $l['pay_type'] === 'daily' ? 'daily' : 'monthly' ?><?= $l['att_entered'] ? '' : ' · <span class="text-warning-emphasis">no attendance</span>' ?></div></td>
            <td class="text-end" title="P <?= qty($l['present']) ?> · H <?= qty($l['half_days']) ?> · L <?= qty($l['leave_days']) ?> · A <?= qty($l['absent']) ?>"><?= qty($l['paid_days']) ?>/<?= (int) $l['days_in_month'] ?></td>
            <td class="text-end"><?= (float) $l['ot_hours'] ? qty($l['ot_hours']) : '' ?></td>
            <td class="text-end"><?= money($earned, false) ?></td>
            <td class="text-end"><?php if ($draft && $mg): ?><input type="number" step="0.01" min="0" name="l[<?= $l['id'] ?>][other_earnings]" value="<?= (float) $l['other_earnings'] ?: '' ?>" class="form-control form-control-sm text-end"><?php else: ?><?= (float) $l['other_earnings'] ? money($l['other_earnings'], false) : '' ?><?php endif ?></td>
            <td class="text-end"><?= money($l['gross'], false) ?></td>
            <td class="text-end"><?= (float) $l['pf_employee'] ? money($l['pf_employee'], false) : '' ?></td>
            <td class="text-end"><?= (float) $l['esi_employee'] ? money($l['esi_employee'], false) : '' ?></td>
            <td class="text-end"><?= (float) $l['pt'] ? money($l['pt'], false) : '' ?></td>
            <td class="text-end"><?= (float) $l['tds'] ? money($l['tds'], false) : '' ?></td>
            <td class="text-end"><?= (float) $l['advance_recovery'] ? money($l['advance_recovery'], false) : '' ?></td>
            <td class="text-end"><?php if ($draft && $mg): ?><input type="number" step="0.01" min="0" name="l[<?= $l['id'] ?>][other_deductions]" value="<?= (float) $l['other_deductions'] ?: '' ?>" class="form-control form-control-sm text-end"><?php else: ?><?= (float) $l['other_deductions'] ? money($l['other_deductions'], false) : '' ?><?php endif ?></td>
            <td class="text-end fw-semibold"><?= money($l['net'], false) ?></td>
            <td><?php if ($draft && $mg): ?><input name="l[<?= $l['id'] ?>][remarks]" value="<?= esc((string) $l['remarks']) ?>" class="form-control form-control-sm"><?php else: ?><?= esc((string) $l['remarks']) ?><?php endif ?></td>
            <td><a href="<?= site_url('admin/payroll/' . $run['id'] . '/payslip/' . $l['employee_id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0" title="Payslip"><i class="bi bi-file-earmark-pdf"></i></a></td>
        </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="fw-semibold"><tr><td>Total</td><td></td><td></td><td class="text-end"><?= money($sum('basic') + $sum('hra') + $sum('conveyance') + $sum('special') + $sum('wages') + $sum('ot_amount'), false) ?></td><td class="text-end"><?= money($sum('other_earnings'), false) ?></td>
        <td class="text-end"><?= money($sum('gross'), false) ?></td><td class="text-end"><?= money($sum('pf_employee'), false) ?></td><td class="text-end"><?= money($sum('esi_employee'), false) ?></td><td class="text-end"><?= money($sum('pt'), false) ?></td>
        <td class="text-end"><?= money($sum('tds'), false) ?></td><td class="text-end"><?= money($sum('advance_recovery'), false) ?></td><td class="text-end"><?= money($sum('other_deductions'), false) ?></td><td class="text-end"><?= money($sum('net'), false) ?></td><td colspan="2"></td></tr></tfoot>
</table></div></div>
<?php if ($draft && $mg): ?><button class="btn btn-primary mt-3">Save incentives / deductions &amp; recalculate</button><?php endif ?>
</form>
<?php if ($log): ?><div class="card mt-3"><div class="card-header bg-white fw-semibold">History</div><ul class="list-group list-group-flush small">
    <?php foreach ($log as $a): ?><li class="list-group-item"><?= esc($a['description']) ?> <span class="text-muted">· <?= user_name($a['user_id']) ?> · <?= fdate($a['created_at'], true) ?></span></li><?php endforeach ?>
</ul></div><?php endif ?>
<?= $this->endSection() ?>
