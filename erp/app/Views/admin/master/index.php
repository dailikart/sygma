<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php if (! empty($back)): ?><a href="<?= site_url($back[0]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= esc($back[1]) ?></a><?php endif ?>
    <form class="d-flex gap-2" method="get"><input name="q" value="<?= esc($q) ?>" class="form-control form-control-sm" placeholder="Search…"><button class="btn btn-sm btn-outline-secondary">Search</button></form>
    <?php if ($canManage && ! empty($import)): ?><a href="<?= site_url('admin/import/' . $import) ?>" class="btn btn-outline-primary ms-auto"><i class="bi bi-upload"></i> Bulk upload</a><?php endif ?>
    <?php if ($canManage): ?><a href="<?= site_url('admin/' . $uri . '/new') ?>" class="btn btn-primary<?= empty($import) ? ' ms-auto' : '' ?>"><i class="bi bi-plus-lg"></i> Add</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover table-sm mb-0 align-middle">
    <thead><tr><?php foreach ($columns as $c): ?><th><?= esc(rtrim(explode('(', $fields[$c][0] ?? ucfirst($c))[0], ' *')) ?></th><?php endforeach ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
        <?php foreach ($columns as $c): $type = $fields[$c][1] ?? 'text'; ?>
            <td><?php
                if ($type === 'bool') {
                    echo $r[$c] ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>';
                } elseif ($type === 'select' && ! array_is_list($fields[$c][2])) {
                    echo esc((string) ($fields[$c][2][$r[$c]] ?? ''));
                } elseif ($type === 'number') {
                    echo str_contains($c, 'price') || str_contains($c, 'balance') ? money($r[$c]) : qty($r[$c]);
                } else {
                    echo esc((string) $r[$c]);
                }
            ?></td>
        <?php endforeach ?>
            <td class="text-end"><?php if ($canManage): ?><a href="<?= site_url('admin/' . $uri . '/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Edit</a><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="<?= count($columns) + 1 ?>" class="text-muted text-center py-4">Nothing here yet.</td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
<?= $this->endSection() ?>
