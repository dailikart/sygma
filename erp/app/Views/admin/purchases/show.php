<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= esc($c['single']) ?> <?= esc($doc[$c['no']]) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?= isset($doc['status']) ? status_badge($doc['status']) : '' ?>
    <div class="ms-auto d-flex gap-2">
        <a href="<?= site_url('admin/' . $c['uri'] . '/' . $doc['id'] . '/pdf') ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Print / PDF</a>
        <?php if ($c['table'] === 'purchase_orders' && can('purchases.manage') && in_array($doc['status'], ['open', 'partial'], true)): ?>
            <a href="<?= site_url('admin/purchases/new?po=' . $doc['id']) ?>" class="btn btn-success"><i class="bi bi-box-arrow-in-down"></i> Receive goods (create purchase)</a>
            <?php if ($doc['status'] === 'open'): ?><form method="post" action="<?= site_url('admin/purchase-orders/' . $doc['id'] . '/cancel') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Cancel this purchase order?">Cancel PO</button></form><?php endif ?>
        <?php endif ?>
        <?php if (can('entries.delete')): ?><form method="post" action="<?= site_url('admin/' . $c['uri'] . '/' . $doc['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Delete <?= esc($c['single']) ?> <?= esc($doc[$c['no']]) ?> permanently?"><i class="bi bi-trash"></i> Delete</button></form><?php endif ?>
        <?php if ($c['table'] === 'purchases' && can('purchases.manage')): ?>
            <a href="<?= site_url('admin/purchase-returns/new?purchase=' . $doc['id']) ?>" class="btn btn-outline-warning"><i class="bi bi-arrow-return-left"></i> Return items</a>
        <?php endif ?>
    </div>
</div>
<div class="card card-body">
    <div class="row mb-3">
        <div class="col-sm-6"><span class="text-muted small">Supplier</span><br><strong><?= esc($supplier['name']) ?></strong><br><span class="small"><?= nl2br(esc((string) $supplier['address'])) ?><?= $supplier['gstin'] ? '<br>GSTIN: ' . esc($supplier['gstin']) : '' ?></span></div>
        <div class="col-sm-6 text-sm-end small">Number: <strong><?= esc($doc[$c['no']]) ?></strong><br>Date: <?= fdate($doc[$c['date']]) ?>
            <?= ! empty($doc['bill_no']) ? '<br>Supplier invoice: ' . esc($doc['bill_no']) . ' dt. ' . fdate($doc['bill_date']) : '' ?><?= ! empty($against) ? '<br>Against purchase ' . esc($against['purchase_no']) . '<br>Supplier invoice: ' . esc((string) $against['bill_no']) . ' dt. ' . fdate($against['bill_date']) : '' ?><?= $location ? '<br>Location: ' . esc($location['name']) : '' ?>
            <?= ! empty($doc['expected_date']) ? '<br>Expected: ' . fdate($doc['expected_date']) : '' ?></div>
    </div>
    <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><?= $c['table'] === 'purchase_orders' ? '<th class="text-end">Received</th>' : '' ?><th class="text-end">Rate</th><th class="text-end">GST</th><th class="text-end">Amount</th></tr></thead><tbody>
        <?php foreach ($lines as $n => $l): ?><tr><td><?= $n + 1 ?></td><td><?= esc($l['name']) ?></td><td class="text-end"><?= qty($l['qty']) ?> <?= esc($l['unit']) ?></td>
            <?= $c['table'] === 'purchase_orders' ? '<td class="text-end">' . qty($l['received_qty']) . '</td>' : '' ?>
            <td class="text-end"><?= money($l['rate'], false) ?></td><td class="text-end"><?= qty($l['tax_percent']) ?>%</td><td class="text-end"><?= money($l['amount'], false) ?></td></tr><?php endforeach ?>
        </tbody></table></div>
    <div class="row justify-content-end"><div class="col-md-4"><table class="table table-sm">
        <tr><td class="text-end">Sub total</td><td class="text-end"><?= money($doc['subtotal']) ?></td></tr>
        <tr><td class="text-end"><?= $doc['gst_type'] === 'inter' ? 'IGST' : 'CGST + SGST' ?></td><td class="text-end"><?= money($doc['tax_total']) ?></td></tr>
        <tr class="fw-bold"><td class="text-end">Total</td><td class="text-end"><?= money($doc['total']) ?></td></tr></table></div></div>
    <?php $note = $doc['notes'] ?? $doc['reason'] ?? ''; if ($note): ?><p class="small text-muted mb-0"><?= nl2br(esc($note)) ?></p><?php endif ?>
</div>
<?= $this->endSection() ?>
