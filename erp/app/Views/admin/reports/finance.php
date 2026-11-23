<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Customers: Cash / Loan<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('admin/reports/_head', ['from' => $from, 'to' => $to, 'extra' => '<form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="' . esc($from) . '"><input type="hidden" name="to" value="' . esc($to) . '"><select name="mode" class="form-select form-select-sm">' . select_options(['cash' => 'Cash', 'loan' => 'Bank loan', 'none' => 'Not decided'], service('request')->getGet('mode'), 'All payment modes') . '</select><button class="btn btn-sm btn-outline-secondary">Filter</button></form>']) ?>
<?php $cnt = ['cash' => 0, 'loan' => 0, '' => 0]; $loanDue = 0; foreach ($rows as $r) { $cnt[$r['finance_mode']] = ($cnt[$r['finance_mode']] ?? 0) + 1; $loanDue += $r['loan_pending']; } ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card card-body stat-card"><div class="small text-muted">Cash customers</div><div class="value"><?= $cnt['cash'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body stat-card"><div class="small text-muted">Loan customers</div><div class="value"><?= $cnt['loan'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body stat-card"><div class="small text-muted">Mode not decided</div><div class="value text-warning"><?= $cnt[''] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body stat-card"><div class="small text-muted">Loan disbursements pending</div><div class="value"><?= money($loanDue) ?></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
    <thead><tr><th>Invoice</th><th>Customer</th><th>Mode</th><th>Bank</th><th class="text-end">Loan</th><th>Disbursed</th><th class="text-end">Invoice</th><th class="text-end">Due</th><th>Project stage</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $due = $r['total'] - $r['amount_paid']; ?>
        <tr><td><a href="<?= site_url('admin/invoices/' . $r['id']) ?>"><?= esc($r['invoice_no']) ?></a><br><small class="text-muted"><?= fdate($r['invoice_date']) ?></small></td>
            <td><?= esc($r['customer']) ?> <?= status_badge($r['customer_type']) ?><br><small class="text-muted"><?= esc(($r['city'] ?? '') . ' ' . ($r['phone'] ?? '')) ?></small></td>
            <td><?= $r['finance_mode'] === 'loan' ? '<span class="badge text-bg-primary">Loan</span>' : ($r['finance_mode'] === 'cash' ? '<span class="badge text-bg-success">Cash</span>' : '<span class="badge text-bg-warning">Not decided</span>') ?></td>
            <td><?= esc((string) $r['loan_bank']) ?><?= $r['loan_ref'] ? '<br><small class="text-muted">' . esc($r['loan_ref']) . '</small>' : '' ?></td>
            <td class="text-end"><?= $r['finance_mode'] === 'loan' ? money($r['loan_amount']) : '' ?></td>
            <td><?= $r['finance_mode'] === 'loan' ? $r['recd_count'] . ' of ' . $r['tranches'] . ' · ' . money($r['loan_recd']) : '' ?></td>
            <td class="text-end"><?= money($r['total']) ?></td><td class="text-end <?= $due > 0 ? 'text-danger' : 'text-success' ?>"><?= money($due) ?></td>
            <td><?= $r['project_no'] ? '<a href="' . site_url('admin/projects/' . $r['project_id']) . '">' . esc(\App\Libraries\Workflow::stageTitle((string) $r['current_stage'])) . '</a>' : '' ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No invoices in this period.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
