<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $card = static fn ($label, $value, $icon, $url = null, $cls = 'primary') =>
    '<div class="col-6 col-md-3"><a class="text-decoration-none text-reset" href="' . ($url ? site_url($url) : '#') . '"><div class="card stat-card h-100"><div class="card-body">'
    . '<div class="text-muted small"><i class="bi ' . $icon . ' text-' . $cls . '"></i> ' . esc($label) . '</div><div class="value">' . $value . '</div></div></div></a></div>'; ?>
<div class="row g-3 mb-4">
    <?php if (isset($leadsOpen)): ?>
        <?= $card('Open leads', $leadsOpen, 'bi-megaphone', 'admin/leads') ?>
        <?= $card('Follow-ups due', $followToday, 'bi-telephone', 'admin/leads?due=1', 'danger') ?>
    <?php endif ?>
    <?php if (isset($salesMonth)): ?>
        <?= $card('Sales this month', money($salesMonth), 'bi-receipt', 'admin/invoices', 'success') ?>
        <?= $card('Collected this month', money($collectedMonth), 'bi-cash', 'admin/payments', 'success') ?>
        <?= $card('Receivable', money($receivable), 'bi-hourglass-split', 'admin/invoices?status=unpaid', 'warning') ?>
    <?php endif ?>
    <?php if (isset($onHold)): ?>
        <?= $card('Projects on hold', $onHold, 'bi-pause-circle', 'admin/projects?status=on_hold', 'warning') ?>
    <?php endif ?>
</div>

<?php if (isset($invTotals)): $all = '&from=2000-01-01&to=' . date('Y-m-d'); $other = (int) ($clientsByMode[''] ?? 0); ?>
<div class="card mb-4"><div class="card-header bg-white fw-semibold d-flex">Clients &amp; invoices <span class="ms-auto small text-muted fw-normal">all invoices, excluding cancelled</span></div><div class="card-body"><div class="row g-3">
    <?= $card('Cash clients', (int) ($clientsByMode['cash'] ?? 0), 'bi-cash-coin', 'admin/invoices?finance=cash' . $all, 'success') ?>
    <?= $card('Bank loan clients', (int) ($clientsByMode['loan'] ?? 0), 'bi-bank', 'admin/invoices?finance=loan' . $all, 'primary') ?>
    <?= $card('Total invoice value', money($invTotals['total']) . '<div class="text-muted fw-normal" style="font-size:.8rem">' . $invTotals['n'] . ' invoice(s)' . ($other ? ' · ' . $other . ' client(s) without cash/loan set' : '') . '</div>', 'bi-receipt', 'admin/invoices?x=1' . $all, 'secondary') ?>
    <div class="col-6 col-md-3"><div class="card stat-card h-100"><div class="card-body">
        <div class="text-muted small"><i class="bi bi-check2-circle text-success"></i> Paid</div><div class="value text-success"><?= money($invTotals['paid']) ?></div>
        <div class="text-muted small mt-1"><i class="bi bi-hourglass-split text-danger"></i> Outstanding</div><div class="value text-danger"><a class="text-reset text-decoration-none" href="<?= site_url('admin/invoices?status=unpaid' . $all) ?>"><?= money($invTotals['due']) ?></a></div>
        <?php if ($invTotals['total'] > 0): $pct = round($invTotals['paid'] / $invTotals['total'] * 100); ?>
        <div class="progress mt-2" style="height:6px" title="<?= $pct ?>% collected"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div><div class="small text-muted"><?= $pct ?>% collected</div>
        <?php endif ?>
    </div></div></div>
</div></div></div>
<?php endif ?>

<?php if (isset($stageCounts)): ?>
<div class="card mb-4"><div class="card-header bg-white fw-semibold">Active projects by stage</div><div class="card-body">
    <div class="stage-steps">
    <?php foreach (config('Workflow')->stages as $key => $s): ?>
        <a class="stage-step text-decoration-none text-reset" href="<?= site_url('admin/projects?stage=' . $key) ?>"><i class="bi <?= $s['icon'] ?>"></i> <?= esc($s['title']) ?><div class="fs-4 fw-bold"><?= (int) ($stageCounts[$key] ?? 0) ?></div></a>
    <?php endforeach ?>
    </div>
</div></div>
<?php endif ?>

<div class="card mb-4"><div class="card-header bg-white fw-semibold d-flex">My tasks <a class="ms-auto small" href="<?= site_url('admin/tasks') ?>">View all</a></div>
    <?= view('admin/partials/task_table', ['tasks' => array_slice($tasks, 0, 10)]) ?>
</div>

<?php if (! empty($lowStock)): ?>
<div class="card"><div class="card-header bg-white fw-semibold text-danger"><i class="bi bi-exclamation-triangle"></i> Low stock</div>
<table class="table table-sm mb-0"><thead><tr><th>Item</th><th>In stock</th><th>Minimum</th></tr></thead><tbody>
<?php foreach ($lowStock as $i): ?><tr><td><?= esc($i['name']) ?></td><td class="text-danger"><?= qty($i['have']) ?> <?= esc($i['unit']) ?></td><td><?= qty($i['min_stock']) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?= $this->endSection() ?>
