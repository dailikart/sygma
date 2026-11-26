<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>GST e-Invoice<?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <label class="small text-muted">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control form-control-sm" style="max-width:160px">
    <label class="small text-muted">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control form-control-sm" style="max-width:160px">
    <select name="show" class="form-select form-select-sm" style="max-width:220px">
        <option value="">Waiting for IRN</option><option value="all" <?= $show === 'all' ? 'selected' : '' ?>>All B2B invoices</option>
    </select>
    <button class="btn btn-sm btn-outline-secondary">Show</button>
    <a href="https://einvoice1.gst.gov.in/" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary ms-auto"><i class="bi bi-box-arrow-up-right"></i> Open e-invoice portal</a>
</form>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">Ready to upload</div><div class="fw-semibold text-success"><?= $counts['ready'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">Need correction</div><div class="fw-semibold <?= $counts['problem'] ? 'text-danger' : '' ?>"><?= $counts['problem'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">IRN received</div><div class="fw-semibold"><?= $counts['done'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-muted">Period</div><div class="fw-semibold small"><?= fdate($from) ?> – <?= fdate($to) ?></div></div></div>
</div>
<?php if ($sellerErrors): ?>
<div class="alert alert-warning"><strong>Complete your company details first</strong> (<a href="<?= site_url('admin/settings') ?>">Settings → Company</a>):<ul class="mb-0"><?php foreach ($sellerErrors as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
<?php endif ?>
<form method="post" action="<?= site_url('admin/einvoice/download') ?>"><?= csrf_field() ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
    <thead><tr><th style="width:32px"><input type="checkbox" class="form-check-input" id="all" title="Tick all ready"></th><th>Invoice</th><th>Date</th><th>Customer</th><th>GSTIN</th><th class="text-end">Total</th><th>E-invoice</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $ready = ! $r['irn'] && ! $r['errors']; ?>
        <tr>
            <td><?php if ($ready): ?><input type="checkbox" class="form-check-input pick" name="ids[]" value="<?= $r['id'] ?>"><?php endif ?></td>
            <td><a href="<?= site_url('admin/invoices/' . $r['id']) ?>" class="fw-semibold"><?= esc($r['invoice_no']) ?></a></td>
            <td class="text-nowrap"><?= fdate($r['invoice_date']) ?></td>
            <td><?= esc($r['company'] ?: $r['name']) ?></td>
            <td><small><?= esc((string) $r['gstin']) ?></small></td>
            <td class="text-end"><?= money($r['total']) ?></td>
            <td><?php if ($r['irn']): ?><span class="badge text-bg-success">IRN received</span> <small class="text-muted">Ack <?= esc((string) $r['irn_ack_no']) ?></small>
                <?php elseif ($ready): ?><span class="badge text-bg-primary">Ready</span> <a href="<?= site_url('admin/einvoice/' . $r['id'] . '/json') ?>" class="small">JSON</a>
                <?php else: ?><span class="badge text-bg-danger">Fix first</span><ul class="small text-danger mb-0 ps-3"><?php foreach ($r['errors'] as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No B2B invoices <?= $show === 'all' ? '' : 'waiting for an IRN ' ?>in this period. (E-invoice is only for customers with a GSTIN.)</td></tr><?php endif ?>
</tbody></table></div></div>
<?php if ($counts['ready']): ?><button class="btn btn-primary mt-3"><i class="bi bi-filetype-json"></i> Download JSON for upload</button><?php endif ?>
</form>
<div class="card card-body mt-3 small">
    <strong>How to upload</strong>
    <ol class="mb-0">
        <li>Tick the <span class="badge text-bg-primary">Ready</span> invoices and click <b>Download JSON for upload</b> (one file for all of them).</li>
        <li>Log in to <b>einvoice1.gst.gov.in</b> → <b>e-Invoice → Bulk Upload</b> → choose the file → <b>Upload</b>.</li>
        <li>The portal returns an <b>IRN</b>, <b>Ack No.</b> and <b>Ack Date</b> (and signed QR code) for each invoice. Open the invoice here and enter them under <b>GST e-invoice</b>: they print on the invoice with the QR code.</li>
    </ol>
    <span class="text-muted mt-1">An IRN can be cancelled on the portal only within 24 hours. After the IRN, change the invoice only through a credit / debit note.</span>
</div>
<script>
document.getElementById('all')?.addEventListener('change', e => document.querySelectorAll('.pick').forEach(c => c.checked = e.target.checked));
</script>
<?= $this->endSection() ?>
