<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Item Groups / Kits<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex mb-3"><p class="text-muted mb-0">The set of items for each system size. The store's dispatch list is generated from the project's kit.</p>
<?php if (can('inventory.manage')): ?><a href="<?= site_url('admin/kits/new') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New Item Group</a><?php endif ?></div>
<div class="card"><table class="table table-hover mb-0">
    <thead><tr><th>Name</th><th>Installation type</th><th>Capacity</th><th>Items</th><th>Active</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= esc($r['name']) ?></td><td><?= esc($r['type'] ?? 'All') ?></td><td><?= qty($r['capacity_kw']) ?> kW</td><td><?= (int) $r['line_count'] ?></td>
        <td><?= $r['is_active'] ? 'Yes' : 'No' ?></td><td class="text-end"><?php if (can('inventory.manage')): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/kits/' . $r['id'] . '/edit') ?>">Edit</a><?php endif ?></td></tr><?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No item groups yet. Create one for each size you install (e.g. 3 kW, 4 kW, 5 kW).</td></tr><?php endif ?>
</tbody></table></div>
<?= $this->endSection() ?>
