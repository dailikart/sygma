<?php $stages = config('Workflow')->stages; ?>
<div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Project</th><th>Customer</th><th>Stage</th><th>kW</th><th>Planned</th><th>Assigned</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
        <tr>
            <td><?= esc($t['project_no']) ?> <?= status_badge($t['customer_type']) ?> <?= $t['subsidy_type'] === 'subsidy' ? status_badge('subsidy') : '' ?></td>
            <td><?= esc($t['customer']) ?><br><small class="text-muted"><?= esc($t['city'] ?? '') ?> <?= esc($t['phone'] ?? '') ?></small></td>
            <td><i class="bi <?= $stages[$t['stage']]['icon'] ?? '' ?>"></i> <?= esc($stages[$t['stage']]['title'] ?? $t['stage']) ?></td>
            <td><?= qty($t['capacity_kw']) ?></td>
            <td><?= fdate($t['planned_date']) ?></td>
            <td><?= user_name($t['assigned_to']) ?></td>
            <td class="text-end"><a href="<?= site_url('admin/projects/' . $t['project_id']) ?>" class="btn btn-sm btn-primary">Open</a></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $tasks): ?><tr><td colspan="7" class="text-center text-muted py-4">No pending tasks. 🎉</td></tr><?php endif ?>
    </tbody>
</table>
</div>
