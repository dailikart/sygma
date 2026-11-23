<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Inventory Usage – B2B / B2C<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('admin/reports/_head', ['from' => $from, 'to' => $to]) ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card card-body stat-card"><div class="small text-muted">B2B projects supplied</div><div class="value"><?= (int) ($projects['b2b']['n'] ?? 0) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body stat-card"><div class="small text-muted">B2C projects supplied</div><div class="value"><?= (int) ($projects['b2c']['n'] ?? 0) ?></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Category</th><th>Item</th><th class="text-end">B2B</th><th class="text-end">B2C</th><th class="text-end">Total used</th><th class="text-end">Value (purchase price)</th></tr></thead><tbody>
    <?php $tv = 0; foreach ($items as $i): $tot = $i['b2b'] + $i['b2c']; $val = $tot * $i['price']; $tv += $val; ?>
        <tr><td><small><?= esc((string) $i['category']) ?></small></td><td><?= esc($i['name']) ?></td><td class="text-end"><?= qty($i['b2b']) ?></td><td class="text-end"><?= qty($i['b2c']) ?></td>
            <td class="text-end fw-semibold"><?= qty($tot) ?> <?= esc($i['unit']) ?></td><td class="text-end"><?= money($val) ?></td></tr>
    <?php endforeach ?>
    <?php if (! $items): ?><tr><td colspan="6" class="text-center text-muted py-4">No material dispatched in this period.</td></tr><?php endif ?>
    </tbody><tfoot class="fw-semibold"><tr><td colspan="5" class="text-end">Total value</td><td class="text-end"><?= money($tv) ?></td></tr></tfoot>
</table></div></div>
<p class="small text-muted mt-2">Counts material dispatched to project sites (in transit or delivered) by the customer type chosen when the customer was created.</p>
<?= $this->endSection() ?>
