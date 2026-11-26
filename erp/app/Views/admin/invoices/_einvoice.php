<?php
/** GST e-invoice box on the invoice page (B2B only). @var array $inv @var array $customer */
if (! \App\Libraries\Features::on('invoices.einvoice') || trim((string) $customer['gstin']) === '' || ! array_key_exists('irn', $inv)) {
    return;
}
$errors = $inv['irn'] ? [] : \App\Libraries\EInvoice::build((int) $inv['id'])[1];
?>
<div class="card mb-3 no-print <?= $inv['irn'] ? 'border-success' : '' ?>"><div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <strong><i class="bi bi-qr-code"></i> GST e-invoice</strong>
        <?php if ($inv['irn']): ?>
            <span class="badge text-bg-success">IRN received</span>
            <span class="small text-muted text-break">IRN <?= esc($inv['irn']) ?> · Ack <?= esc((string) $inv['irn_ack_no']) ?> · <?= fdate($inv['irn_ack_date'], true) ?><?= $inv['irn_qr'] ? ' · QR on PDF' : ' · no QR code saved' ?></span>
        <?php elseif ($inv['status'] === 'cancelled'): ?>
            <span class="small text-muted">Cancelled invoice: no e-invoice needed.</span>
        <?php elseif ($errors): ?>
            <span class="badge text-bg-danger">Fix before upload</span>
        <?php else: ?>
            <span class="badge text-bg-primary">Ready to upload</span>
            <a href="<?= site_url('admin/einvoice/' . $inv['id'] . '/json') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-filetype-json"></i> Download JSON</a>
            <a href="https://einvoice1.gst.gov.in/" target="_blank" rel="noopener" class="small">Open portal</a>
        <?php endif ?>
        <?php if (can('invoices.manage') && $inv['status'] !== 'cancelled'): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="collapse" data-bs-target="#irnForm"><?= $inv['irn'] ? 'Change' : 'Enter IRN from portal' ?></button>
        <?php endif ?>
    </div>
    <?php if ($errors && $inv['status'] !== 'cancelled'): ?><ul class="small text-danger mb-1 mt-2"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul><?php endif ?>
    <?php if ($inv['irn']): ?><div class="small text-warning-emphasis mt-1">After the IRN, edit this invoice only if you also cancel the IRN on the portal (allowed within 24 hours) and upload again; otherwise issue a credit / debit note.</div><?php endif ?>
    <?php if (can('invoices.manage')): ?>
    <form method="post" action="<?= site_url('admin/einvoice/' . $inv['id'] . '/irn') ?>" class="collapse mt-2" id="irnForm"><?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-6"><label class="form-label small">IRN (64 characters) *</label><input name="irn" class="form-control form-control-sm font-monospace" maxlength="70" required value="<?= esc((string) $inv['irn']) ?>"></div>
            <div class="col-md-3"><label class="form-label small">Ack No.</label><input name="irn_ack_no" class="form-control form-control-sm" value="<?= esc((string) $inv['irn_ack_no']) ?>"></div>
            <div class="col-md-3"><label class="form-label small">Ack Date &amp; time</label><input type="datetime-local" name="irn_ack_date" class="form-control form-control-sm" value="<?= $inv['irn_ack_date'] ? date('Y-m-d\TH:i', strtotime($inv['irn_ack_date'])) : '' ?>"></div>
            <div class="col-12"><label class="form-label small">Signed QR code (the long text from the portal; it prints as the QR code on the invoice)</label><textarea name="irn_qr" rows="2" class="form-control form-control-sm font-monospace"><?= esc((string) $inv['irn_qr']) ?></textarea></div>
        </div>
        <div class="mt-2 d-flex gap-2"><button class="btn btn-sm btn-primary">Save e-invoice details</button>
            <?php if ($inv['irn']): ?><button name="clear" value="1" class="btn btn-sm btn-outline-danger" formnovalidate data-confirm="Remove the IRN from this invoice (e.g. it was cancelled on the portal)?">Remove IRN</button><?php endif ?></div>
    </form>
    <?php endif ?>
</div></div>
