<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Day Book<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
    <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
        <select name="type" class="form-select form-select-sm"><?= select_options(array_combine($t = ['Sales', 'Receipt', 'Purchase', 'Debit Note', 'Payment', 'Journal', 'Contra'], $t), service('request')->getGet('type'), 'All voucher types') ?></select><button class="btn btn-sm btn-outline-secondary">Filter</button></form>
    <?php if (can('accounts.manage')): ?><a href="<?= site_url('admin/accounts/journal') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Journal / Contra</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>Type</th><th>Voucher</th><th>Ledger</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th></th></tr></thead><tbody>
    <?php $dr = 0; $cr = 0; foreach ($vouchers as $v): $lines = $entries[$v['id']] ?? []; foreach ($lines as $i => $e): $dr += $e['debit']; $cr += $e['credit']; ?>
        <tr class="<?= $i === 0 ? 'border-top' : '' ?>">
            <td><?= $i === 0 ? fdate($v['voucher_date']) : '' ?></td><td><?= $i === 0 ? esc($v['voucher_type']) : '' ?></td><td><?= $i === 0 ? '<a href="' . site_url('admin/vouchers/' . $v['id'] . '/print') . '" target="_blank" title="Print">' . esc($v['voucher_no']) . ' <i class="bi bi-printer small"></i></a>' : '' ?></td>
            <td><a href="<?= site_url('admin/ledgers/' . $e['ledger_id']) ?>" class="text-reset"><?= esc($e['name']) ?></a></td>
            <td class="text-end"><?= $e['debit'] > 0 ? money($e['debit'], false) : '' ?></td><td class="text-end"><?= $e['credit'] > 0 ? money($e['credit'], false) : '' ?></td>
            <td class="text-end"><?php if ($i === 0 && $v['ref_type'] === 'manual' && can('accounts.manage')): ?><form method="post" action="<?= site_url('admin/accounts/journal/' . $v['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0" data-confirm="Delete this voucher?"><i class="bi bi-trash"></i></button></form><?php endif ?></td></tr>
        <?php endforeach; if ($v['narration']): ?><tr><td colspan="3"></td><td colspan="4" class="small text-muted fst-italic"><?= esc($v['narration']) ?></td></tr><?php endif ?>
    <?php endforeach ?>
    <?php if (! $vouchers): ?><tr><td colspan="7" class="text-center text-muted py-4">No vouchers in this period.</td></tr><?php endif ?>
    </tbody><tfoot class="fw-semibold"><tr><td colspan="4" class="text-end">Total</td><td class="text-end"><?= money($dr, false) ?></td><td class="text-end"><?= money($cr, false) ?></td><td></td></tr></tfoot>
</table></div></div>
<?= $this->endSection() ?>
