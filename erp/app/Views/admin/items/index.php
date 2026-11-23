<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Items<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $req = service('request'); $cats = $fields['category_id'][2]; ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-start">
    <form class="row g-2 flex-grow-1" method="get" style="max-width:980px">
        <div class="col-sm-3"><input name="q" value="<?= esc($req->getGet('q')) ?>" class="form-control form-control-sm" placeholder="Name / code"></div>
        <div class="col-sm-3"><select name="type" class="form-select form-select-sm"><?= select_options($types, $req->getGet('type'), 'All installation types') ?></select></div>
        <div class="col-sm-2"><select name="category" class="form-select form-select-sm"><?= select_options($cats, $req->getGet('category'), 'All categories') ?></select></div>
        <div class="col-sm-2"><select name="location" class="form-select form-select-sm"><?= select_options($locations, $locId ?: '', 'All locations') ?></select></div>
        <div class="col-sm-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
    </form>
    <?php if ($canManage): ?>
    <div class="ms-auto d-flex flex-wrap gap-2 align-items-start">
        <a href="<?= site_url('admin/installation-types') ?>" class="btn btn-sm btn-outline-secondary">Installation types</a>
        <a href="<?= site_url('admin/item-categories') ?>" class="btn btn-sm btn-outline-secondary">Categories</a>
        <a href="<?= site_url('admin/locations') ?>" class="btn btn-sm btn-outline-secondary">Locations</a>
        <a href="<?= site_url('admin/import/items') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Bulk upload</a>
        <a href="<?= site_url('admin/items/new') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add Item</a>
    </div>
    <?php endif ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover table-sm mb-0 align-middle">
    <thead><tr><th>Name</th><th>Installation type</th><th>Category</th><th class="text-end">Purchase price</th><th class="text-end">Sale price</th><th class="text-end"><?= $locId ? 'Stock (' . esc($locations[$locId] ?? '') . ')' : 'Stock' ?></th><th>Unit</th><th>GST &amp; HSN</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $low = $r['min_stock'] > 0 && $r['stock'] < $r['min_stock']; ?>
        <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>">
            <td><?= esc($r['name']) ?><?= $r['sku'] ? ' <small class="text-muted">' . esc($r['sku']) . '</small>' : '' ?></td>
            <td><?= esc($types[$r['installation_type_id']] ?? 'All') ?></td>
            <td><?= esc($cats[$r['category_id']] ?? '') ?></td>
            <td class="text-end"><?= money($r['purchase_price']) ?></td>
            <td class="text-end"><?= money($r['sale_price']) ?></td>
            <td class="text-end <?= $low ? 'text-danger fw-semibold' : '' ?>"><a href="<?= site_url('admin/stock/item/' . $r['id']) ?>" class="text-reset"><?= qty($r['stock']) ?></a></td>
            <td><?= esc($r['unit']) ?></td>
            <td><?= qty($r['tax_percent']) ?>% <?= esc((string) $r['hsn']) ?></td>
            <td class="text-end text-nowrap"><?php if ($canManage): ?><a href="<?= site_url('admin/items/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a><?php endif ?>
                <?php if (can('entries.delete')): ?><form method="post" action="<?= site_url('admin/items/' . $r['id'] . '/delete') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Delete item" data-confirm="Delete item <?= esc($r['name']) ?>? Only possible when its stock is zero and it is not in a kit."><i class="bi bi-trash"></i></button></form><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No items yet. Create installation types and categories first, then add items.</td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
<p class="small text-muted mt-2">Stock is kept per location. Add stock with <a href="<?= site_url('admin/purchases/new') ?>">Purchase</a> (choose "Receive into location") or <a href="<?= site_url('admin/stock/adjust') ?>">Stock Adjustment</a>; see each store in <a href="<?= site_url('admin/stock') ?>">Stock Balance</a>.</p>
<?= $this->endSection() ?>
