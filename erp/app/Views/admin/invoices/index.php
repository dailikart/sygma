<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Invoices<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
    <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <form method="get" class="d-flex gap-2">
        <input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" class="form-control form-control-sm" placeholder="Invoice no / customer">
        <select name="status" class="form-select form-select-sm"><?= select_options(['unpaid' => 'Unpaid / partial', 'paid' => 'Paid', 'cancelled' => 'Cancelled'], service('request')->getGet('status'), 'All statuses') ?></select>
        <select name="subsidy" class="form-select form-select-sm"><?= select_options(['subsidy' => 'Subsidy', 'non_subsidy' => 'Non-subsidy'], service('request')->getGet('subsidy'), 'Subsidy: all') ?></select>
        <select name="finance" class="form-select form-select-sm"><?= select_options(['cash' => 'Cash', 'loan' => 'Bank loan', 'none' => 'Not decided'], service('request')->getGet('finance'), 'Payment mode: all') ?></select>
        <select name="type" class="form-select form-select-sm"><?= select_options(['b2c' => 'B2C', 'b2b' => 'B2B'], service('request')->getGet('type'), 'B2B / B2C') ?></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <?php if (can('invoices.manage')): ?><a href="<?= site_url('admin/invoices/new') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Create New Invoice</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover table-sm table-bordered table-grid mb-0 align-middle small">
    <thead><tr><th>Invoice #</th><th>Date</th><th>Customer</th><th class="text-center lh-sm">EB<br>Payment</th><th class="text-center lh-sm">EB<br>Submission</th><th>Type / kW</th><th class="text-end">Amount</th><th class="text-end lh-sm">Meter<br>charges</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Status</th></tr></thead>
    <tbody>
    <?php $yn = static fn ($e, $k) => $e === null ? '<span class="text-muted">—</span>' : ($e[$k] ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-light border">No</span>');
    $sum = 0; $meter = 0; $paid = 0; $due = 0; foreach ($rows as $r): if ($r['status'] !== 'cancelled') { $sum += $r['total']; $meter += eb_charges_extra($r); $paid += $r['amount_paid']; $due += $r['total'] - $r['amount_paid']; } $e = $eb[$r['id']] ?? null; ?>
        <tr>
            <td class="text-nowrap"><a href="<?= site_url('admin/invoices/' . $r['id']) ?>"><?= esc($r['invoice_no']) ?></a><div class="small text-muted">by <?= esc(user_name($r['created_by'])) ?></div></td>
            <td class="text-nowrap"><?= fdate($r['invoice_date']) ?></td>
            <td><?= esc($r['customer']) ?></td>
            <td class="text-center"><?= $yn($e, 'payment') ?></td>
            <td class="text-center"><?= $yn($e, 'submission') ?></td>
            <td><?= status_badge($r['customer_type']) ?> <?= status_badge($r['subsidy_type']) ?> <span class="text-nowrap fw-semibold"><?= qty($r['capacity_kw']) ?> kW</span> <?= $r['finance_mode'] === 'loan' ? '<span class="badge text-bg-primary">Loan</span>' : ($r['finance_mode'] === 'cash' ? '<span class="badge text-bg-light border">Cash</span>' : '<span class="badge text-bg-warning">Mode?</span>') ?></td>
            <td class="text-end text-nowrap"><?= money($r['total']) ?><?php if (eb_charges_extra($r) > 0 && $r['status'] !== 'cancelled'): ?><div class="text-muted" title="Total receivable = invoice + meter charges">Total <?= money($r['total'] + eb_charges_extra($r)) ?></div><?php endif ?></td>
            <td class="text-end text-nowrap"><?= $r['status'] === 'cancelled' ? '—' : eb_charges_label($r) ?></td>
            <td class="text-end text-nowrap"><?= money($r['amount_paid']) ?></td>
            <td class="text-end text-nowrap"><?= $r['status'] === 'cancelled' ? '—' : money($r['total'] - $r['amount_paid']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="11" class="text-center text-muted py-4">No invoices in this period.</td></tr><?php endif ?>
    </tbody>
    <tfoot><tr class="fw-semibold"><td colspan="6" class="text-end">Total (excluding cancelled)</td><td class="text-end"><?= money($sum) ?><?php if ($meter > 0): ?><div class="fw-normal text-muted">Total <?= money($sum + $meter) ?></div><?php endif ?></td><td class="text-end"><?= money($meter) ?></td><td class="text-end"><?= money($paid) ?></td><td class="text-end"><?= money($due) ?></td><td></td></tr></tfoot>
</table>
</div></div>
<?= $this->endSection() ?>
