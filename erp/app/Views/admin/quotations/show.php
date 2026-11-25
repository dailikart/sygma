<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Quotation <?= esc($q['quote_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $st = \App\Controllers\Admin\Quotations::STATUSES; $badge = ['draft' => 'secondary', 'sent' => 'info', 'accepted' => 'primary', 'rejected' => 'danger', 'converted' => 'success'];
$manage = can('quotations.manage'); $open = $q['status'] !== 'converted'; ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <span class="badge fs-6 text-bg-<?= $badge[$q['status']] ?? 'light' ?>"><?= esc($st[$q['status']] ?? $q['status']) ?></span>
    <?php if ($q['viewed_at']): ?><span class="small text-success"><i class="bi bi-eye"></i> Customer opened the link <?= fdate($q['viewed_at'], true) ?></span><?php endif ?>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <div class="btn-group"><a href="<?= site_url('admin/quotations/' . $q['id'] . '/pdf') ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> View PDF</a>
            <a href="<?= site_url('admin/quotations/' . $q['id'] . '/pdf?download=1') ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i></a></div>
        <?php if ($manage): ?>
            <a href="<?= site_url('admin/quotations/' . $q['id'] . '/whatsapp') ?>" target="_blank" class="btn btn-outline-success<?= $q['party_phone'] ? '' : ' disabled' ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            <button class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#emailForm"><i class="bi bi-envelope"></i> Email</button>
            <?php if ($open): ?><a href="<?= site_url('admin/quotations/' . $q['id'] . '/edit') ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Edit</a><?php endif ?>
            <?php if ($open && can('invoices.manage')): ?><form method="post" action="<?= site_url('admin/quotations/' . $q['id'] . '/convert') ?>"><?= csrf_field() ?><button class="btn btn-success" data-confirm="Convert to an invoice?<?= $q['customer_id'] ? '' : ' A customer is created from these details first.' ?>"><i class="bi bi-receipt"></i> Convert to invoice</button></form><?php endif ?>
        <?php endif ?>
        <?php if ($invoice): ?><a href="<?= site_url('admin/invoices/' . $invoice['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-receipt"></i> <?= esc($invoice['invoice_no']) ?> <?= status_badge($invoice['status']) ?></a><?php endif ?>
        <?php if (can('quotations.delete') && $q['status'] !== 'converted'): ?><form method="post" action="<?= site_url('admin/quotations/' . $q['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Delete quotation <?= esc($q['quote_no']) ?>?"><i class="bi bi-trash"></i></button></form><?php endif ?>
    </div>
</div>
<?php if ($manage): ?>
<form method="post" action="<?= site_url('admin/quotations/' . $q['id'] . '/email') ?>" class="card card-body mb-3 collapse" id="emailForm"><?= csrf_field() ?>
    <div class="d-flex gap-2"><input type="email" name="to" class="form-control" placeholder="customer@example.com" value="<?= esc((string) $q['party_email']) ?>" required><button class="btn btn-primary text-nowrap"><i class="bi bi-send"></i> Send PDF</button></div>
</form>
<?php if ($open): ?>
<form method="post" action="<?= site_url('admin/quotations/' . $q['id'] . '/status') ?>" class="d-flex flex-wrap gap-2 mb-3 align-items-center small"><?= csrf_field() ?>
    <span class="text-muted">Set status:</span>
    <?php foreach (['sent' => 'outline-info', 'accepted' => 'outline-primary', 'rejected' => 'outline-danger', 'draft' => 'outline-secondary'] as $s => $cls): if ($s === $q['status']) continue; ?>
        <button name="status" value="<?= $s ?>" class="btn btn-sm btn-<?= $cls ?>"><?= esc($st[$s]) ?></button>
    <?php endforeach ?>
    <span class="text-muted ms-2">Share link: <a href="<?= esc(\App\Controllers\Admin\Quotations::shareUrl($q)) ?>" target="_blank"><?= esc(\App\Controllers\Admin\Quotations::shareUrl($q)) ?></a></span>
</form>
<?php endif ?>
<?php endif ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-body">
            <div class="row mb-3 small">
                <div class="col-sm-6"><span class="text-muted">For</span><br><strong><?= esc($q['party_name']) ?></strong> <?= status_badge($q['customer_type']) ?>
                    <?= $customer ? '<a href="' . site_url('admin/customers/' . $customer['id']) . '" class="ms-1">customer</a>' : ($lead ? '<a href="' . site_url('admin/leads/' . $lead['id']) . '" class="ms-1">lead</a>' : '<span class="badge text-bg-light border">New enquiry</span>') ?>
                    <br><?= esc((string) $q['party_phone']) ?> <?= esc((string) $q['party_email']) ?><br><?= esc(trim(($q['party_address'] ?? '') . ' ' . ($q['party_city'] ?? '') . ' ' . ($q['party_state'] ?? ''))) ?></div>
                <div class="col-sm-6 text-sm-end">Date: <?= fdate($q['quote_date']) ?><br>Valid until: <?= fdate($q['valid_until']) ?><br>
                    System: <strong><?= qty($q['capacity_kw']) ?> kW</strong> <?= esc($type) ?><?= $kit ? '<br>Kit: ' . esc($kit['name']) : '' ?><br><?= status_badge($q['subsidy_type']) ?></div>
            </div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>#</th><th>Description</th><th>HSN</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">GST</th><th class="text-end">Amount</th></tr></thead><tbody>
                <?php foreach (\App\Libraries\InvoiceFormat::lines($q, $lines, $type) as $n => $l): ?><tr><td><?= $n + 1 ?></td><td><?= esc($l['description']) ?></td><td><?= esc((string) ($l['hsn'] ?? '')) ?></td><td class="text-end"><?= qty($l['qty']) ?></td><td class="text-end"><?= money($l['rate'], false) ?></td><td class="text-end"><?= esc($l['tax_label']) ?></td><td class="text-end"><?= money($l['amount'], false) ?></td></tr><?php endforeach ?>
                </tbody></table></div>
            <div class="row justify-content-end"><div class="col-md-6"><table class="table table-sm mb-0">
                <tr><td>Sub total</td><td class="text-end"><?= money($q['subtotal']) ?></td></tr>
                <tr><td>GST</td><td class="text-end"><?= money($q['tax_total']) ?></td></tr>
                <tr class="fw-bold"><td>Total</td><td class="text-end"><?= money($q['total']) ?></td></tr>
                <?php if ($roi['extra'] > 0): ?><tr><td>EB / meter charges (paid separately)</td><td class="text-end"><?= money($roi['extra']) ?></td></tr><?php endif ?>
                <?php if ((float) $q['subsidy_amount'] > 0): ?><tr class="text-success"><td>Less: expected subsidy</td><td class="text-end">− <?= money($q['subsidy_amount']) ?></td></tr><?php endif ?>
                <tr class="fw-bold"><td>Net cost to customer</td><td class="text-end"><?= money($roi['net_cost']) ?></td></tr>
            </table></div></div>
            <?php if ($q['notes']): ?><div class="small text-muted mt-3"><?= nl2br(esc($q['notes'])) ?></div><?php endif ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header bg-white fw-semibold"><i class="bi bi-sun"></i> Savings</div>
            <table class="table table-sm mb-0">
                <tr><td>Generation per year</td><td class="text-end"><?= number_format($roi['units_year']) ?> units</td></tr>
                <tr><td>Savings per year</td><td class="text-end"><?= money($roi['savings_year']) ?></td></tr>
                <tr><td>Payback</td><td class="text-end fw-bold"><?= $roi['payback_years'] !== null ? $roi['payback_years'] . ' years' : '—' ?></td></tr>
                <tr><td>Savings in 25 years</td><td class="text-end fw-bold text-success"><?= money($roi['savings_25']) ?></td></tr>
            </table>
            <div class="card-body small text-muted py-2">Based on <?= qty($q['units_per_kw_day']) ?> units/kW/day at ₹<?= qty($q['tariff']) ?>/unit.</div></div>
        <?php if (! empty($q['roof_design_id']) && can('designs.view')): ?><a class="btn btn-sm btn-outline-primary w-100 mt-3" href="<?= site_url('admin/designs/' . $q['roof_design_id']) ?>"><i class="bi bi-badge-3d"></i> Open the 3D roof design</a><?php endif ?>
        <?php if ($q['design_path']): ?><div class="card mt-3"><div class="card-header bg-white fw-semibold">Design</div><div class="card-body text-center">
            <?php if (preg_match('/\.(jpe?g|png|webp)$/i', $q['design_path'])): ?><a href="<?= site_url('admin/quotations/' . $q['id'] . '/design') ?>" target="_blank"><img src="<?= site_url('admin/quotations/' . $q['id'] . '/design') ?>" class="img-fluid rounded" alt="Design"></a>
            <?php else: ?><a href="<?= site_url('admin/quotations/' . $q['id'] . '/design') ?>" target="_blank"><i class="bi bi-file-earmark-pdf fs-1"></i><br>Open design</a><?php endif ?></div></div><?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
