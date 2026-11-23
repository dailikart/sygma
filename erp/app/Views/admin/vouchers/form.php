<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Add <?= esc($c['title']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/' . $c['uri']) ?>" enctype="multipart/form-data" class="card card-body" style="max-width:800px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12"><span class="badge text-bg-light border fs-6">Voucher no: <?= esc($nextNo) ?></span> <small class="text-muted">(given automatically when you save)</small></div>
        <div class="col-md-6"><label class="form-label"><?= esc($c['headLabel']) ?> *</label><select name="head_id" class="form-select" required><?= select_options($heads, old('head_id'), '— Select —') ?></select>
            <?php if (! $heads): ?><div class="form-text text-danger">Add one first in the <?= esc(strtolower($c['headLabel'])) ?> master.</div><?php endif ?></div>
        <div class="col-md-3"><label class="form-label">Date *</label><input type="date" name="doc_date" class="form-control" required value="<?= esc(old('doc_date', date('Y-m-d'))) ?>"></div>
        <div class="col-md-3"><label class="form-label">Amount (₹) *</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?= esc(old('amount')) ?>"></div>
        <div class="col-md-6"><label class="form-label"><?= $c['table'] === 'incomes' ? 'Received in' : 'Paid from' ?> account *</label><select name="account_id" class="form-select" required><?= select_options($accounts, old('account_id'), '— Bank / cash —') ?></select></div>
        <?php if ($c['table'] === 'supplier_payments'): ?><div class="col-md-6"><label class="form-label">Mode</label><select name="mode" class="form-select"><?= select_options(['bank' => 'Bank transfer', 'upi' => 'UPI', 'cheque' => 'Cheque', 'cash' => 'Cash'], old('mode', 'bank')) ?></select></div><?php endif ?>
        <?php if ($c['party']): ?><div class="col-md-6"><label class="form-label"><?= $c['party'] === 'paid_to' ? 'Paid to' : 'Received from' ?></label><input name="party" class="form-control" value="<?= esc(old('party')) ?>"></div><?php endif ?>
        <div class="col-md-6"><label class="form-label">Reference / UTR / bill no.</label><input name="reference" class="form-control" value="<?= esc(old('reference')) ?>"></div>
        <?php if ($c['table'] === 'expenses'): ?>
            <div class="col-md-6"><label class="form-label">Project (optional – for project profit)</label><select name="project_id" class="form-select"><?= select_options($projects, old('project_id'), '— None —') ?></select></div>
            <div class="col-md-6"><label class="form-label">Bill / receipt (image or PDF)</label><input type="file" name="attachment" accept="image/*,application/pdf" class="form-control"></div>
        <?php endif ?>
        <div class="col-12"><label class="form-label">Narration / notes</label><input name="notes" class="form-control" value="<?= esc(old('notes')) ?>"></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save</button> <a href="<?= site_url('admin/' . $c['uri']) ?>" class="btn btn-light">Cancel</a></div>
</form>
<?= $this->endSection() ?>
