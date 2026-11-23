<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= esc($c['title']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $actions = can('entries.delete') || ($c['table'] === 'purchases' && can('purchases.manage')); ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
    <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
        <select name="supplier" class="form-select form-select-sm"><?= select_options($suppliers, service('request')->getGet('supplier'), 'All suppliers') ?></select><button class="btn btn-sm btn-outline-secondary">Filter</button></form>
    <?php if (can('purchases.manage')): ?><a href="<?= site_url('admin/' . $c['uri'] . '/new') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New <?= esc($c['single']) ?></a><?php endif ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Number</th><th>Date</th><th>Supplier</th><?= $c['table'] === 'purchases' ? '<th>Supplier inv. no.</th>' : '' ?><?= $c['table'] === 'purchase_orders' ? '<th>Status</th>' : '' ?><th class="text-end">Total</th><?= $actions ? '<th></th>' : '' ?></tr></thead><tbody>
    <?php $sum = 0; foreach ($rows as $r): if (($r['status'] ?? '') !== 'cancelled') { $sum += $r['total']; } ?>
        <tr><td><a href="<?= site_url('admin/' . $c['uri'] . '/' . $r['id']) ?>"><?= esc($r[$c['no']]) ?></a></td><td><?= fdate($r[$c['date']]) ?></td><td><?= esc($r['supplier']) ?></td>
            <?= $c['table'] === 'purchases' ? '<td>' . esc($r['bill_no'] ?? '') . '</td>' : '' ?><?= $c['table'] === 'purchase_orders' ? '<td>' . status_badge($r['status']) . '</td>' : '' ?>
            <td class="text-end"><?= money($r['total']) ?></td>
            <?php if ($actions): ?><td class="text-end text-nowrap">
                <?php if ($c['table'] === 'purchases' && can('purchases.manage')): ?><a href="<?= site_url('admin/purchase-returns/new?purchase=' . $r['id']) ?>" class="btn btn-sm btn-outline-warning" title="Return items from this purchase"><i class="bi bi-arrow-return-left"></i> Return</a><?php endif ?>
                <?php if (can('entries.delete')): ?><form method="post" action="<?= site_url('admin/' . $c['uri'] . '/' . $r['id'] . '/delete') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Delete" data-confirm="Delete <?= esc($c['single']) ?> <?= esc($r[$c['no']]) ?>?<?= $c['table'] === 'purchases' ? ' Its stock leaves the store and the purchase entry is removed from the accounts.' : ($c['table'] === 'purchase_returns' ? ' The returned stock comes back into the store and the debit note is removed.' : '') ?>"><i class="bi bi-trash"></i></button></form><?php endif ?>
            </td><?php endif ?></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Nothing in this period.</td></tr><?php endif ?>
    </tbody><tfoot><tr class="fw-semibold"><td colspan="<?= $c['table'] === 'purchase_returns' ? 3 : 4 ?>" class="text-end">Total</td><td class="text-end"><?= money($sum) ?></td><?= $actions ? '<td></td>' : '' ?></tr></tfoot>
</table></div></div>
<?= $this->endSection() ?>
