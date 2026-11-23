<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= esc($c['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php if (can('customers.manage')): ?><a href="<?= site_url('admin/customers/' . $c['id'] . '/edit') ?>" class="btn btn-outline-primary">Edit</a><?php endif ?>
    <?php if (can('invoices.manage')): ?><a href="<?= site_url('admin/invoices/new?customer=' . $c['id']) ?>" class="btn btn-primary"><i class="bi bi-receipt"></i> Create Invoice</a><?php endif ?>
    <?php if (can('entries.delete')): ?><form method="post" action="<?= site_url('admin/customers/' . $c['id'] . '/delete') ?>" class="ms-auto"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Delete customer <?= esc($c['name']) ?>? Only possible when they have no invoices."><i class="bi bi-trash"></i> Delete customer</button></form><?php endif ?>
</div>
<div class="row g-3">
<div class="col-lg-4"><div class="card card-body">
    <h2 class="h5"><?= esc($c['name']) ?> <?= status_badge($c['type']) ?></h2>
    <?php if ($c['company']): ?><div><?= esc($c['company']) ?></div><?php endif ?>
    <div><i class="bi bi-telephone"></i> <?= esc($c['phone'] ?? '') ?></div>
    <div><i class="bi bi-envelope"></i> <?= esc($c['email'] ?? '—') ?></div>
    <div class="mt-2 small"><?= nl2br(esc($c['address'] ?? '')) ?><br><?= esc(trim(($c['city'] ?? '') . ' ' . ($c['state'] ?? '') . ' ' . ($c['pincode'] ?? ''))) ?></div>
    <table class="table table-sm mt-2 mb-0"><tr><th>GSTIN</th><td><?= esc($c['gstin'] ?? '—') ?></td></tr><tr><th>EB service no.</th><td><?= esc($c['eb_service_no'] ?? '—') ?></td></tr></table>
</div></div>
<div class="col-lg-8">
    <?= \App\Controllers\Admin\Documents::panel((int) $c['id']) ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Projects</div><table class="table table-sm mb-0">
        <thead><tr><th>Project</th><th>kW</th><th>Type</th><th>Stage</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($projects as $p): ?><tr><td><a href="<?= site_url('admin/projects/' . $p['id']) ?>"><?= esc($p['project_no']) ?></a></td><td><?= qty($p['capacity_kw']) ?></td><td><?= status_badge($p['subsidy_type']) ?></td>
            <td><?= esc(\App\Libraries\Workflow::stageTitle((string) $p['current_stage'])) ?></td><td><?= status_badge($p['status']) ?></td></tr><?php endforeach ?>
        <?php if (! $projects): ?><tr><td colspan="5" class="text-muted">No projects yet.</td></tr><?php endif ?>
    </tbody></table></div>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Invoices</div><table class="table table-sm mb-0">
        <thead><tr><th>Invoice</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($invoices as $i): ?><tr><td><a href="<?= site_url('admin/invoices/' . $i['id']) ?>"><?= esc($i['invoice_no']) ?></a></td><td><?= fdate($i['invoice_date']) ?></td>
            <td class="text-end"><?= money($i['total']) ?></td><td class="text-end"><?= money($i['amount_paid']) ?></td><td><?= status_badge($i['status']) ?></td></tr><?php endforeach ?>
        <?php if (! $invoices): ?><tr><td colspan="5" class="text-muted">No invoices yet.</td></tr><?php endif ?>
    </tbody></table></div>
    <div class="card"><div class="card-header bg-white fw-semibold">Payments</div><table class="table table-sm mb-0">
        <thead><tr><th>Receipt</th><th>Date</th><th>Mode</th><th class="text-end">Amount</th></tr></thead><tbody>
        <?php foreach ($payments as $p): ?><tr><td><a href="<?= site_url('admin/vouchers/print/payment/' . $p['id']) ?>" target="_blank"><?= esc($p['receipt_no']) ?></a></td><td><?= fdate($p['payment_date']) ?></td><td><?= esc(strtoupper($p['mode'])) ?></td><td class="text-end"><?= money($p['amount']) ?></td></tr><?php endforeach ?>
        <?php if (! $payments): ?><tr><td colspan="4" class="text-muted">No payments yet.</td></tr><?php endif ?>
    </tbody></table></div>
</div>
</div>
<?= $this->endSection() ?>
