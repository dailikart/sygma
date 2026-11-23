<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Project Profit<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('admin/reports/_head', ['from' => $from, 'to' => $to, 'extra' => '<form method="get" class="d-flex gap-2"><input type="hidden" name="from" value="' . esc($from) . '"><input type="hidden" name="to" value="' . esc($to) . '"><select name="type" class="form-select form-select-sm">' . select_options(['b2c' => 'B2C', 'b2b' => 'B2B'], service('request')->getGet('type'), 'B2B / B2C') . '</select><button class="btn btn-sm btn-outline-secondary">Filter</button></form>']) ?>
<?php
$t = ['revenue' => 0, 'material' => 0, 'expenses' => 0, 'eb' => 0, 'cost' => 0, 'profit' => 0, 'kw' => 0];
foreach ($rows as $r) { foreach (['revenue', 'material', 'expenses', 'eb', 'cost', 'profit'] as $k) { $t[$k] += $r[$k]; } $t['kw'] += (float) $r['capacity_kw']; }
$margin = $t['revenue'] > 0 ? round($t['profit'] / $t['revenue'] * 100, 1) : null;
$card = static fn ($label, $value, $cls = '') => '<div class="col-6 col-md"><div class="card card-body stat-card h-100"><div class="small text-muted">' . $label . '</div><div class="value ' . $cls . '">' . $value . '</div></div></div>';
?>
<div class="row g-3 mb-3">
    <?= $card('Projects', count($rows) . ' <small class="fs-6 text-muted">' . qty($t['kw']) . ' kW</small>') ?>
    <?= $card('Invoice value (before GST)', money($t['revenue'])) ?>
    <?= $card('Total cost', money($t['cost'])) ?>
    <?= $card('Profit', money($t['profit']), $t['profit'] < 0 ? 'text-danger' : 'text-success') ?>
    <?= $card('Margin', $margin === null ? '—' : $margin . '%', ($margin ?? 0) < 0 ? 'text-danger' : '') ?>
    <?= $card('Profit per kW', $t['kw'] > 0 ? money($t['profit'] / $t['kw']) : '—') ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover table-bordered table-grid mb-0 align-middle small">
    <thead><tr><th>Project</th><th>Customer</th><th>kW</th><th>Stage</th><th class="text-end">Invoice value<br><span class="fw-normal">before GST</span></th><th class="text-end">Material</th><th class="text-end">Expenses</th><th class="text-end lh-sm">EB charges<br><span class="fw-normal">paid by us</span></th><th class="text-end">Profit</th><th class="text-end">Margin</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td class="text-nowrap"><a href="<?= site_url('admin/projects/' . $r['id']) ?>"><?= esc($r['project_no']) ?></a><div class="text-muted"><a class="text-reset" href="<?= site_url('admin/invoices/' . $r['invoice_id']) ?>"><?= esc($r['invoice_no']) ?></a> · <?= fdate($r['invoice_date']) ?></div></td>
            <td><?= esc($r['customer']) ?> <?= status_badge($r['customer_type']) ?></td><td><?= qty($r['capacity_kw']) ?></td>
            <td><?= esc(\App\Libraries\Workflow::stageTitle((string) $r['current_stage'])) ?></td>
            <td class="text-end text-nowrap"><?= money($r['revenue']) ?></td>
            <td class="text-end text-nowrap"><?= $r['material'] > 0 ? money($r['material']) : '<span class="text-muted" title="Nothing dispatched yet">—</span>' ?></td>
            <td class="text-end text-nowrap"><?= $r['expenses'] > 0 ? money($r['expenses']) : '—' ?></td>
            <td class="text-end text-nowrap"><?= $r['eb'] > 0 ? money($r['eb']) : '—' ?></td>
            <td class="text-end text-nowrap fw-semibold <?= $r['profit'] < 0 ? 'text-danger' : 'text-success' ?>"><?= money($r['profit']) ?></td>
            <td class="text-end"><?= $r['margin'] === null ? '—' : $r['margin'] . '%' ?></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No projects with invoices in this period.</td></tr><?php endif ?>
    </tbody>
    <tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">Total</td><td class="text-end"><?= money($t['revenue']) ?></td><td class="text-end"><?= money($t['material']) ?></td><td class="text-end"><?= money($t['expenses']) ?></td><td class="text-end"><?= money($t['eb']) ?></td><td class="text-end <?= $t['profit'] < 0 ? 'text-danger' : 'text-success' ?>"><?= money($t['profit']) ?></td><td class="text-end"><?= $margin === null ? '—' : $margin . '%' ?></td></tr></tfoot>
</table></div></div>
<p class="small text-muted mt-2"><strong>How it is worked out:</strong> invoice value before GST − material dispatched to the site (at the average purchase rate) − expenses linked to the project (Accounts → Expenses → choose the project) − EB / meter charges when the company pays them (amount on the project's EB / Net Meter card). Material shows "—" until it is dispatched, so the profit of a project is final once all material has gone.</p>
<?= $this->endSection() ?>
