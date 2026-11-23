<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Edit Payment <?= esc($pay['receipt_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $max = round((float) $inv['total'] - (float) $inv['amount_paid'] + (float) $pay['amount'], 2); ?>
<div class="mb-3"><a href="<?= site_url('admin/invoices/' . $inv['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= esc($inv['invoice_no']) ?></a></div>
<form method="post" action="<?= site_url('admin/payments/' . $pay['id'] . '/update') ?>" class="card card-body" style="max-width:860px">
    <?= csrf_field() ?>
    <div class="alert alert-warning small"><i class="bi bi-pencil-square"></i> Editing receipt <strong><?= esc($pay['receipt_no']) ?></strong> on invoice <?= esc($inv['invoice_no']) ?>. The receipt number stays the same; the accounting entry and the invoice's paid / due are updated.</div>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Amount *</label><input type="number" step="0.01" min="0.01" max="<?= $max ?>" name="amount" class="form-control" required value="<?= esc(old('amount', $pay['amount'])) ?>"><div class="form-text">Up to <?= money($max) ?></div></div>
        <div class="col-md-3"><label class="form-label">Payment date *</label><input type="date" name="payment_date" class="form-control" required value="<?= esc(old('payment_date', $pay['payment_date'])) ?>"></div>
        <div class="col-md-3"><label class="form-label">Payment mode *</label><select name="mode" class="form-select" required><?= select_options(['bank' => 'Bank transfer (NEFT/RTGS/IMPS)', 'upi' => 'UPI', 'cheque' => 'Cheque', 'cash' => 'Cash', 'loan' => 'Loan disbursement from bank'], old('mode', $pay['mode'])) ?></select></div>
        <div class="col-md-3"><label class="form-label">Received in account *</label><select name="account_id" class="form-select" required><?= select_options($accounts, old('account_id', $pay['account_id']), '— Bank / cash —') ?></select></div>
        <div class="col-md-4"><label class="form-label">Transaction ID / UTR / cheque no.</label><input name="reference" class="form-control" value="<?= esc((string) old('reference', $pay['reference'])) ?>"></div>
        <div class="col-md-8"><label class="form-label">Note</label><input name="notes" class="form-control" value="<?= esc((string) old('notes', $pay['notes'])) ?>"></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save changes</button> <a href="<?= site_url('admin/invoices/' . $inv['id']) ?>" class="btn btn-light">Cancel</a></div>
</form>
<?= $this->endSection() ?>
