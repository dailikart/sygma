<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Supplier-wise Summary<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('admin/reports/_head', ['from' => $from, 'to' => $to]) ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Supplier</th><th class="text-end">Opening</th><th class="text-end">Purchases</th><th class="text-end">Returns</th><th class="text-end">Paid</th><th class="text-end">Balance payable</th><th class="text-center">Open POs</th><th></th></tr></thead><tbody>
    <?php $t = array_fill_keys(['opening', 'purchases', 'returns', 'paid', 'balance'], 0); foreach ($rows as $r): foreach ($t as $k => $_) { $t[$k] += $r[$k]; } ?>
        <tr><td><?= esc($r['name']) ?><?= $r['gstin'] ? '<br><small class="text-muted">' . esc($r['gstin']) . '</small>' : '' ?></td>
            <td class="text-end"><?= money($r['opening'], false) ?></td><td class="text-end"><?= money($r['purchases'], false) ?></td><td class="text-end"><?= money($r['returns'], false) ?></td>
            <td class="text-end"><?= money($r['paid'], false) ?></td><td class="text-end fw-semibold <?= $r['balance'] > 0 ? 'text-danger' : '' ?>"><?= money($r['balance']) ?></td>
            <td class="text-center"><?= $r['open_po'] ? '<a href="' . site_url('admin/purchase-orders?supplier=' . $r['id']) . '">' . $r['open_po'] . '</a>' : '' ?></td>
            <td class="text-end text-nowrap"><a href="<?= site_url('admin/purchases?supplier=' . $r['id'] . '&from=' . $from . '&to=' . $to) ?>" class="btn btn-sm btn-link">Purchases</a>
                <?php if (can('accounts.view')): ?><a href="<?= site_url('admin/reports/suppliers/' . $r['id'] . '?from=' . $from . '&to=' . $to) ?>" class="btn btn-sm btn-link">Statement</a><?php endif ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No supplier activity in this period.</td></tr><?php endif ?>
    </tbody><tfoot class="fw-semibold"><tr><td>Total</td><td class="text-end"><?= money($t['opening'], false) ?></td><td class="text-end"><?= money($t['purchases'], false) ?></td><td class="text-end"><?= money($t['returns'], false) ?></td><td class="text-end"><?= money($t['paid'], false) ?></td><td class="text-end"><?= money($t['balance']) ?></td><td colspan="2"></td></tr></tfoot>
</table></div></div>
<?= $this->endSection() ?>
