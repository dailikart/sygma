<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Project <?= esc($p['project_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$pid = (int) $p['id'];
$staffFor = static fn (string $stage) => staff_options($wf->stages[$stage]['role']) ?: staff_options();
$v = static fn (array $s, string $k, $d = '') => esc((string) ($s['data'][$k] ?? $d));
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <?= status_badge($p['status']) ?> <?= status_badge($p['customer_type']) ?> <?= status_badge($p['subsidy_type']) ?>
    <span class="text-muted small"><?= qty($p['capacity_kw']) ?> kW <?= $type ? '· ' . esc($type['name']) : '' ?> <?= $kit ? '· Kit: ' . esc($kit['name']) : '' ?></span>
    <div class="ms-auto d-flex gap-2">
        <?php if (can('entries.delete')): ?><form method="post" action="<?= site_url('admin/projects/' . $pid . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" data-confirm="Delete project <?= esc($p['project_no']) ?> permanently? Its stages, photos, dispatches (stock goes back to the store) and AMC visits are deleted. The invoice is kept."><i class="bi bi-trash"></i> Delete</button></form><?php endif ?>
        <?php if (can('amc.manage') || can('customers.manage')): ?><a href="<?= site_url('admin/service/new?customer=' . $p['customer_id'] . '&project=' . $pid) ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-tools"></i> Complaint</a><?php endif ?>
        <?php if ($invoice): ?><a href="<?= site_url('admin/invoices/' . $invoice['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt"></i> <?= esc($invoice['invoice_no']) ?> <?= status_badge($invoice['status']) ?></a><?php endif ?>
        <?php if (can('projects.manage') && in_array($p['status'], ['active', 'on_hold'], true)): ?>
            <form method="post" action="<?= site_url('admin/projects/' . $pid . '/status') ?>" class="d-flex gap-1"><?= csrf_field() ?>
                <?php if ($p['status'] === 'active'): ?><button name="status" value="on_hold" class="btn btn-sm btn-outline-warning">Put on hold</button><?php else: ?><button name="status" value="active" class="btn btn-sm btn-outline-success">Resume</button><?php endif ?>
                <button name="status" value="cancelled" class="btn btn-sm btn-outline-danger" data-confirm="Cancel this project?">Cancel</button></form>
        <?php endif ?>
    </div>
</div>

<?php if (! empty($profit) && $profit['revenue'] > 0): ?>
<div class="card card-body py-2 mb-3 small d-flex flex-row flex-wrap gap-3 align-items-center">
    <span class="fw-semibold"><i class="bi bi-graph-up-arrow"></i> Profit</span>
    <span>Invoice before GST <strong><?= money($profit['revenue']) ?></strong></span>
    <span>− Material <strong><?= money($profit['material']) ?></strong></span>
    <span>− Expenses <strong><?= money($profit['expenses']) ?></strong></span>
    <?php if ($profit['eb'] > 0): ?><span>− EB charges <strong><?= money($profit['eb']) ?></strong></span><?php endif ?>
    <span>= <strong class="<?= $profit['profit'] < 0 ? 'text-danger' : 'text-success' ?>"><?= money($profit['profit']) ?></strong><?= $profit['margin'] !== null ? ' (' . $profit['margin'] . '%)' : '' ?></span>
    <a href="<?= site_url('admin/reports/project-profit') ?>" class="ms-auto">All projects</a>
</div>
<?php endif ?>
<?php if (\App\Libraries\Features::on('projects.status_page') && (can('projects.view') || can('projects.manage') || can('invoices.view'))): $statusUrl = \App\Libraries\StatusLink::url($pid); ?>
<div class="card card-body py-2 mb-3 small d-flex flex-row flex-wrap gap-2 align-items-center">
    <span class="fw-semibold"><i class="bi bi-phone"></i> Customer status page</span>
    <input class="form-control form-control-sm" style="max-width:360px" value="<?= esc($statusUrl) ?>" readonly id="statusUrl" onclick="this.select()">
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('statusUrl').value).then(() => { this.innerHTML = '<i class=&quot;bi bi-check2&quot;></i> Copied'; })"><i class="bi bi-clipboard"></i> Copy</button>
    <a href="<?= site_url('admin/projects/' . $pid . '/status-link/whatsapp') ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i> Send on WhatsApp</a>
    <a href="<?= esc($statusUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Open</a>
    <span class="text-muted"><?= $p['status_viewed_at'] ? '<i class="bi bi-eye text-success"></i> Customer last opened ' . fdate($p['status_viewed_at'], true) : 'Not opened by the customer yet' ?></span>
    <?php if (can('projects.manage')): ?><form method="post" action="<?= site_url('admin/projects/' . $pid . '/status-link/reset') ?>" class="ms-auto"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0" data-confirm="Create a new link? The old link stops working (use this if it was sent to the wrong person).">Reset link</button></form><?php endif ?>
</div>
<?php endif ?>
<div class="stage-steps mb-3">
<?php foreach ($stages as $s): ?>
    <a href="#stage-<?= $s['stage'] ?>" class="stage-step text-decoration-none text-reset <?= $s['status'] ?>">
        <div><i class="bi <?= $wf->stages[$s['stage']]['icon'] ?>"></i> <strong><?= esc($wf->stages[$s['stage']]['title']) ?></strong></div>
        <div class="small"><?= $s['status'] === 'done' ? '<i class="bi bi-check-circle-fill text-success"></i> ' . fdate($s['completed_at']) : status_badge($s['status']) ?></div>
        <div class="small text-muted"><?= $s['assigned_to'] ? '<i class="bi bi-person"></i> ' . esc(user_name($s['assigned_to'])) : '' ?></div>
    </a>
<?php endforeach ?>
    <div class="stage-step <?= $p['status'] === 'completed' ? 'done' : '' ?>"><i class="bi bi-flag"></i> <strong>Completed</strong><div class="small"><?= fdate($p['completed_at']) ?></div></div>
</div>

<div class="row g-3">
<div class="col-xl-8">
<?php foreach ($stages as $s): $key = $s['stage']; $canAct = \App\Libraries\Workflow::canAct($s); $active = $s['status'] === 'active' && $p['status'] === 'active'; ?>
<div class="card mb-3 <?= $active ? 'border border-primary' : '' ?>" id="stage-<?= $key ?>">
    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold"><i class="bi <?= $wf->stages[$key]['icon'] ?>"></i> <?= esc($wf->stages[$key]['title']) ?></span> <?= status_badge($s['status']) ?>
        <?php if ($s['planned_date']): ?><small class="text-muted">Planned <?= fdate($s['planned_date']) ?></small><?php endif ?>
        <?php if ($s['completed_by']): ?><small class="text-muted">by <?= esc(user_name($s['completed_by'])) ?></small><?php endif ?>
        <?php if (can('projects.manage') && in_array($s['status'], ['pending', 'active'], true)): ?>
        <form method="post" action="<?= site_url('admin/projects/' . $pid . '/assign') ?>" class="ms-auto d-flex gap-1"><?= csrf_field() ?>
            <input type="hidden" name="stage_id" value="<?= $s['id'] ?>">
            <select name="user_id" class="form-select form-select-sm" style="max-width:160px"><?= select_options($staffFor($key), $s['assigned_to'], 'Anyone in role') ?></select>
            <input type="date" name="planned_date" value="<?= esc((string) $s['planned_date']) ?>" class="form-control form-control-sm" style="max-width:150px" title="Planned date">
            <button class="btn btn-sm btn-outline-secondary">Assign</button></form>
        <?php endif ?>
    </div>
    <div class="card-body">
    <?php if ($key === 'survey'): ?>
        <?php if ($s['status'] === 'rejected'): ?><div class="alert alert-danger py-2">Rejected: <?= esc((string) $s['notes']) ?>
            <?php if (can('projects.manage') || can('survey.update')): ?><form method="post" action="<?= site_url('admin/projects/' . $pid . '/survey/reopen') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger ms-2">Reopen survey</button></form><?php endif ?></div><?php endif ?>
        <?= view('admin/projects/_files', ['list' => $files['survey'] ?? [], 'stageRow' => $s]) ?>
        <?php if ($active && $canAct): ?>
            <?= view('admin/projects/_upload', ['pid' => $pid, 'stage' => 'survey', 'labels' => ['site_photo' => 'Site / roof photo', 'survey_sheet' => 'Survey sheet']]) ?>
            <form method="post" action="<?= site_url('admin/projects/' . $pid . '/stage/survey') ?>"><?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label small">Survey date</label><input type="date" name="survey_date" class="form-control form-control-sm" value="<?= $v($s, 'survey_date', date('Y-m-d')) ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Roof type</label><select name="roof_type" class="form-select form-select-sm"><?= select_options(array_combine($r = ['RCC', 'Metal sheet', 'Tile', 'Asbestos', 'Ground mount'], $r), $s['data']['roof_type'] ?? '', '—') ?></select></div>
                    <div class="col-md-3"><label class="form-label small">Shadow-free area (sq.ft)</label><input name="roof_area" class="form-control form-control-sm" value="<?= $v($s, 'roof_area') ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Recommended kW</label><input name="recommended_kw" class="form-control form-control-sm" value="<?= $v($s, 'recommended_kw', qty($p['capacity_kw'])) ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Structure height</label><input name="structure_height" class="form-control form-control-sm" value="<?= $v($s, 'structure_height') ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Cable length (m)</label><input name="cable_length" class="form-control form-control-sm" value="<?= $v($s, 'cable_length') ?>"></div>
                    <div class="col-md-6"><label class="form-label small">Shading / obstacles</label><input name="shading" class="form-control form-control-sm" value="<?= $v($s, 'shading') ?>"></div>
                    <div class="col-12"><label class="form-label small">Notes (reason if rejecting)</label><textarea name="notes" class="form-control form-control-sm" rows="2"><?= esc((string) $s['notes']) ?></textarea></div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <button name="action" value="save" class="btn btn-sm btn-outline-primary">Save</button>
                    <button name="action" value="complete" class="btn btn-sm btn-success" data-confirm="Approve the site? The store will be notified to dispatch material."><i class="bi bi-check-lg"></i> Approve site</button>
                    <button name="action" value="reject" class="btn btn-sm btn-outline-danger ms-auto" data-confirm="Reject the site? The project goes on hold.">Reject</button>
                </div>
            </form>
        <?php elseif ($s['data']): ?>
            <dl class="row small mb-0"><?php foreach (['survey_date' => 'Date', 'roof_type' => 'Roof', 'roof_area' => 'Area', 'recommended_kw' => 'Recommended kW', 'structure_height' => 'Structure', 'cable_length' => 'Cable', 'shading' => 'Shading'] as $k => $l): if (! empty($s['data'][$k])): ?><dt class="col-sm-3"><?= $l ?></dt><dd class="col-sm-9"><?= esc((string) $s['data'][$k]) ?></dd><?php endif; endforeach ?></dl>
        <?php endif ?>

    <?php elseif ($key === 'dispatch'): ?>
        <?php if ($dispatches): ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Dispatch</th><th>Status</th><th>Vehicle</th><th>Dispatched</th><th>Delivered</th></tr></thead><tbody>
            <?php foreach ($dispatches as $d): ?><tr><td><a href="<?= site_url('admin/dispatches/' . $d['id']) ?>"><?= esc($d['dispatch_no']) ?></a></td><td><?= status_badge($d['status']) ?></td><td><?= esc((string) $d['vehicle_no']) ?></td><td><?= fdate($d['dispatched_at'], true) ?></td><td><?= fdate($d['delivered_at'], true) ?></td></tr><?php endforeach ?>
        </tbody></table></div><?php endif ?>
        <?php if (in_array($s['status'], ['active', 'done'], true) && $p['status'] === 'active' && can('dispatch.manage')): ?>
            <a href="<?= site_url('admin/projects/' . $pid . '/dispatch') ?>" class="btn btn-sm btn-primary"><i class="bi bi-list-check"></i> <?= $dispatches ? 'Prepare another dispatch list' : 'Prepare dispatch list from kit' ?></a>
        <?php elseif ($s['status'] === 'pending'): ?><p class="text-muted small mb-0">Starts after the site survey is approved.</p><?php endif ?>
        <?php if ($active && $canAct && array_filter($dispatches, static fn ($d) => in_array($d['status'], ['dispatched', 'delivered'], true))): ?>
            <form method="post" action="<?= site_url('admin/projects/' . $pid . '/stage/dispatch') ?>" class="mt-2"><?= csrf_field() ?>
                <button name="action" value="complete" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Complete dispatch stage</button></form>
        <?php endif ?>

    <?php elseif ($key === 'delivery'): ?>
        <?= view('admin/projects/_files', ['list' => $files['delivery'] ?? [], 'stageRow' => $s]) ?>
        <?php $onRoad = array_filter($dispatches, static fn ($d) => $d['status'] === 'dispatched'); $drafts = array_filter($dispatches, static fn ($d) => $d['status'] === 'draft'); ?>
        <?php foreach ($onRoad as $d): ?>
            <p class="mb-1"><?= esc($d['dispatch_no']) ?> is on the road. <a href="<?= site_url('admin/dispatches/' . $d['id']) ?>" class="btn btn-sm btn-success ms-2"><i class="bi bi-camera"></i> Mark delivered with photo</a></p>
        <?php endforeach ?>
        <?php if ($active && $drafts): ?><p class="small text-muted mb-1">Draft list(s) not loaded: <?php foreach ($drafts as $d): ?><a href="<?= site_url('admin/dispatches/' . $d['id']) ?>"><?= esc($d['dispatch_no']) ?></a> <?php endforeach ?>– dispatch or delete them; they do not block delivery.</p><?php endif ?>
        <?php if ($active && $canAct && ! $onRoad): ?>
            <?php if (\App\Libraries\Workflow::hasFile($pid, 'delivery', 'delivery_photo')): ?>
                <form method="post" action="<?= site_url('admin/projects/' . $pid . '/stage/delivery') ?>" class="mt-2"><?= csrf_field() ?>
                    <button name="action" value="complete" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Complete delivery – start installation</button></form>
            <?php else: ?>
                <?= view('admin/projects/_upload', ['pid' => $pid, 'stage' => 'delivery', 'labels' => ['delivery_photo' => 'Delivery photo']]) ?>
                <p class="small text-muted mb-0">Upload the delivery photo, then complete delivery.</p>
            <?php endif ?>
        <?php endif ?>
        <?php if ($s['status'] === 'pending'): ?><p class="text-muted small mb-0">Starts when material is dispatched.</p><?php endif ?>

    <?php elseif ($key === 'installation'): ?>
        <?php if ($s['status'] === 'pending'): ?><div class="alert alert-light border py-2 small mb-2"><i class="bi bi-info-circle"></i> Installation opens after <strong>Delivery at Site</strong> is completed (material marked delivered with a photo).</div><?php endif ?>
        <?php
            $invSerial = $panelSerial = [];
            foreach ($dispatchSerials as $ds) { if (stripos((string) $ds['category'], 'inverter') !== false) { $invSerial[] = $ds['serial_nos']; } elseif (stripos((string) $ds['category'], 'panel') !== false) { $panelSerial[] = $ds['serial_nos']; } }
            if (empty($s['data']['inverter_serial']) && $invSerial) { $s['data']['inverter_serial'] = str_replace("\n", ', ', implode("\n", $invSerial)); }
            if (empty($s['data']['panel_serials']) && $panelSerial) { $s['data']['panel_serials'] = implode("\n", $panelSerial); }
        ?>
        <?php $need = ['panel_photo' => 'Panel photo', 'net_meter_agreement' => 'Net-meter agreement', 'panel_serial_photo' => 'Panel serial no. photo', 'inverter_photo' => 'Inverter photo (optional)']; ?>
        <?php foreach (array_slice($need, 0, 3, true) as $lab => $title): $has = \App\Libraries\Workflow::hasFile($pid, 'installation', $lab); ?>
            <span class="badge <?= $has ? 'text-bg-success' : 'text-bg-light border' ?> me-1"><i class="bi <?= $has ? 'bi-check' : 'bi-circle' ?>"></i> <?= $title ?></span>
        <?php endforeach ?>
        <?= view('admin/projects/_files', ['list' => $files['installation'] ?? [], 'stageRow' => $s]) ?>
        <?php if ($active && $canAct): ?>
            <?= view('admin/projects/_upload', ['pid' => $pid, 'stage' => 'installation', 'labels' => $need]) ?>
            <form method="post" action="<?= site_url('admin/projects/' . $pid . '/stage/installation') ?>"><?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label small">Installation date</label><input type="date" name="installation_date" class="form-control form-control-sm" value="<?= $v($s, 'installation_date', date('Y-m-d')) ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Inverter make</label><input name="inverter_make" class="form-control form-control-sm" value="<?= $v($s, 'inverter_make') ?>"></div>
                    <div class="col-md-6"><label class="form-label small">Inverter serial number *</label><input name="inverter_serial" class="form-control form-control-sm" value="<?= $v($s, 'inverter_serial') ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Panel make</label><input name="panel_make" class="form-control form-control-sm" value="<?= $v($s, 'panel_make') ?>"></div>
                    <div class="col-md-3"><label class="form-label small">No. of panels</label><input type="number" name="panel_count" class="form-control form-control-sm" value="<?= $v($s, 'panel_count') ?>"></div>
                    <div class="col-md-6"><label class="form-label small">Panel serial numbers * (one per line)</label><textarea name="panel_serials" rows="3" class="form-control form-control-sm"><?= $v($s, 'panel_serials') ?></textarea></div>
                    <div class="col-12"><label class="form-label small">Notes</label><input name="notes" class="form-control form-control-sm" value="<?= esc((string) $s['notes']) ?>"></div>
                </div>
                <div class="d-flex gap-2 mt-2"><button name="action" value="save" class="btn btn-sm btn-outline-primary">Save</button>
                    <button name="action" value="complete" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Installation completed</button></div>
            </form>
        <?php elseif ($s['data']): ?>
            <dl class="row small mb-0"><dt class="col-sm-3">Date</dt><dd class="col-sm-9"><?= fdate($s['data']['installation_date'] ?? null) ?></dd>
                <dt class="col-sm-3">Inverter</dt><dd class="col-sm-9"><?= $v($s, 'inverter_make') ?> <?= $v($s, 'inverter_serial') ?></dd>
                <dt class="col-sm-3">Panels</dt><dd class="col-sm-9"><?= $v($s, 'panel_make') ?> × <?= $v($s, 'panel_count') ?><br><small style="white-space:pre-line"><?= $v($s, 'panel_serials') ?></small></dd></dl>
        <?php endif ?>

    <?php elseif ($key === 'dcr'): ?>
        <?php $editable = $active && $canAct; ?>
        <span class="badge <?= \App\Libraries\Workflow::hasFile($pid, 'dcr', 'dcr_certificate') ? 'text-bg-success' : 'text-bg-light border' ?>"><i class="bi <?= \App\Libraries\Workflow::hasFile($pid, 'dcr', 'dcr_certificate') ? 'bi-check' : 'bi-circle' ?>"></i> DCR certificate</span>
        <?= view('admin/projects/_files', ['list' => $files['dcr'] ?? [], 'stageRow' => $s]) ?>
        <?php if ($editable): ?><?= view('admin/projects/_upload', ['pid' => $pid, 'stage' => 'dcr', 'labels' => ['dcr_certificate' => 'DCR certificate', 'submission_proof' => 'Portal submission screenshot']]) ?><?php endif ?>
        <form method="post" action="<?= site_url('admin/projects/' . $pid . '/stage/dcr') ?>"><?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label small">DCR certificate no.</label><input name="dcr_number" class="form-control form-control-sm" value="<?= $v($s, 'dcr_number') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-3"><label class="form-label small">DCR date</label><input type="date" name="dcr_date" class="form-control form-control-sm" value="<?= $v($s, 'dcr_date') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-3"><label class="form-label small">Installation submission / portal ref. *</label><input name="submission_ref" class="form-control form-control-sm" value="<?= $v($s, 'submission_ref') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-3"><label class="form-label small">Submitted on</label><input type="date" name="submission_date" class="form-control form-control-sm" value="<?= $v($s, 'submission_date', date('Y-m-d')) ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-12"><label class="form-label small">Notes</label><input name="notes" class="form-control form-control-sm" value="<?= esc((string) $s['notes']) ?>" <?= $editable ? '' : 'disabled' ?>></div>
            </div>
            <?php if ($editable): ?><div class="d-flex gap-2 mt-2"><button name="action" value="save" class="btn btn-sm btn-outline-primary">Save</button>
                <button name="action" value="complete" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Submit installation – close DCR step</button></div><?php endif ?>
        </form>

    <?php elseif ($key === 'eb_process'): ?>
        <?php $paid = \App\Libraries\Workflow::invoicePaid($pid); $steps = $s['data']['steps'] ?? []; $editable = $active && $canAct; ?>
        <?php if (! $paid): ?><div class="alert alert-warning py-2 small">Invoice not fully paid yet – "EB charges paid" stays locked and the EB process cannot be completed until the full amount is received.</div><?php endif ?>
        <?php if ($missingDocs): ?><div class="alert alert-info py-2 small">Required documents still missing for the PCR: <strong><?= esc(implode(', ', $missingDocs)) ?></strong> – <a href="#documents">upload them below</a>.</div><?php endif ?>
        <?= view('admin/projects/_files', ['list' => $files['eb_process'] ?? [], 'stageRow' => $s]) ?>
        <?php if ($editable): ?><?= view('admin/projects/_upload', ['pid' => $pid, 'stage' => 'eb_process', 'labels' => ['pcr_copy' => 'PCR copy', 'sanction_letter' => 'Sanction letter', 'eb_receipt' => 'EB charges receipt', 'commissioning_report' => 'Commissioning report']]) ?><?php endif ?>
        <form method="post" action="<?= site_url('admin/projects/' . $pid . '/stage/eb_process') ?>"><?= csrf_field() ?>
            <div class="table-responsive"><table class="table table-sm align-middle"><tbody>
            <?php foreach ($wf->ebSteps as $k => $title): $done = ! empty($steps[$k]); $locked = $k === 'eb_charges_paid' && ! $paid && ! $done; ?>
                <tr><td style="width:36px"><input type="checkbox" class="form-check-input" name="steps[<?= $k ?>]" value="1" <?= $done ? 'checked' : '' ?> <?= ! $editable || $locked ? 'disabled' : '' ?>>
                    <?php if ($done && ! $editable): ?><input type="hidden" name="steps[<?= $k ?>]" value="1"><?php endif ?></td>
                    <td><?= esc($title) ?> <?= $locked ? '<i class="bi bi-lock text-warning"></i>' : '' ?></td>
                    <td style="width:170px"><input type="date" name="step_date[<?= $k ?>]" class="form-control form-control-sm" value="<?= esc((string) ($steps[$k] ?? '')) ?>" <?= $editable ? '' : 'disabled' ?>></td></tr>
            <?php endforeach ?>
            </tbody></table></div>
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label small">EB submission status</label><select name="eb_submission_status" class="form-select form-select-sm" <?= $editable ? '' : 'disabled' ?>><?= select_options(['no' => 'No', 'yes' => 'Yes'], $s['data']['eb_submission_status'] ?? 'no') ?></select></div>
                <div class="col-md-3"><label class="form-label small">EB application no.</label><input name="application_no" class="form-control form-control-sm" value="<?= $v($s, 'application_no') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-3"><label class="form-label small">Sanction no.</label><input name="sanction_no" class="form-control form-control-sm" value="<?= $v($s, 'sanction_no') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-3"><label class="form-label small">EB charges (₹)</label><input name="eb_charges_amount" class="form-control form-control-sm" value="<?= $v($s, 'eb_charges_amount') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-6"><label class="form-label small">AMC plan (starts on the commissioning date)</label><select name="amc_plan_id" class="form-select form-select-sm" <?= $editable ? '' : 'disabled' ?>><?= select_options($amcPlans, $s['data']['amc_plan_id'] ?? '', 'Default plan') ?></select></div>
                <div class="col-md-6"><label class="form-label small">Notes</label><input name="notes" class="form-control form-control-sm" value="<?= esc((string) $s['notes']) ?>" <?= $editable ? '' : 'disabled' ?>></div>
            </div>
            <?php if ($editable): ?><div class="d-flex flex-wrap gap-2 mt-2 align-items-center"><button name="action" value="save" class="btn btn-sm btn-outline-primary">Save progress</button>
                <button name="action" value="complete" class="btn btn-sm btn-success" id="ebComplete" data-confirm="All EB steps done and the system is commissioned?"><i class="bi bi-check-lg"></i> Commissioned – complete EB process</button>
                <small class="text-muted" id="ebHint">Tick each step as it happens and click <strong>Save progress</strong>. Completing is possible when all steps are ticked.</small></div>
            <script>
            (function () {
                const boxes = document.querySelectorAll('#stage-eb_process input[type=checkbox][name^="steps["]');
                const btn = document.getElementById('ebComplete');
                const upd = () => { const all = [...boxes].every(b => b.checked); btn.disabled = !all; };
                boxes.forEach(b => b.addEventListener('change', function () {
                    const d = this.closest('tr').querySelector('input[type=date]'); if (this.checked && d && !d.value) d.value = new Date().toISOString().slice(0, 10);
                    upd();
                }));
                upd();
            })();
            </script><?php endif ?>
        </form>

    <?php elseif ($key === 'subsidy'): ?>
        <?php $editable = $active && $canAct; ?>
        <form method="post" action="<?= site_url('admin/projects/' . $pid . '/stage/subsidy') ?>"><?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small">Subsidy status</label><select name="status" class="form-select form-select-sm" <?= $editable ? '' : 'disabled' ?>><?= select_options($wf->subsidyStatuses, $s['data']['status'] ?? 'not_applied') ?></select></div>
                <div class="col-md-4"><label class="form-label small">PM Surya Ghar application no.</label><input name="application_no" class="form-control form-control-sm" value="<?= $v($s, 'application_no') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-4"><label class="form-label small">Applied on</label><input type="date" name="applied_date" class="form-control form-control-sm" value="<?= $v($s, 'applied_date') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-4"><label class="form-label small">Subsidy amount (₹)</label><input name="subsidy_amount" class="form-control form-control-sm" value="<?= $v($s, 'subsidy_amount') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-4"><label class="form-label small">Received on</label><input type="date" name="received_date" class="form-control form-control-sm" value="<?= $v($s, 'received_date') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                <div class="col-md-4"><label class="form-label small">Notes</label><input name="notes" class="form-control form-control-sm" value="<?= esc((string) $s['notes']) ?>" <?= $editable ? '' : 'disabled' ?>></div>
            </div>
            <?php if ($editable): ?><div class="d-flex gap-2 mt-2"><button name="action" value="save" class="btn btn-sm btn-outline-primary">Save status</button>
                <button name="action" value="complete" class="btn btn-sm btn-success" data-confirm="Close the project with this subsidy status?"><i class="bi bi-flag"></i> Close project</button></div>
                <div class="form-text">To close, set the status to Received, Rejected or Refunded.</div><?php endif ?>
        </form>
    <?php endif ?>
    </div>
</div>
<?php endforeach ?>
<?= \App\Controllers\Admin\Documents::panel((int) $customer['id'], $pid) ?>
</div>

<div class="col-xl-4">
    <?php if ($invoice): ?><?= view('admin/invoices/_finance', ['inv' => $invoice, 'tranches' => $tranches, 'financeEditable' => $financeEditable]) ?><?php endif ?>
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6"><a href="<?= site_url('admin/customers/' . $customer['id']) ?>"><?= esc($customer['name']) ?></a></h2>
        <div><i class="bi bi-telephone"></i> <a href="tel:<?= esc((string) $customer['phone']) ?>"><?= esc((string) $customer['phone']) ?></a></div>
        <div class="small mt-1"><i class="bi bi-geo-alt"></i> <?= esc((string) $p['site_address']) ?></div>
        <?php if ($customer['eb_service_no']): ?><div class="small">EB service no: <?= esc($customer['eb_service_no']) ?></div><?php endif ?>
        <a class="small" target="_blank" href="https://maps.google.com/?q=<?= urlencode((string) $p['site_address']) ?>">Open in Maps</a>
    </div></div>
    <?php if ($amc): ?>
    <div class="card mb-3"><div class="card-body"><h2 class="h6"><i class="bi bi-wrench-adjustable"></i> AMC</h2>
        <div class="small">Commissioned <?= fdate($p['commissioned_on']) ?><br>AMC <?= esc($amc['contract_no']) ?>: <?= fdate($amc['start_date']) ?> – <?= fdate($amc['end_date']) ?> <?= status_badge($amc['status']) ?></div>
        <?php if (can('amc.view') || can('amc.manage')): ?><a href="<?= site_url('admin/amc/contracts/' . $amc['id']) ?>" class="btn btn-sm btn-outline-primary mt-2">Service visits</a><?php endif ?></div></div>
    <?php endif ?>
    <div class="card"><div class="card-header bg-white fw-semibold">Activity</div>
        <ul class="list-group list-group-flush small">
        <?php foreach ($log as $a): ?><li class="list-group-item"><?= esc($a['description']) ?><br><span class="text-muted"><?= esc(user_name($a['user_id'])) ?> · <?= fdate($a['created_at'], true) ?></span></li><?php endforeach ?>
        </ul></div>
</div>
</div>
<?= $this->endSection() ?>
