<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Tally Export<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $qs = service('request')->getGet(); unset($qs['export']); ?>
<div class="card card-body mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-end">
        <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
        <form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
            <select name="type" class="form-select form-select-sm"><?= select_options(array_combine($types, $types), $type, 'All voucher types') ?></select><button class="btn btn-sm btn-outline-secondary">Show</button></form>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a href="?<?= esc(http_build_query(['export' => 'ledgers'])) ?>" class="btn btn-sm btn-outline-dark" title="Import once before the vouchers">1. Ledger masters XML</a>
            <a href="?<?= esc(http_build_query($qs + ['export' => 'xml'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-filetype-xml"></i> 2. Vouchers XML</a>
            <a href="?<?= esc(http_build_query($qs + ['export' => 'excel'])) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </div>
    <div class="small text-muted mt-2">In Tally Prime: <strong>Gateway of Tally → Import → Masters</strong> (ledger XML, first time), then <strong>Import → Transactions</strong> (vouchers XML). GST ledger names come from Settings → Tally ledger names.</div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>Type</th><th>Voucher</th><th>Ledger</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead><tbody>
    <?php foreach ($vouchers as $v): foreach ($entries[$v['id']] ?? [] as $i => $e): ?>
        <tr class="<?= $i === 0 ? 'border-top' : '' ?>"><td><?= $i === 0 ? fdate($v['voucher_date']) : '' ?></td><td><?= $i === 0 ? esc($v['voucher_type']) : '' ?></td><td><?= $i === 0 ? esc($v['voucher_no']) : '' ?></td>
            <td><?= esc($e['name']) ?> <small class="text-muted"><?= esc($e['group_name']) ?></small></td><td class="text-end"><?= $e['debit'] > 0 ? money($e['debit'], false) : '' ?></td><td class="text-end"><?= $e['credit'] > 0 ? money($e['credit'], false) : '' ?></td></tr>
    <?php endforeach; endforeach ?>
    <?php if (! $vouchers): ?><tr><td colspan="6" class="text-center text-muted py-4">No vouchers in this period.</td></tr><?php endif ?>
</tbody></table></div></div>
<p class="small text-muted mt-2"><?= count($vouchers) ?> voucher(s).</p>
<?= $this->endSection() ?>
