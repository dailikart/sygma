<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Complaint <?= esc($t['ticket_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $bd = include __DIR__ . '/_badges.php'; $S = \App\Libraries\ServiceDesk::STATUSES; $C = \App\Libraries\ServiceDesk::CATEGORIES;
$work = \App\Libraries\ServiceDesk::canWork($t); $pending = in_array($t['status'], ['open', 'assigned', 'in_progress'], true); ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <span class="badge fs-6 text-bg-<?= $bd['status'][$t['status']] ?? 'light' ?>"><?= esc($S[$t['status']] ?? $t['status']) ?></span>
    <span class="badge text-bg-<?= $bd['priority'][$t['priority']] ?? 'light' ?>"><?= esc(\App\Libraries\ServiceDesk::PRIORITIES[$t['priority']] ?? '') ?> priority</span>
    <?= $t['under_amc'] ? '<span class="badge text-bg-success">Under AMC</span>' : '<span class="badge text-bg-light border">Not under AMC</span>' ?>
    <div class="ms-auto d-flex gap-2">
        <?php if ($t['project_id']): ?><a href="<?= site_url('admin/projects/' . $t['project_id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-kanban"></i> <?= esc($t['project_no']) ?></a><?php endif ?>
        <a href="tel:<?= esc(preg_replace('/[^\d+]/', '', (string) ($t['contact_phone'] ?: $t['phone']))) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-telephone"></i> Call</a>
        <?php if (can('entries.delete')): ?><form method="post" action="<?= site_url('admin/service/' . $t['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" data-confirm="Delete complaint <?= esc($t['ticket_no']) ?>?"><i class="bi bi-trash"></i></button></form><?php endif ?>
    </div>
</div>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card card-body mb-3">
        <h2 class="h5 mb-1"><?= esc($C[$t['category']] ?? $t['category']) ?></h2>
        <div class="small text-muted mb-2">Registered <?= fdate($t['created_at'], true) ?> via <?= esc(\App\Libraries\ServiceDesk::VIA[$t['reported_via']] ?? $t['reported_via']) ?><?= $t['created_by'] ? ' by ' . esc(user_name($t['created_by'])) : '' ?></div>
        <p class="mb-0"><?= nl2br(esc((string) $t['description'])) ?></p>
        <?php if ($t['resolution']): ?><div class="alert alert-success mt-3 mb-0 small"><strong>Resolved <?= fdate($t['resolved_at'], true) ?><?= $t['resolved_by'] ? ' by ' . esc(user_name($t['resolved_by'])) : '' ?>:</strong> <?= nl2br(esc($t['resolution'])) ?><?= $t['chargeable'] ? '<br>Service charge: <strong>' . money($t['charge_amount']) . '</strong>' : '' ?></div><?php endif ?>
    </div>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">History</div><ul class="list-group list-group-flush small">
        <?php foreach ($updates as $u): ?><li class="list-group-item"><div class="d-flex"><strong><?= esc(user_name($u['created_by']) === '—' ? 'Customer' : user_name($u['created_by'])) ?></strong><?= $u['status'] ? ' <span class="badge text-bg-' . ($bd['status'][$u['status']] ?? 'light') . ' ms-2">' . esc($S[$u['status']] ?? $u['status']) . '</span>' : '' ?><span class="ms-auto text-muted"><?= fdate($u['created_at'], true) ?></span></div>
            <?= $u['note'] ? '<div>' . nl2br(esc($u['note'])) . '</div>' : '' ?><?php if ($u['photo_path']): ?><a href="<?= site_url('admin/service/photo/' . $u['id']) ?>" target="_blank"><img src="<?= site_url('admin/service/photo/' . $u['id']) ?>" style="max-height:120px" class="rounded mt-1" alt="photo"></a><?php endif ?></li><?php endforeach ?>
    </ul></div>
    <?php if ($work && $pending): ?>
    <form method="post" action="<?= site_url('admin/service/' . $t['id'] . '/update') ?>" enctype="multipart/form-data" class="card card-body mb-3"><?= csrf_field() ?>
        <h3 class="h6">Add update</h3>
        <textarea name="note" class="form-control mb-2" rows="2" placeholder="Called the customer / reached site / checked inverter…"></textarea>
        <div class="d-flex flex-wrap gap-2"><input type="file" name="photo" accept="image/*" capture="environment" class="form-control form-control-sm" style="max-width:260px">
            <select name="status" class="form-select form-select-sm" style="max-width:200px"><option value="">Keep status</option><option value="in_progress">In progress</option></select>
            <button class="btn btn-sm btn-outline-primary">Save update</button></div>
    </form>
    <form method="post" action="<?= site_url('admin/service/' . $t['id'] . '/resolve') ?>" enctype="multipart/form-data" class="card card-body border-success mb-3"><?= csrf_field() ?>
        <h3 class="h6 text-success"><i class="bi bi-check2-circle"></i> Resolve</h3>
        <textarea name="resolution" class="form-control mb-2" rows="2" required placeholder="What was done to fix it (e.g. replaced MC4 connector, reset inverter, cleaned panels)"></textarea>
        <div class="d-flex flex-wrap gap-2 align-items-center"><input type="file" name="photo" accept="image/*" capture="environment" class="form-control form-control-sm" style="max-width:260px">
            <label class="small">Service charge ₹ <input type="number" step="0.01" min="0" name="charge_amount" class="form-control form-control-sm d-inline-block" style="width:110px" value="0"></label>
            <?php if (! $t['under_amc']): ?><span class="small text-muted">Not under AMC – charge if applicable.</span><?php endif ?>
            <button class="btn btn-sm btn-success ms-auto">Mark resolved</button></div>
    </form>
    <?php endif ?>
</div>
<div class="col-lg-5">
    <div class="card card-body mb-3 small">
        <strong><?= esc($t['customer']) ?></strong>
        <div><i class="bi bi-telephone"></i> <?= esc((string) $t['phone']) ?><?= $t['contact_phone'] && $t['contact_phone'] !== $t['phone'] ? ' · contact ' . esc($t['contact_phone']) : '' ?></div>
        <div><i class="bi bi-geo-alt"></i> <?= esc(trim(($t['address'] ?? '') . ' ' . ($t['city'] ?? ''))) ?: '—' ?>
            <?php if ($t['address'] || $t['city']): ?><a target="_blank" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode(trim(($t['address'] ?? '') . ' ' . ($t['city'] ?? ''))) ?>">Maps</a><?php endif ?></div>
        <?php if ($t['project_no']): ?><div class="mt-1">System <?= qty($t['capacity_kw']) ?> kW · commissioned <?= fdate($t['commissioned_on']) ?></div><?php endif ?>
        <?php if ($contract): ?><div>AMC <?= esc($contract['contract_no']) ?>: <?= fdate($contract['start_date']) ?> – <?= fdate($contract['end_date']) ?></div><?php endif ?>
    </div>
    <div class="card card-body mb-3">
        <h3 class="h6">Technician</h3>
        <div class="mb-2"><?= $t['assigned_to'] ? '<strong>' . esc(user_name($t['assigned_to'])) . '</strong>' . ($t['scheduled_date'] ? ' · visit ' . fdate($t['scheduled_date']) : '') : '<span class="text-danger">Not assigned yet</span>' ?></div>
        <?php if (can('amc.manage') && $pending): ?>
        <form method="post" action="<?= site_url('admin/service/' . $t['id'] . '/assign') ?>" class="d-flex flex-wrap gap-2"><?= csrf_field() ?>
            <select name="assigned_to" class="form-select form-select-sm" style="max-width:200px" required><?= select_options($staff, $t['assigned_to'], '— Technician —') ?></select>
            <input type="date" name="scheduled_date" class="form-control form-control-sm" style="max-width:160px" value="<?= esc((string) $t['scheduled_date']) ?>">
            <button class="btn btn-sm btn-primary"><?= $t['assigned_to'] ? 'Re-assign' : 'Assign' ?></button></form>
        <?php endif ?>
    </div>
    <?php if (can('amc.manage') && in_array($t['status'], ['resolved', 'closed'], true)): ?>
    <form method="post" action="<?= site_url('admin/service/' . $t['id'] . '/close') ?>" class="card card-body mb-3"><?= csrf_field() ?>
        <input name="note" class="form-control form-control-sm mb-2" placeholder="Note (optional), e.g. customer confirmed working">
        <div class="d-flex gap-2"><?php if ($t['status'] === 'resolved'): ?><button name="action" value="close" class="btn btn-sm btn-secondary">Close complaint</button><?php endif ?>
            <button name="action" value="reopen" class="btn btn-sm btn-outline-danger">Reopen – not fixed</button></div>
    </form>
    <?php endif ?>
    <?php if ($history): ?><div class="card"><div class="card-header bg-white fw-semibold small">Earlier complaints of this customer</div><ul class="list-group list-group-flush small">
        <?php foreach ($history as $h): ?><li class="list-group-item d-flex"><a href="<?= site_url('admin/service/' . $h['id']) ?>"><?= esc($h['ticket_no']) ?></a><span class="ms-2"><?= esc($C[$h['category']] ?? '') ?></span><span class="ms-auto badge text-bg-<?= $bd['status'][$h['status']] ?? 'light' ?>"><?= esc($S[$h['status']] ?? '') ?></span></li><?php endforeach ?>
    </ul></div><?php endif ?>
</div>
</div>
<?= $this->endSection() ?>
