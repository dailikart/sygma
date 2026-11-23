<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Quotations<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $st = \App\Controllers\Admin\Quotations::STATUSES; $badge = ['draft' => 'secondary', 'sent' => 'info', 'accepted' => 'primary', 'rejected' => 'danger', 'converted' => 'success']; ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
    <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="<?= esc($from) ?>"><input type="hidden" name="to" value="<?= esc($to) ?>">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" class="form-control form-control-sm" placeholder="Quote no / name / phone">
        <select name="status" class="form-select form-select-sm"><?= select_options($st, service('request')->getGet('status'), 'All statuses') ?></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button></form>
    <?php if (can('quotations.manage')): ?><a href="<?= site_url('admin/quotations/new') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New Quotation</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover table-sm table-bordered table-grid mb-0 align-middle small">
    <thead><tr><th>Quotation #</th><th>Date</th><th>For</th><th>kW</th><th class="text-end">Total</th><th class="text-end">Subsidy</th><th>Status</th><th>Valid until</th></tr></thead><tbody>
    <?php $sum = 0; foreach ($rows as $r): $sum += $r['total']; $expired = $r['valid_until'] && $r['valid_until'] < date('Y-m-d') && in_array($r['status'], ['draft', 'sent'], true); ?>
        <tr><td class="text-nowrap"><a href="<?= site_url('admin/quotations/' . $r['id']) ?>"><?= esc($r['quote_no']) ?></a><div class="text-muted">by <?= esc(user_name($r['created_by'])) ?></div></td>
            <td class="text-nowrap"><?= fdate($r['quote_date']) ?></td>
            <td><?= esc($r['party_name']) ?> <?= status_badge($r['customer_type']) ?><div class="text-muted"><?= esc((string) $r['party_phone']) ?> <?= $r['customer_id'] ? '' : '<span class="badge text-bg-light border">' . ($r['lead_id'] ? 'Lead' : 'New enquiry') . '</span>' ?></div></td>
            <td><?= qty($r['capacity_kw']) ?></td>
            <td class="text-end text-nowrap"><?= money($r['total']) ?></td>
            <td class="text-end text-nowrap"><?= (float) $r['subsidy_amount'] > 0 ? money($r['subsidy_amount']) : '—' ?></td>
            <td><span class="badge text-bg-<?= $badge[$r['status']] ?? 'light' ?>"><?= esc($st[$r['status']] ?? $r['status']) ?></span><?= $r['viewed_at'] ? ' <i class="bi bi-eye text-success" title="Customer opened the link on ' . fdate($r['viewed_at'], true) . '"></i>' : '' ?></td>
            <td class="text-nowrap <?= $expired ? 'text-danger' : '' ?>"><?= fdate($r['valid_until']) ?><?= $expired ? ' <small>(expired)</small>' : '' ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No quotations in this period.</td></tr><?php endif ?>
    </tbody><tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">Total</td><td class="text-end"><?= money($sum) ?></td><td colspan="3"></td></tr></tfoot>
</table></div></div>
<?= $this->endSection() ?>
