<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Profit &amp; Loss Account<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('admin/reports/_head', ['from' => $from, 'to' => $to]) ?>
<?php
$side = static function (array $rows) { $h = ''; foreach ($rows as $label => $amt) { $h .= '<tr><td class="ps-4">' . esc($label) . '</td><td class="text-end">' . money($amt, false) . '</td></tr>'; } return $h; };
$group = static function (string $title, array $items) use ($side) { if (! $items) { return ''; } return '<tr class="table-light"><td class="fw-semibold">' . esc($title) . '</td><td class="text-end fw-semibold">' . money(array_sum($items), false) . '</td></tr>' . $side($items); };
$grossL = $gross < 0 ? -$gross : 0; $grossP = $gross > 0 ? $gross : 0;
$leftTotal  = $tradingDr + $grossP; $rightTotal = $tradingCr + $grossL;
$netL = $net < 0 ? -$net : 0; $netP = $net > 0 ? $net : 0;
$ie = array_sum($g['Indirect Expenses']); $ii = array_sum($g['Indirect Incomes']);
?>
<p class="text-muted small">For <?= fdate($from) ?> to <?= fdate($to) ?>. Stock is valued at purchase price.</p>
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><table class="table table-sm mb-0">
        <thead><tr><th>Particulars (Dr)</th><th class="text-end">₹</th></tr></thead><tbody>
        <tr class="table-light"><td class="fw-semibold">Opening Stock</td><td class="text-end fw-semibold"><?= money($opening, false) ?></td></tr>
        <?= $group('Purchase Accounts', $g['Purchase Accounts']) ?>
        <?= $group('Direct Expenses', $g['Direct Expenses']) ?>
        <?php if ($grossP): ?><tr class="text-success fw-semibold"><td>Gross Profit c/o</td><td class="text-end"><?= money($grossP, false) ?></td></tr><?php endif ?>
        <tr class="border-top fw-bold"><td></td><td class="text-end"><?= money(max($leftTotal, $rightTotal), false) ?></td></tr>
        <?php if ($grossL): ?><tr class="text-danger fw-semibold"><td>Gross Loss b/f</td><td class="text-end"><?= money($grossL, false) ?></td></tr><?php endif ?>
        <?= $group('Indirect Expenses', $g['Indirect Expenses']) ?>
        <?php if ($netP): ?><tr class="text-success fw-bold"><td>Net Profit</td><td class="text-end"><?= money($netP, false) ?></td></tr><?php endif ?>
        <tr class="border-top fw-bold"><td>Total</td><td class="text-end"><?= money($grossL + $ie + $netP, false) ?></td></tr>
        </tbody></table></div></div>
    <div class="col-lg-6"><div class="card"><table class="table table-sm mb-0">
        <thead><tr><th>Particulars (Cr)</th><th class="text-end">₹</th></tr></thead><tbody>
        <?= $group('Sales Accounts', $g['Sales Accounts']) ?>
        <?= $group('Direct Incomes', $g['Direct Incomes']) ?>
        <tr class="table-light"><td class="fw-semibold">Closing Stock</td><td class="text-end fw-semibold"><?= money($closing, false) ?></td></tr>
        <?php if ($grossL): ?><tr class="text-danger fw-semibold"><td>Gross Loss c/o</td><td class="text-end"><?= money($grossL, false) ?></td></tr><?php endif ?>
        <tr class="border-top fw-bold"><td></td><td class="text-end"><?= money(max($leftTotal, $rightTotal), false) ?></td></tr>
        <?php if ($grossP): ?><tr class="text-success fw-semibold"><td>Gross Profit b/f</td><td class="text-end"><?= money($grossP, false) ?></td></tr><?php endif ?>
        <?= $group('Indirect Incomes', $g['Indirect Incomes']) ?>
        <?php if ($netL): ?><tr class="text-danger fw-bold"><td>Net Loss</td><td class="text-end"><?= money($netL, false) ?></td></tr><?php endif ?>
        <tr class="border-top fw-bold"><td>Total</td><td class="text-end"><?= money($grossP + $ii + $netL, false) ?></td></tr>
        </tbody></table></div></div>
</div>
<div class="alert <?= $net >= 0 ? 'alert-success' : 'alert-danger' ?> mt-3 mb-0"><strong><?= $net >= 0 ? 'Net Profit' : 'Net Loss' ?>: <?= money(abs($net)) ?></strong> · Gross <?= $gross >= 0 ? 'profit' : 'loss' ?> <?= money(abs($gross)) ?></div>
<?= $this->endSection() ?>
