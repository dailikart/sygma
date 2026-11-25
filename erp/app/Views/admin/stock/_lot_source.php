<?php /** One lot's origin: purchase / supplier bill / PO links, or opening / adjustment. @var array $lot */ ?>
<?php if ($lot['source'] === 'purchase' && $lot['purchase_id']): ?>
    <a href="<?= site_url('admin/purchases/' . $lot['purchase_id']) ?>" class="fw-semibold"><?= esc((string) $lot['purchase_no']) ?></a>
    <?= $lot['bill_no'] ? '<span class="text-muted">· bill</span> ' . esc($lot['bill_no']) : '' ?>
    <?= $lot['purchase_order_id'] ? '<span class="text-muted">·</span> <a href="' . site_url('admin/purchase-orders/' . $lot['purchase_order_id']) . '">' . esc((string) $lot['po_no']) . '</a>' : '' ?>
<?php else: ?>
    <span class="badge text-bg-light border"><?= $lot['source'] === 'opening' ? 'Opening stock' : 'Stock adjustment' ?></span>
<?php endif ?>
