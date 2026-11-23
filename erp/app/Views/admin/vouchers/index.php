<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= esc($c['title']) ?><?= $c['table'] === 'supplier_payments' ? 's' : '' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
    <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
        <select name="head" class="form-select form-select-sm"><?= select_options($heads, service('request')->getGet('head'), 'All') ?></select><button class="btn btn-sm btn-outline-secondary">Filter</button></form>
    <?php $qs = service('request')->getGet(); unset($qs['export']); ?>
    <a href="?<?= esc(http_build_query($qs + ['from' => $from, 'to' => $to, 'export' => 'excel'])) ?>" class="btn btn-outline-success ms-auto"><i class="bi bi-file-earmark-excel"></i> Excel</a>
    <?php if (can('accounts.manage')): ?><a href="<?= site_url('admin/' . $c['uri'] . '/new') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add <?= esc($c['title']) ?></a><?php endif ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Voucher</th><th>Date</th><th><?= esc($c['headLabel']) ?></th><?= $c['party'] ? '<th>' . ($c['party'] === 'paid_to' ? 'Paid to' : 'Received from') . '</th>' : '' ?><th>Account</th><th>Reference</th><th>By</th><th class="text-end">Amount</th><th></th></tr></thead><tbody>
    <?php $sum = 0; foreach ($rows as $r): $sum += $r['amount']; ?>
        <tr><td><a href="<?= site_url('admin/vouchers/print/' . ['incomes' => 'income', 'expenses' => 'expense', 'supplier_payments' => 'supplier_payment'][$c['table']] . '/' . $r['id']) ?>" target="_blank"><?= esc($r['voucher_no']) ?></a></td><td><?= fdate($r[$c['date']]) ?></td><td><?= esc($r['head']) ?></td>
            <?= $c['party'] ? '<td>' . esc((string) $r[$c['party']]) . '</td>' : '' ?><td><?= esc($r['account']) ?><?= isset($r['mode']) ? ' <small class="text-muted">' . strtoupper($r['mode']) . '</small>' : '' ?></td>
            <td><?= esc((string) $r['reference']) ?><?= ! empty($r['notes']) ? '<br><small class="text-muted">' . esc($r['notes']) . '</small>' : '' ?></td>
            <td class="small text-muted"><?= esc(user_name($r['created_by'])) ?></td>
            <td class="text-end"><?= money($r['amount']) ?></td>
            <td class="text-end text-nowrap">
                <?php if (! empty($r['attachment'])): ?><i class="bi bi-paperclip" title="Bill attached"></i><?php endif ?>
                <a href="<?= site_url('admin/vouchers/print/' . ['incomes' => 'income', 'expenses' => 'expense', 'supplier_payments' => 'supplier_payment'][$c['table']] . '/' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-link" title="Print voucher"><i class="bi bi-printer"></i></a>
                <?php if (can('accounts.manage')): ?><form method="post" action="<?= site_url('admin/' . $c['uri'] . '/' . $r['id'] . '/delete') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger" data-confirm="Delete this entry? Its accounts voucher is removed too."><i class="bi bi-trash"></i></button></form><?php endif ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-4">Nothing in this period.</td></tr><?php endif ?>
    </tbody><tfoot><tr class="fw-semibold"><td colspan="<?= $c['party'] ? 7 : 6 ?>" class="text-end">Total</td><td class="text-end"><?= money($sum) ?></td><td></td></tr></tfoot>
</table></div></div>
<?= $this->endSection() ?>
