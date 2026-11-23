<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Subsidy Status<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $labels = config('Workflow')->subsidyStatuses; ?>
<?= view('admin/reports/_head', ['extra' => '<form method="get" class="d-flex gap-2"><select name="status" class="form-select form-select-sm">' . select_options($labels, service('request')->getGet('status'), 'All statuses') . '</select><button class="btn btn-sm btn-outline-secondary">Filter</button></form>']) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Project</th><th>Customer</th><th>kW</th><th>Commissioned</th><th>Application no.</th><th>Status</th><th class="text-end">Amount</th><th>Received</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td><a href="<?= site_url('admin/projects/' . $r['id'] . '#stage-subsidy') ?>"><?= esc($r['project_no']) ?></a></td><td><?= esc($r['customer']) ?><br><small class="text-muted"><?= esc((string) $r['phone']) ?></small></td>
            <td><?= qty($r['capacity_kw']) ?></td><td><?= fdate($r['commissioned_on']) ?></td><td><?= esc((string) ($r['data']['application_no'] ?? '')) ?></td>
            <td><?= status_badge($r['subsidy_status']) ?></td><td class="text-end"><?= ! empty($r['data']['subsidy_amount']) ? money($r['data']['subsidy_amount']) : '' ?></td><td><?= fdate($r['data']['received_date'] ?? null) ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No subsidy projects.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
