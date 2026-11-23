<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Income vs Expense<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('admin/reports/_head', ['from' => $from, 'to' => $to]) ?>
<div class="card mb-3"><div class="table-responsive"><table class="table table-sm mb-0 text-end">
    <thead><tr><th class="text-start">Month</th><th>Sales billed</th><th>Net purchases</th><th>Customer receipts</th><th>Other income</th><th class="table-success">Money in</th><th>Supplier payments</th><th>Expenses</th><th class="table-danger">Money out</th><th>Net</th></tr></thead><tbody>
    <?php $t = array_fill_keys(['sales', 'purch', 'rec', 'oi', 'in', 'sp', 'ex', 'out'], 0);
    foreach ($months as $k => $m): $in = $m['receipts'] + $m['other_income']; $out = $m['expenses'] + $m['supplier_payments'];
        foreach (['sales' => $m['sales'], 'purch' => $m['purchases'] - $m['returns'], 'rec' => $m['receipts'], 'oi' => $m['other_income'], 'in' => $in, 'sp' => $m['supplier_payments'], 'ex' => $m['expenses'], 'out' => $out] as $kk => $vv) { $t[$kk] += $vv; } ?>
        <tr><td class="text-start"><?= date('M Y', strtotime($k . '-01')) ?></td><td><?= money($m['sales'], false) ?></td><td><?= money($m['purchases'] - $m['returns'], false) ?></td><td><?= money($m['receipts'], false) ?></td><td><?= money($m['other_income'], false) ?></td>
            <td class="table-success"><?= money($in, false) ?></td><td><?= money($m['supplier_payments'], false) ?></td><td><?= money($m['expenses'], false) ?></td><td class="table-danger"><?= money($out, false) ?></td>
            <td class="<?= $in - $out < 0 ? 'text-danger' : 'text-success' ?>"><?= money($in - $out, false) ?></td></tr>
    <?php endforeach ?>
    </tbody><tfoot class="fw-bold"><tr><td class="text-start">Total</td><td><?= money($t['sales']) ?></td><td><?= money($t['purch']) ?></td><td><?= money($t['rec']) ?></td><td><?= money($t['oi']) ?></td><td><?= money($t['in']) ?></td><td><?= money($t['sp']) ?></td><td><?= money($t['ex']) ?></td><td><?= money($t['out']) ?></td>
        <td class="<?= $t['in'] - $t['out'] < 0 ? 'text-danger' : 'text-success' ?>"><?= money($t['in'] - $t['out']) ?></td></tr></tfoot>
</table></div></div>
<div class="row g-3">
    <div class="col-md-6"><div class="card"><div class="card-header bg-white fw-semibold">Expenses by head</div><table class="table table-sm mb-0">
        <?php foreach ($expenseHeads as $h): ?><tr><td><?= esc($h['name']) ?></td><td class="text-end"><?= money($h['amount']) ?></td></tr><?php endforeach ?>
        <?php if (! $expenseHeads): ?><tr><td class="text-muted">No expenses.</td></tr><?php endif ?></table></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header bg-white fw-semibold">Other income by head</div><table class="table table-sm mb-0">
        <?php foreach ($incomeHeads as $h): ?><tr><td><?= esc($h['name']) ?></td><td class="text-end"><?= money($h['amount']) ?></td></tr><?php endforeach ?>
        <?php if (! $incomeHeads): ?><tr><td class="text-muted">No other income.</td></tr><?php endif ?></table></div></div>
</div>
<p class="small text-muted mt-2">Money in / out is on a received / paid basis. Sales and purchases show what was billed in the month.</p>
<?= $this->endSection() ?>
