<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Stock Balance<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
        <select name="location" class="form-select form-select-sm" style="max-width:220px"><?= select_options($allLocations, $locId ?: '', 'All locations') ?></select>
        <select name="category" class="form-select form-select-sm" style="max-width:220px"><?= select_options($categories, service('request')->getGet('category'), 'All categories') ?></select>
        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="instock" value="1" id="instock" <?= $inStockOnly ? 'checked' : '' ?>><label class="form-check-label small" for="instock">Only items in stock</label></div>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <a href="<?= site_url('admin/stock/lots') ?>" class="btn btn-outline-secondary ms-auto"><i class="bi bi-hourglass-split"></i> Stock ageing</a>
    <?php if (can('inventory.manage')): ?><a href="<?= site_url('admin/stock/adjust') ?>" class="btn btn-outline-primary">Opening stock / adjustment</a><?php endif ?>
    <?php if (can('purchases.manage')): ?><a href="<?= site_url('admin/purchases/new') ?>" class="btn btn-primary">Add Purchase</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-sm table-hover mb-0 align-middle">
    <thead><tr><th>Item</th><th>Category</th>
        <?php foreach ($locations as $name): ?><th class="text-end"><?= esc($name) ?></th><?php endforeach ?>
        <th class="text-end"><?= $locId ? 'In this store' : 'In stock' ?></th><th class="text-end">In transit</th><th class="text-end">Value (FIFO)</th><th></th></tr></thead>
    <tbody>
    <?php $totalValue = 0; $shown = 0; foreach ($items as $it): $b = $balances[$it['id']] ?? [];
        $total = 0; foreach ($locations as $lid => $_) { $total += $b[$lid] ?? 0; }
        if ($inStockOnly && $total <= 0) { continue; }
        $shown++; $value = $fifoValue[$it['id']] ?? $total * $it['purchase_price']; $totalValue += $value;
        $low = ! $locId && $it['min_stock'] > 0 && $total < $it['min_stock']; ?>
        <tr>
            <td><a href="<?= site_url('admin/stock/item/' . $it['id']) ?>"><?= esc($it['name']) ?></a> <small class="text-muted"><?= esc($it['unit']) ?></small></td>
            <td><small><?= esc($it['category'] ?? '') ?><?= $it['type'] ? ' · ' . esc($it['type']) : '' ?></small></td>
            <?php foreach ($locations as $id => $name): ?><td class="text-end"><?= qty($b[$id] ?? 0) ?></td><?php endforeach ?>
            <td class="text-end fw-semibold <?= $low ? 'text-danger' : '' ?>"><?= qty($total) ?><?= $low ? ' <i class="bi bi-exclamation-triangle" title="Below minimum ' . qty($it['min_stock']) . '"></i>' : '' ?></td>
            <td class="text-end text-info"><?= qty($transit[$it['id']] ?? 0) ?></td>
            <td class="text-end"><?= money($value) ?></td>
            <td><a href="<?= site_url('admin/stock/item/' . $it['id'] . '/lots') ?>" class="btn btn-sm btn-outline-secondary py-0" title="Which PO / purchase bill this stock came from">Lots</a></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $shown): ?><tr><td colspan="<?= count($locations) + 6 ?>" class="text-center text-muted py-4"><?= $items ? 'No items match this filter.' : 'No items yet.' ?></td></tr><?php endif ?>
    </tbody>
    <tfoot><tr class="fw-semibold"><td colspan="<?= count($locations) + 4 ?>" class="text-end">Stock value</td><td class="text-end"><?= money($totalValue) ?></td><td></td></tr></tfoot>
</table>
</div></div>
<?php if ($locId): ?><p class="small text-muted mt-2 mb-0">Showing stock in <strong><?= esc($allLocations[$locId]) ?></strong> only. Value = this store's stock at the rates of the purchases it came from.</p><?php endif ?>
<p class="small text-muted mt-2">Value is FIFO: the stock still in store at the rate of the purchase bill it came from (the oldest stock goes out first). Click <b>Lots</b> to see which PO and purchase bill each piece came from, or open the <a href="<?= site_url('admin/stock/lots') ?>">stock ageing</a> report.<br>"In transit" = loaded and dispatched from the store but not yet marked delivered at the site.</p>
<?= $this->endSection() ?>
