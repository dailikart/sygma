<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Project Status<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('admin/reports/_head') ?>
<div class="row g-3 mb-3"><?php foreach ($completed as $c): ?><div class="col-6 col-md-3"><div class="card card-body stat-card"><div class="small text-muted">Completed <?= strtoupper($c['customer_type']) ?></div><div class="value"><?= (int) $c['n'] ?> <small class="fs-6 text-muted"><?= qty($c['kw']) ?> kW</small></div></div></div><?php endforeach ?></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Project</th><th>Customer</th><th>kW</th><th>Type</th><th>Current stage</th><th>Assigned</th><th class="text-end">Days in stage</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $days = $r['started_at'] ? (int) floor((time() - strtotime($r['started_at'])) / 86400) : null; ?>
        <tr><td><a href="<?= site_url('admin/projects/' . $r['id']) ?>"><?= esc($r['project_no']) ?></a></td><td><?= esc($r['customer']) ?></td><td><?= qty($r['capacity_kw']) ?></td>
            <td><?= status_badge($r['customer_type']) ?> <?= status_badge($r['subsidy_type']) ?></td><td><?= esc(\App\Libraries\Workflow::stageTitle((string) $r['current_stage'])) ?></td>
            <td><?= esc(user_name($r['assigned_to'])) ?></td><td class="text-end <?= $days !== null && $days > 7 ? 'text-danger fw-semibold' : '' ?>"><?= $days ?? '—' ?></td><td><?= status_badge($r['status']) ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No active projects.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
