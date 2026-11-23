<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>User Log<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $req = service('request'); $qs = $req->getGet(); unset($qs['export']); ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
    <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
        <input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
        <select name="user" class="form-select form-select-sm" style="max-width:180px"><?= select_options($users, $req->getGet('user'), 'All users') ?></select>
        <select name="type" class="form-select form-select-sm" style="max-width:170px"><?= select_options(['changes' => 'Entries & changes', 'logins' => 'Logins / logouts'], $req->getGet('type'), 'Everything') ?></select>
        <input name="q" value="<?= esc($req->getGet('q')) ?>" class="form-control form-control-sm" style="max-width:200px" placeholder="Search (INV/…, name)">
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <a href="?<?= esc(http_build_query($qs + ['export' => 'excel'])) ?>" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-file-earmark-excel"></i> Excel</a>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
    <thead><tr><th style="width:170px">Date &amp; time</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
    <?php foreach ($rows as $r):
        $icon = match ($r['action']) { 'login' => 'bi-box-arrow-in-right text-success', 'logout' => 'bi-box-arrow-right text-secondary', 'failed_login' => 'bi-shield-exclamation text-danger', default => 'bi-pencil-square text-primary' };
        $label = match (true) {
            $r['entity'] === 'audit' && str_contains($r['action'], '::') => ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', explode('::', $r['action'])[0]))),
            $r['entity'] !== 'audit' => ucfirst($r['entity']) . ': ' . str_replace('_', ' ', $r['action']),
            default => ucfirst(str_replace('_', ' ', $r['action'])),
        };
        $link = $r['entity'] === 'project' ? site_url('admin/projects/' . $r['entity_id']) : ($r['entity'] === 'invoice' ? site_url('admin/invoices/' . $r['entity_id']) : null); ?>
        <tr><td class="small"><?= fdate($r['created_at'], true) ?></td>
            <td><?= esc(user_name($r['user_id'])) ?></td>
            <td class="text-nowrap"><i class="bi <?= $icon ?>"></i> <?= esc($label) ?></td>
            <td class="small"><?= $link ? '<a href="' . $link . '">' . esc($r['description']) . '</a>' : esc($r['description']) ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="4" class="text-center text-muted py-4">No activity in this period.</td></tr><?php endif ?>
</tbody></table></div></div>
<p class="small text-muted mt-2">Every saved entry, change, delete, login and failed login is recorded with the user and IP address. Showing up to 2,000 rows; narrow the dates or user for more.</p>
<?= $this->endSection() ?>
