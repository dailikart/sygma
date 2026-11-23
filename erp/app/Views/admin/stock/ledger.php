<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Stock Ledger: <?= esc($item['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>Location</th><th>Type</th><th>Reference</th><th>Note</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th><?= can('entries.delete') ? '<th></th>' : '' ?></tr></thead><tbody>
    <?php $bal = 0; $links = ['purchase' => 'purchases', 'purchase_return' => 'purchase-returns', 'dispatch' => 'dispatches'];
    foreach ($rows as $r): $bal += $r['qty']; ?>
        <tr><td><?= fdate($r['created_at'], true) ?></td><td><?= esc($r['location']) ?></td><td><?= esc(ucfirst(str_replace('_', ' ', $r['type']))) ?></td>
            <td><?php if (isset($links[$r['ref_type']]) && $r['ref_id']): ?><a href="<?= site_url('admin/' . $links[$r['ref_type']] . '/' . $r['ref_id']) ?>">#<?= $r['ref_id'] ?></a><?php endif ?></td>
            <td><?= esc($r['note'] ?? '') ?></td><td class="text-end text-success"><?= $r['qty'] > 0 ? qty($r['qty']) : '' ?></td>
            <td class="text-end text-danger"><?= $r['qty'] < 0 ? qty(-$r['qty']) : '' ?></td><td class="text-end"><?= qty($bal) ?></td>
            <?php if (can('entries.delete')): ?><td class="text-end"><?php if (in_array($r['type'], ['opening', 'adjustment'], true)): ?><form method="post" action="<?= site_url('admin/stock/movements/' . $r['id'] . '/delete') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger py-0" title="Delete this line" data-confirm="Delete this <?= esc($r['type']) ?> line of <?= qty($r['qty']) ?>?"><i class="bi bi-trash"></i></button></form><?php endif ?></td><?php endif ?></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="9" class="text-muted text-center py-3">No movements yet.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
