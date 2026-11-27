<?= $this->extend('owner/layout') ?>
<?= $this->section('content') ?>
<?php $badge = ['active' => 'success', 'trial' => 'info', 'suspended' => 'danger', 'expired' => 'secondary', 'setup' => 'warning'];
$labels = ['all' => 'All', 'active' => 'Active', 'trial' => 'Trial', 'expiring' => 'Expiring in 15 days', 'expired' => 'Expired', 'suspended' => 'Suspended']; ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h1 class="h4 mb-0 me-auto">Companies</h1>
    <form method="post" action="<?= site_url('owner/migrate-all') ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" data-confirm="Run database updates for every company now? (Take a database backup first.)"><i class="bi bi-database-up"></i> Database updates for all</button></form>
    <a href="<?= site_url('owner/companies/new') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New company</a>
</div>
<div class="row g-2 mb-3">
    <?php foreach ($labels as $k => $l): ?>
    <div class="col-6 col-md-4 col-xl-2"><a href="<?= site_url('owner?show=' . $k) ?>" class="card card-body py-2 stat text-decoration-none text-body <?= ($show ?: 'all') === $k ? 'border border-warning' : '' ?>">
        <div class="small text-muted"><?= $l ?></div><div class="num <?= $k === 'expiring' && $counts[$k] ? 'text-warning' : ($k === 'suspended' && $counts[$k] ? 'text-danger' : '') ?>"><?= (int) ($counts[$k] ?? 0) ?></div></a></div>
    <?php endforeach ?>
</div>
<form class="d-flex gap-2 mb-2" method="get"><input type="hidden" name="show" value="<?= esc($show) ?>"><input name="q" value="<?= esc($q) ?>" class="form-control form-control-sm" style="max-width:300px" placeholder="Name, code, address, phone, email"><button class="btn btn-sm btn-outline-secondary">Search</button></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Company</th><th>Address</th><th>Plan</th><th>Status</th><th>Expires</th><th>Last used</th></tr></thead><tbody>
    <?php foreach ($companies as $c): $days = $c['expires_on'] ? (int) floor((strtotime($c['expires_on']) - strtotime(date('Y-m-d'))) / 86400) : null; ?>
        <tr onclick="location.href='<?= site_url('owner/companies/' . $c['id']) ?>'" style="cursor:pointer">
            <td><a href="<?= site_url('owner/companies/' . $c['id']) ?>" class="fw-semibold"><?= esc($c['name']) ?></a><div class="small text-muted"><?= esc($c['code']) ?><?= $c['contact_name'] ? ' · ' . esc($c['contact_name']) : '' ?><?= $c['phone'] ? ' · ' . esc($c['phone']) : '' ?></div></td>
            <td class="small"><?= esc($c['domain']) ?><?= $c['alt_domain'] ? '<br><span class="text-muted">' . esc($c['alt_domain']) . '</span>' : '' ?></td>
            <td class="small"><?= esc((string) $c['plan']) ?><?= $c['max_users'] ? '<br><span class="text-muted">' . (int) $c['max_users'] . ' users</span>' : '' ?></td>
            <td><span class="badge text-bg-<?= $badge[$c['state_label']] ?? 'light' ?>"><?= ucfirst($c['state_label']) ?></span></td>
            <td class="small text-nowrap"><?= $c['expires_on'] ? date('d-m-Y', strtotime($c['expires_on'])) . ($days !== null && $days >= 0 && $days <= 15 ? ' <span class="badge text-bg-warning">' . $days . ' d</span>' : '') : '<span class="text-muted">—</span>' ?></td>
            <td class="small text-muted text-nowrap"><?= $c['last_seen_at'] ? date('d-m-Y H:i', strtotime($c['last_seen_at'])) : 'never' ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $companies): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= $counts['all'] ? 'No company matches.' : 'No companies yet. Click <b>New company</b> to add your first client.' ?></td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
