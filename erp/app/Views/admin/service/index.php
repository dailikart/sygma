<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Service Complaints<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $bd = include __DIR__ . '/_badges.php'; $S = \App\Libraries\ServiceDesk::STATUSES; $C = \App\Libraries\ServiceDesk::CATEGORIES; $P = \App\Libraries\ServiceDesk::PRIORITIES;
$active = (int) ($counts['open'] ?? 0) + (int) ($counts['assigned'] ?? 0) + (int) ($counts['in_progress'] ?? 0); ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="btn-group btn-group-sm flex-wrap">
        <a href="?status=active" class="btn btn-outline-secondary <?= $status === 'active' ? 'active' : '' ?>">Pending <span class="badge text-bg-danger"><?= $active ?></span></a>
        <?php foreach ($S as $k => $label): ?><a href="?status=<?= $k ?>" class="btn btn-outline-secondary <?= $status === $k ? 'active' : '' ?>"><?= esc($label) ?> <span class="badge text-bg-light border"><?= (int) ($counts[$k] ?? 0) ?></span></a><?php endforeach ?>
        <a href="?status=all" class="btn btn-outline-secondary <?= $status === 'all' ? 'active' : '' ?>">All</a>
    </div>
    <form method="get" class="d-flex gap-2"><input type="hidden" name="status" value="<?= esc($status) ?>">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" class="form-control form-control-sm" placeholder="No. / customer / phone / project">
        <select name="assigned" class="form-select form-select-sm"><?= select_options($staff, service('request')->getGet('assigned'), 'Any technician') ?></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button></form>
    <?php if (can('amc.manage') || can('customers.manage')): ?><a href="<?= site_url('admin/service/new') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New Complaint</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover table-sm table-bordered table-grid mb-0 align-middle small">
    <thead><tr><th>Complaint</th><th>Customer</th><th>Problem</th><th>Priority</th><th>Status</th><th>Technician</th><th>Visit</th><th>Age</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $age = (int) floor((time() - strtotime($r['created_at'])) / 86400); $pending = in_array($r['status'], ['open', 'assigned', 'in_progress'], true); ?>
        <tr><td class="text-nowrap"><a href="<?= site_url('admin/service/' . $r['id']) ?>"><?= esc($r['ticket_no']) ?></a><div class="text-muted"><?= fdate($r['created_at']) ?><?= $r['under_amc'] ? ' · <span class="badge text-bg-success">AMC</span>' : '' ?></div></td>
            <td><?= esc($r['customer']) ?><div class="text-muted"><?= esc((string) $r['phone']) ?><?= $r['project_no'] ? ' · ' . esc($r['project_no']) : '' ?></div></td>
            <td><?= esc($C[$r['category']] ?? $r['category']) ?><div class="text-muted text-truncate" style="max-width:240px"><?= esc((string) $r['description']) ?></div></td>
            <td><span class="badge text-bg-<?= $bd['priority'][$r['priority']] ?? 'light' ?>"><?= esc($P[$r['priority']] ?? $r['priority']) ?></span></td>
            <td><span class="badge text-bg-<?= $bd['status'][$r['status']] ?? 'light' ?>"><?= esc($S[$r['status']] ?? $r['status']) ?></span></td>
            <td><?= $r['assigned_to'] ? esc(user_name($r['assigned_to'])) : '<span class="text-danger">Not assigned</span>' ?></td>
            <td class="text-nowrap <?= $pending && $r['scheduled_date'] && $r['scheduled_date'] < date('Y-m-d') ? 'text-danger fw-semibold' : '' ?>"><?= fdate($r['scheduled_date']) ?></td>
            <td class="text-nowrap <?= $pending && $age > 2 ? 'text-danger fw-semibold' : '' ?>"><?= $pending ? $age . ' d' : '—' ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No complaints here.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
