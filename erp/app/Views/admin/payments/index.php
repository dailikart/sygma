<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Payments Received<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="mb-3 d-flex flex-wrap gap-2 align-items-end"><?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
        <select name="mode" class="form-select form-select-sm"><?= select_options(['bank' => 'Bank transfer', 'upi' => 'UPI', 'cheque' => 'Cheque', 'cash' => 'Cash', 'loan' => 'Loan disbursement'], service('request')->getGet('mode'), 'All payment modes') ?></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button></form></div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Receipt</th><th>Date</th><th>Invoice</th><th>Customer</th><th>Mode</th><th>Account</th><th>Reference</th><th>By</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    <?php $sum = 0; foreach ($rows as $r): $sum += $r['amount']; ?>
        <tr><td><a href="<?= site_url('admin/vouchers/print/payment/' . $r['id']) ?>" target="_blank"><?= esc($r['receipt_no']) ?></a></td><td><?= fdate($r['payment_date']) ?></td>
            <td><a href="<?= site_url('admin/invoices/' . $r['invoice_id']) ?>"><?= esc($r['invoice_no']) ?></a></td><td><?= esc($r['customer']) ?></td>
            <td><?= esc(strtoupper($r['mode'])) ?></td><td><?= esc($r['account'] ?? '') ?></td><td><?= esc($r['reference'] ?? '') ?></td><td class="small text-muted"><?= esc(user_name($r['created_by'])) ?></td><td class="text-end"><?= money($r['amount']) ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No payments in this period.</td></tr><?php endif ?>
    </tbody>
    <tfoot><tr class="fw-semibold"><td colspan="8" class="text-end">Total</td><td class="text-end"><?= money($sum) ?></td></tr></tfoot>
</table>
</div></div>
<?= $this->endSection() ?>
