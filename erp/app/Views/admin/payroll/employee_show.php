<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= esc($e['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $gross = $e['basic'] + $e['hra'] + $e['conveyance'] + $e['special']; ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <span class="badge text-bg-<?= $e['status'] === 'left' ? 'secondary' : 'success' ?>"><?= $e['status'] === 'left' ? 'Left' : 'Working' ?></span>
    <span class="text-muted"><?= esc($e['emp_code']) ?> · <?= esc((string) $e['designation']) ?><?= $e['department'] ? ' · ' . esc($e['department']) : '' ?></span>
    <?php if (can('payroll.manage')): ?>
        <a href="<?= site_url('admin/employees/' . $e['id'] . '/edit') ?>" class="btn btn-outline-primary ms-auto"><i class="bi bi-pencil-square"></i> Edit</a>
        <form method="post" action="<?= site_url('admin/employees/' . $e['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Delete <?= esc($e['name']) ?>?"><i class="bi bi-trash"></i></button></form>
    <?php endif ?>
</div>
<div class="row g-3">
<div class="col-lg-5">
    <div class="card mb-3"><div class="card-body small">
        <table class="table table-sm mb-0">
            <tr><th style="width:42%">Pay</th><td><?= $e['pay_type'] === 'daily' ? money($e['daily_wage']) . ' per day' : money($gross) . ' per month' ?></td></tr>
            <?php if ($e['pay_type'] === 'monthly'): ?><tr><th>Basic · HRA · Conv. · Special</th><td><?= money($e['basic'], false) ?> · <?= money($e['hra'], false) ?> · <?= money($e['conveyance'], false) ?> · <?= money($e['special'], false) ?></td></tr><?php endif ?>
            <tr><th>Deductions</th><td><?= implode(', ', array_filter([$e['pf_applicable'] ? 'PF' : '', $e['esi_applicable'] ? 'ESI' : '', $e['pt_applicable'] ? 'Professional tax' : '', (float) $e['tds_monthly'] ? 'TDS ' . money($e['tds_monthly']) : ''])) ?: 'None' ?></td></tr>
            <tr><th>Joined</th><td><?= fdate($e['join_date']) ?><?= $e['exit_date'] ? ' · left ' . fdate($e['exit_date']) : '' ?></td></tr>
            <tr><th>Phone · email</th><td><?= esc((string) $e['phone']) ?> <?= esc((string) $e['email']) ?></td></tr>
            <tr><th>Bank</th><td><?= esc(trim($e['bank_name'] . ' ' . $e['bank_account'] . ' ' . $e['ifsc'])) ?: '—' ?></td></tr>
            <tr><th>PAN · UAN · ESI</th><td><?= esc(implode(' · ', array_filter([$e['pan'], $e['uan'], $e['esi_no']]))) ?: '—' ?></td></tr>
            <tr><th>ERP login</th><td><?= $user ? esc($user['username']) : '—' ?></td></tr>
        </table>
    </div></div>
    <div class="card"><div class="card-header fw-semibold">Salary advances</div><div class="card-body small">
        <table class="table table-sm"><thead><tr><th>Date</th><th class="text-end">Given</th><th class="text-end">Per month</th><th class="text-end">Recovered</th><th></th></tr></thead><tbody>
        <?php foreach ($advances as $a): ?><tr><td><?= fdate($a['adv_date']) ?><div class="text-muted"><?= esc($a['voucher_no']) ?></div></td><td class="text-end"><?= money($a['amount'], false) ?></td><td class="text-end"><?= money($a['monthly_recovery'], false) ?></td>
            <td class="text-end"><?= money($a['recovered'], false) ?><?= $a['recovered'] >= $a['amount'] ? ' <span class="badge text-bg-success">done</span>' : '' ?></td>
            <td><?php if (can('payroll.manage') && (float) $a['recovered'] <= 0): ?><form method="post" action="<?= site_url('admin/employees/advances/' . $a['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger py-0" data-confirm="Delete this advance?"><i class="bi bi-trash"></i></button></form><?php endif ?></td></tr><?php endforeach ?>
        <?php if (! $advances): ?><tr><td colspan="5" class="text-muted">None.</td></tr><?php endif ?>
        </tbody></table>
        <?php if (can('payroll.manage') && $e['status'] === 'active'): ?>
        <form method="post" action="<?= site_url('admin/employees/' . $e['id'] . '/advance') ?>" class="row g-2"><?= csrf_field() ?>
            <div class="col-6"><input type="date" name="adv_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-6"><input type="number" step="0.01" min="1" name="amount" class="form-control form-control-sm" placeholder="Amount ₹" required></div>
            <div class="col-6"><input type="number" step="0.01" min="0" name="monthly_recovery" class="form-control form-control-sm" placeholder="Recover per month ₹"></div>
            <div class="col-6"><select name="account_id" class="form-select form-select-sm" required><?= select_options($accounts, '', 'Paid from…') ?></select></div>
            <div class="col-8"><input name="notes" class="form-control form-control-sm" placeholder="Reason"></div>
            <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100">Give advance</button></div>
        </form>
        <?php endif ?>
    </div></div>
</div>
<div class="col-lg-7"><div class="card"><div class="card-header fw-semibold">Payslips</div><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Month</th><th class="text-end">Paid days</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net</th><th></th></tr></thead><tbody>
    <?php foreach ($slips as $s): ?><tr><td><?= date('M Y', strtotime($s['month'])) ?> <?= status_badge($s['status']) ?></td><td class="text-end"><?= qty($s['paid_days']) ?></td><td class="text-end"><?= money($s['gross'], false) ?></td>
        <td class="text-end"><?= money($s['total_deductions'], false) ?></td><td class="text-end fw-semibold"><?= money($s['net'], false) ?></td>
        <td class="text-end"><a href="<?= site_url('admin/payroll/' . $s['run_id'] . '/payslip/' . $s['employee_id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-file-earmark-pdf"></i></a></td></tr><?php endforeach ?>
    <?php if (! $slips): ?><tr><td colspan="6" class="text-muted text-center py-3">No salary runs yet.</td></tr><?php endif ?>
</tbody></table></div></div></div>
</div>
<?= $this->endSection() ?>
