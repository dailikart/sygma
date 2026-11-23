<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Ledgers<?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-muted">Customer, supplier, bank/cash, income-head, expense-head, sales, purchase and GST ledgers are created automatically. Add others (capital, loans, fixed assets…) here.</p>
<?php if (can('accounts.manage')): ?>
<form method="post" action="<?= site_url('admin/ledgers') ?>" class="card card-body mb-3"><?= csrf_field() ?>
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small">Ledger name (as in Tally)</label><input name="name" class="form-control form-control-sm" required></div>
        <div class="col-md-3"><label class="form-label small">Group</label><select name="group_name" class="form-select form-select-sm"><?= select_options(array_combine(\App\Libraries\Ledger::GROUPS, \App\Libraries\Ledger::GROUPS)) ?></select></div>
        <div class="col-md-2"><label class="form-label small">Opening balance</label><input type="number" step="0.01" name="opening_balance" class="form-control form-control-sm" value="0"></div>
        <div class="col-md-1"><select name="opening_type" class="form-select form-select-sm"><option>Dr</option><option>Cr</option></select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Add ledger</button></div>
    </div>
</form>
<?php endif ?>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Ledger</th><th>Group</th><th>Type</th><th class="text-end">Opening</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><a href="<?= site_url('admin/ledgers/' . $r['id']) ?>"><?= esc($r['name']) ?></a></td><td><?= esc($r['group_name']) ?></td><td><small class="text-muted"><?= esc((string) $r['ref_type']) ?></small></td>
        <td class="text-end"><?= (float) $r['opening_balance'] ? \App\Libraries\Ledger::drcr((float) $r['opening_balance']) : '' ?></td></tr><?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="4" class="text-muted text-center py-3">No ledgers yet. They appear as you record invoices, payments, purchases and expenses.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
