<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Invoice <?= esc($inv['invoice_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $due = round($inv['total'] - $inv['amount_paid'], 2); ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center no-print">
    <?= status_badge($inv['status']) ?> <?= status_badge($inv['subsidy_type']) ?>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <div class="btn-group">
            <a href="<?= site_url('admin/invoices/' . $inv['id'] . '/pdf') ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> View PDF</a>
            <a href="<?= site_url('admin/invoices/' . $inv['id'] . '/pdf?download=1') ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i></a>
        </div>
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#emailModal"><i class="bi bi-envelope"></i> Email</button>
        <?php if ($project): ?><a href="<?= site_url('admin/projects/' . $project['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-kanban"></i> Project <?= esc($project['project_no']) ?></a><?php endif ?>
        <?php if (can('invoices.manage') && $inv['status'] !== 'cancelled' && (float) $inv['amount_paid'] == 0): ?>
            <form method="post" action="<?= site_url('admin/invoices/' . $inv['id'] . '/cancel') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Mark this invoice as cancelled?">Mark as Cancelled</button></form>
        <?php endif ?>
        <?php if (can('entries.edit') && $inv['status'] !== 'cancelled'): ?>
            <a href="<?= site_url('admin/invoices/' . $inv['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Edit</a>
        <?php endif ?>
        <?php if (can('entries.delete')): ?>
            <form method="post" action="<?= site_url('admin/invoices/' . $inv['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Delete invoice <?= esc($inv['invoice_no']) ?> permanently? Its payments<?= $project ? ', project ' . esc($project['project_no']) . ' (stages, photos, dispatches – stock goes back to the store)' : '' ?> and accounting entries are deleted too. This cannot be undone."><i class="bi bi-trash"></i> Delete</button></form>
        <?php endif ?>
        <?php if (can('payments.manage') && in_array($inv['status'], ['unpaid', 'partial'], true)): ?>
            <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#payForm"><i class="bi bi-plus-square"></i> Payment</button>
        <?php endif ?>
    </div>
</div>

<?php if (can('payments.manage') && in_array($inv['status'], ['unpaid', 'partial'], true)): ?>
<form method="post" action="<?= site_url('admin/invoices/' . $inv['id'] . '/payments') ?>" class="card card-body mb-3 collapse <?= session('errors') ? 'show' : '' ?>" id="payForm">
    <?= csrf_field() ?>
    <h3 class="h6">Record Payment for <?= esc($inv['invoice_no']) ?> <span class="badge text-bg-light border ms-2">Receipt no: <?= esc(\App\Libraries\Numbering::next('payments', 'receipt_no', setting('Solar.receiptPrefix'))) ?></span></h3>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Amount received *</label><input type="number" step="0.01" min="0.01" max="<?= $due ?>" name="amount" class="form-control" required value="<?= esc(old('amount', $due)) ?>"></div>
        <div class="col-md-3"><label class="form-label">Payment date *</label><input type="date" name="payment_date" class="form-control" required value="<?= esc(old('payment_date', date('Y-m-d'))) ?>"></div>
        <div class="col-md-3"><label class="form-label">Payment mode *</label><select name="mode" id="pay_mode" class="form-select" required><?= select_options(['bank' => 'Bank transfer (NEFT/RTGS/IMPS)', 'upi' => 'UPI', 'cheque' => 'Cheque', 'cash' => 'Cash', 'loan' => 'Loan disbursement from bank'], old('mode', 'bank')) ?></select></div>
        <?php $pendingT = array_filter($tranches, static fn ($t) => $t['status'] === 'pending'); if ($pendingT): ?>
        <div class="col-md-4"><label class="form-label">Loan disbursement</label><select name="loan_tranche_id" id="tranche" class="form-select"><option value="">— Not a loan disbursement —</option>
            <?php foreach ($pendingT as $t): ?><option value="<?= $t['id'] ?>" data-amount="<?= esc($t['amount']) ?>">Disbursement <?= (int) $t['seq'] ?> – <?= money($t['amount']) ?></option><?php endforeach ?></select></div>
        <?php endif ?>
        <div class="col-md-3"><label class="form-label">Received in account *</label><select name="account_id" class="form-select" required><?= select_options($accounts, old('account_id'), '— Bank / cash —') ?></select>
            <?php if (! $accounts): ?><div class="form-text text-danger">Add a bank account first (Accounts → Bank / Cash Accounts).</div><?php endif ?></div>
        <div class="col-md-4"><label class="form-label">Transaction ID / UTR / cheque no.</label><input name="reference" class="form-control" value="<?= esc(old('reference')) ?>"></div>
        <div class="col-md-8"><label class="form-label">Note</label><input name="notes" class="form-control" value="<?= esc(old('notes')) ?>"></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="send_receipt" value="1" id="sr"><label class="form-check-label" for="sr"><i class="bi bi-envelope"></i> Email the payment receipt (PDF) to the customer<?= $customer['email'] ? ' (' . esc($customer['email']) . ')' : ' – <span class="text-danger">no email on file</span>' ?></label></div>
    </div>
    <div class="mt-3"><button class="btn btn-success">Save payment</button></div>
</form>
<?php endif ?>

<?= view('admin/invoices/_finance', ['inv' => $inv, 'tranches' => $tranches, 'financeEditable' => $financeEditable]) ?>

<div class="card"><div class="card-body p-4">
    <div class="small text-muted mb-2 no-print">Format: <strong><?= esc(\App\Libraries\InvoiceFormat::FORMATS[$inv['print_format']] ?? $inv['print_format']) ?></strong></div>
    <div class="row">
        <div class="col-sm-6">
            <h2 class="h4 text-primary"><?= esc($inv['invoice_no']) ?></h2>
            <strong><?= esc(setting('Solar.companyName')) ?></strong><br>
            <span class="small"><?= nl2br(esc((string) setting('Solar.companyAddress'))) ?><br><?= esc((string) setting('Solar.companyPhone')) ?>
            <?= setting('Solar.companyGstin') ? '<br>GSTIN: ' . esc(setting('Solar.companyGstin')) : '' ?></span>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <span class="text-muted small">Bill To</span><br>
            <a href="<?= site_url('admin/customers/' . $customer['id']) ?>" class="fw-semibold"><?= esc($customer['name']) ?></a> <?= status_badge($customer['type']) ?><br>
            <span class="small"><?= nl2br(esc((string) $customer['address'])) ?><br><?= esc(trim(($customer['city'] ?? '') . ' ' . ($customer['state'] ?? '') . ' ' . ($customer['pincode'] ?? ''))) ?>
            <?= $customer['gstin'] ? '<br>GSTIN: ' . esc($customer['gstin']) : '' ?></span>
            <div class="mt-3 small">Invoice date: <strong><?= fdate($inv['invoice_date']) ?></strong><br>Due date: <strong><?= fdate($inv['due_date']) ?></strong><br>System: <strong><?= qty($inv['capacity_kw']) ?> kW</strong></div>
        </div>
    </div>
    <div class="table-responsive mt-4">
    <table class="table">
        <thead><tr><th>#</th><th>Item</th><th>HSN</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php foreach (\App\Libraries\InvoiceFormat::lines($inv, $lines, $type) as $n => $l): ?>
            <tr><td><?= $n + 1 ?></td><td><?= esc($l['description']) ?></td><td><?= esc($l['hsn'] ?? '') ?></td><td class="text-end"><?= qty($l['qty']) ?></td>
                <td class="text-end"><?= money($l['rate'], false) ?></td><td class="text-end"><?= esc($l['tax_label']) ?></td><td class="text-end"><?= money($l['amount'], false) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?= view('admin/invoices/_gst_summary', ['inv' => $inv, 'lines' => $lines]) ?>
    <div class="row justify-content-end"><div class="col-md-5">
        <table class="table table-sm">
            <tr><td class="text-end">Sub Total</td><td class="text-end"><?= money($inv['subtotal']) ?></td></tr>
            <?php if ($inv['gst_type'] === 'inter'): ?>
                <tr><td class="text-end">IGST</td><td class="text-end"><?= money($inv['tax_total']) ?></td></tr>
            <?php else: ?>
                <tr><td class="text-end">CGST</td><td class="text-end"><?= money(round($inv['tax_total'] / 2, 2)) ?></td></tr>
                <tr><td class="text-end">SGST</td><td class="text-end"><?= money($inv['tax_total'] - round($inv['tax_total'] / 2, 2)) ?></td></tr>
            <?php endif ?>
            <tr class="fw-bold"><td class="text-end">Total</td><td class="text-end"><?= money($inv['total']) ?></td></tr>
            <tr><td class="text-end">Paid</td><td class="text-end"><?= money($inv['amount_paid']) ?></td></tr>
            <tr class="<?= $due > 0 ? 'text-danger' : 'text-success' ?> fw-semibold"><td class="text-end">Amount Due</td><td class="text-end"><?= money($due) ?></td></tr>
        </table>
    </div></div>
    <?php if ($inv['notes']): ?><p class="small text-muted mb-0"><?= nl2br(esc($inv['notes'])) ?></p><?php endif ?>
</div></div>

<div class="mt-3"><?= \App\Controllers\Admin\Documents::panel((int) $customer['id'], $project ? (int) $project['id'] : null) ?></div>

<div class="row g-3 mt-1">
<div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">Payments</div>
    <table class="table table-sm mb-0"><thead><tr><th>Receipt</th><th>Date</th><th>Mode</th><th>Account</th><th>Reference</th><th class="text-end">Amount</th><?= can('entries.edit') || can('entries.delete') ? '<th></th>' : '' ?></tr></thead><tbody>
    <?php foreach ($payments as $p): ?><tr><td><a href="<?= site_url('admin/vouchers/print/payment/' . $p['id']) ?>" target="_blank"><?= esc($p['receipt_no']) ?></a></td><td><?= fdate($p['payment_date']) ?></td><td><?= esc(strtoupper($p['mode'])) ?></td><td><?= esc($p['account'] ?? '') ?></td><td><?= esc($p['reference'] ?? '') ?></td><td class="text-end"><?= money($p['amount']) ?></td>
        <?php if (can('entries.edit') || can('entries.delete')): ?><td class="text-end text-nowrap">
            <?php if (can('entries.edit')): ?><a href="<?= site_url('admin/payments/' . $p['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary py-0" title="Edit payment"><i class="bi bi-pencil"></i></a><?php endif ?>
            <?php if (can('entries.delete')): ?><form method="post" action="<?= site_url('admin/payments/' . $p['id'] . '/delete') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger py-0" title="Delete payment" data-confirm="Delete receipt <?= esc($p['receipt_no']) ?> of <?= money($p['amount']) ?>? The invoice's paid / due and the accounts are updated."><i class="bi bi-trash"></i></button></form><?php endif ?>
        </td><?php endif ?></tr><?php endforeach ?>
    <?php if (! $payments): ?><tr><td colspan="6" class="text-muted">No payments yet.</td></tr><?php endif ?>
    </tbody></table></div></div>
<div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Activity log</div>
    <ul class="list-group list-group-flush small">
    <?php foreach ($log as $a): ?><li class="list-group-item"><?= esc($a['description']) ?><br><span class="text-muted"><?= user_name($a['user_id']) ?> · <?= fdate($a['created_at'], true) ?></span></li><?php endforeach ?>
    <?php if (! $log): ?><li class="list-group-item text-muted">Nothing yet.</li><?php endif ?>
    </ul></div></div>
</div>

<div class="modal fade" id="emailModal" tabindex="-1"><div class="modal-dialog"><form method="post" action="<?= site_url('admin/invoices/' . $inv['id'] . '/email') ?>" class="modal-content">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Send invoice to customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">Email to</label><input type="email" name="to" class="form-control" value="<?= esc($customer['email'] ?? '') ?>" required>
        <div class="form-text">The invoice PDF is attached.</div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Send</button></div>
</form></div></div>
<?= $this->endSection() ?>
