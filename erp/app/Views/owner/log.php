<?= $this->extend('owner/layout') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3">Activity</h1>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0 small">
    <thead><tr><th>When</th><th>Who</th><th>Company</th><th>What</th><th>Detail</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td class="text-nowrap"><?= date('d-m-Y H:i', strtotime($r['created_at'])) ?></td><td><?= esc((string) $r['owner']) ?></td>
        <td><?= $r['company_id'] && $r['company'] ? '<a href="' . site_url('owner/companies/' . $r['company_id']) . '">' . esc($r['company']) . '</a>' : esc((string) $r['code']) ?></td>
        <td><?= esc(str_replace('_', ' ', $r['action'])) ?></td><td><?= $r['action'] === 'support_login' ? '' : esc((string) $r['detail']) ?></td><td class="text-muted"><?= esc((string) $r['ip']) ?></td></tr><?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-3">Nothing yet.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
