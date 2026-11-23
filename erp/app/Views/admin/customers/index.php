<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Customers<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <form class="d-flex gap-2" method="get">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" class="form-control form-control-sm" placeholder="Search…">
        <select name="type" class="form-select form-select-sm"><?= select_options(['b2c' => 'B2C', 'b2b' => 'B2B'], service('request')->getGet('type'), 'All types') ?></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <?php if (can('customers.manage')): ?><a href="<?= site_url('admin/import/customers') ?>" class="btn btn-outline-primary ms-auto"><i class="bi bi-upload"></i> Bulk upload</a><a href="<?= site_url('admin/customers/new') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Customer</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Name</th><th>Type</th><th>Phone</th><th>City</th><th>GSTIN</th><th>Projects</th><th class="text-end">Balance due</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td><a href="<?= site_url('admin/customers/' . $r['id']) ?>"><?= esc($r['name']) ?></a><?= $r['company'] ? '<br><small class="text-muted">' . esc($r['company']) . '</small>' : '' ?></td>
            <td><?= status_badge($r['type']) ?></td><td><?= esc($r['phone'] ?? '') ?></td><td><?= esc($r['city'] ?? '') ?></td><td><?= esc($r['gstin'] ?? '') ?></td>
            <td><?= (int) $r['projects'] ?></td><td class="text-end <?= $r['due'] > 0 ? 'text-danger' : '' ?>"><?= money($r['due']) ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No customers yet.</td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
<?= $this->endSection() ?>
