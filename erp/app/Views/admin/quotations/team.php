<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Sales Team Progress<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$st     = \App\Controllers\Admin\Quotations::STATUSES;
$colors = ['draft' => '#a3acbf', 'sent' => '#6c9cf0', 'accepted' => '#34c38f', 'converted' => '#f1b44c', 'rejected' => '#ef7676'];
$short  = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'converted' => 'Invoiced', 'rejected' => 'Rejected'];
$pct    = static fn ($a, $b) => $b > 0 ? round($a / $b * 100) : 0;
$lakh   = static fn ($v) => '₹' . number_format($v / 100000, 1) . 'L';
$max    = max(1, ...array_map(static fn ($p) => $p['n'], $people ?: [['n' => 1]]));
// Donut as a conic gradient.
$stops = [];
$at    = 0;
foreach ($colors as $s => $c) {
    $share = $team['n'] ? ($team['by'][$s] ?? 0) / $team['n'] * 100 : 0;
    if ($share > 0) {
        $stops[] = $c . ' ' . $at . '% ' . ($at + $share) . '%';
        $at += $share;
    }
}
?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
    <?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?>
    <a href="<?= site_url('admin/quotations') ?>" class="btn btn-outline-secondary ms-auto">All quotations</a>
</div>
<div class="card mb-3" style="max-width:900px"><div class="card-body">
    <div class="d-flex justify-content-between"><h6 class="mb-3">Team</h6><span class="text-muted small"><?= $team['n'] ?> quotes</span></div>
    <div class="d-flex flex-wrap align-items-center gap-4">
        <div style="width:150px;height:150px;border-radius:50%;background:<?= $stops ? 'conic-gradient(' . implode(',', $stops) . ')' : '#e9ecef' ?>;position:relative">
            <div style="position:absolute;inset:22px;border-radius:50%;background:var(--bs-body-bg);display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div class="fs-3 fw-bold"><?= $pct($team['won'], $team['n']) ?>%</div><div class="small text-muted">won</div></div></div>
        <div><div class="fs-6 fw-semibold">Team</div>
            <div><?= $team['n'] ?> quotes made</div><div><?= $team['sent'] ?> sent to customer</div>
            <div><?= $team['won'] ?> won · <?= $lakh($team['won_value']) ?></div></div>
    </div>
    <hr>
    <?php if (! $people): ?><p class="text-muted mb-0">No quotations in this period.</p><?php endif ?>
    <?php foreach ($people as $p): ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between"><strong><?= esc($p['name']) ?></strong><span class="text-muted small"><?= $p['n'] ?> quotes</span></div>
            <div class="d-flex rounded overflow-hidden my-1" style="height:22px;background:var(--bs-tertiary-bg)">
                <?php foreach ($colors as $s => $c): $k = $p['by'][$s] ?? 0; if (! $k) continue; ?>
                    <div title="<?= esc($st[$s]) ?>: <?= $k ?>" style="width:<?= $k / $max * 100 ?>%;background:<?= $c ?>;min-width:22px" class="text-center small fw-semibold text-dark"><?= $k ?></div>
                <?php endforeach ?>
            </div>
            <div class="small text-muted">Sent to customer <b><?= $p['sent'] ?></b> · Won <b><?= $p['won'] ?></b> (<?= $pct($p['won'], $p['n']) ?>%)<?= $p['won_value'] > 0 ? ' · <b>' . $lakh($p['won_value']) . '</b> won' : '' ?></div>
        </div>
    <?php endforeach ?>
    <div class="small d-flex flex-wrap gap-3 border-top pt-2">
        <?php foreach ($colors as $s => $c): ?><span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?= $c ?>"></span> <?= esc($short[$s]) ?> <?= $team['by'][$s] ?? 0 ?></span><?php endforeach ?>
    </div>
</div></div>
<?= $this->endSection() ?>
