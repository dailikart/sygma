<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Dispatch<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="btn-group mb-3">
    <?php foreach (['' => 'Default', 'draft' => 'To dispatch', 'dispatched' => 'On the road', 'delivered' => 'Delivered'] as $k => $l): ?>
        <a href="<?= site_url('admin/dispatches' . ($k ? '?status=' . $k : '')) ?>" class="btn btn-sm <?= (string) service('request')->getGet('status') === $k ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $l ?></a>
    <?php endforeach ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Dispatch</th><th>Project</th><th>Customer / site</th><th>From</th><th>Vehicle</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td><?= esc($r['dispatch_no']) ?><br><small class="text-muted"><?= fdate($r['created_at']) ?></small></td>
            <td><a href="<?= site_url('admin/projects/' . $r['project_id']) ?>"><?= esc($r['project_no']) ?></a></td>
            <td><?= esc($r['customer']) ?><br><small class="text-muted"><?= esc(($r['city'] ?? '') . ' ' . ($r['phone'] ?? '')) ?></small></td>
            <td><?= esc($r['location']) ?></td><td><?= esc((string) $r['vehicle_no']) ?></td><td><?= status_badge($r['status']) ?></td>
            <td class="text-end"><a href="<?= site_url('admin/dispatches/' . $r['id']) ?>" class="btn btn-sm btn-primary">Open</a></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Nothing here.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
