<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Projects<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $req = service('request'); $wf = config('Workflow'); ?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get">
    <input name="q" value="<?= esc($req->getGet('q')) ?>" class="form-control form-control-sm" style="max-width:220px" placeholder="Project / customer / phone">
    <select name="status" class="form-select form-select-sm" style="max-width:160px"><?= select_options(['active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'], $status) ?></select>
    <select name="stage" class="form-select form-select-sm" style="max-width:200px"><?= select_options(array_map(static fn ($s) => $s['title'], $wf->stages), $req->getGet('stage'), 'Any stage') ?></select>
    <select name="subsidy" class="form-select form-select-sm" style="max-width:160px"><?= select_options(['subsidy' => 'Subsidy', 'non_subsidy' => 'Non-subsidy'], $req->getGet('subsidy'), 'All') ?></select>
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
</form>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Project</th><th>Customer</th><th>System</th><th>Type</th><th>Current stage</th><th class="text-end">Invoice due</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= site_url('admin/projects/' . $r['id']) ?>"><?= esc($r['project_no']) ?></a><br><small class="text-muted"><?= fdate($r['created_at']) ?></small></td>
            <td><?= esc($r['customer']) ?><br><small class="text-muted"><?= esc(($r['city'] ?? '') . ' ' . ($r['phone'] ?? '')) ?></small></td>
            <td><?= qty($r['capacity_kw']) ?> kW<br><small class="text-muted"><?= esc($r['install_type'] ?? '') ?></small></td>
            <td><?= status_badge($r['customer_type']) ?> <?= status_badge($r['subsidy_type']) ?></td>
            <td><?php if ($r['status'] === 'completed'): ?><span class="text-success"><i class="bi bi-check-circle"></i> Completed</span><?php else: ?><i class="bi <?= $wf->stages[$r['current_stage']]['icon'] ?? '' ?>"></i> <?= esc($wf->stages[$r['current_stage']]['title'] ?? '') ?><?php endif ?></td>
            <td class="text-end"><?= $r['invoice_no'] ? money($r['total'] - $r['amount_paid']) : '—' ?></td>
            <td><?= status_badge($r['status']) ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No projects. Projects are created from invoices.</td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
<?= $this->endSection() ?>
