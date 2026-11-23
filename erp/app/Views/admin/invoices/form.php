<?= $this->extend('admin/layout') ?>
<?php $inv = $inv ?? null; $pre = $pre ?? []; $val = static fn ($k, $d = '') => old($k, $inv[$k] ?? $pre[$k] ?? $d); ?>
<?= $this->section('title') ?><?= $inv ? 'Edit Invoice ' . esc($inv['invoice_no']) : 'Create Invoice' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if ($inv): ?><div class="alert alert-warning small"><i class="bi bi-pencil-square"></i> Editing a saved invoice. Totals, GST, the accounting entries and the project (kW, customer) are updated when you save. Payments stay as they are.</div><?php endif ?>
<?php if (! empty($pre['quotation_id'])): ?><div class="alert alert-info small"><i class="bi bi-file-earmark-text"></i> Filled from quotation <strong><?= esc($pre['quote_no']) ?></strong>. Check and save; the quotation is marked as converted.</div><?php endif ?>
<form method="post" action="<?= site_url($inv ? 'admin/invoices/' . $inv['id'] . '/update' : 'admin/invoices') ?>">
<?php if (! empty($pre['quotation_id'])): ?><input type="hidden" name="quotation_id" value="<?= (int) $pre['quotation_id'] ?>"><?php endif ?>
<?= csrf_field() ?>
<div class="card card-body mb-3">
    <div class="mb-2"><span class="badge text-bg-light border fs-6">Invoice no: <?= esc($nextNo) ?></span> <?= $inv ? '' : '<small class="text-muted">(given automatically when you save)</small>' ?></div>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Customer *</label>
            <select name="customer_id" class="form-select" required>
                <option value="">— Select customer —</option>
                <?php foreach ($customers as $c): ?><option data-type="<?= esc($c['type']) ?>" data-eb-by="<?= esc((string) ($c['eb_charges_by'] ?? '')) ?>" data-eb-amount="<?= esc((string) ($c['eb_charges_amount'] ?? '')) ?>" value="<?= $c['id'] ?>" <?= (int) old('customer_id', $customerId) === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?> (<?= strtoupper($c['type']) ?><?= $c['city'] ? ', ' . esc($c['city']) : '' ?>)</option><?php endforeach ?>
            </select>
            <div class="form-text">Not in the list? <a href="<?= site_url('admin/customers/new') ?>">Add customer</a></div></div>
        <div class="col-md-3"><label class="form-label">Invoice date *</label><input type="date" name="invoice_date" class="form-control" required value="<?= esc($val('invoice_date', date('Y-m-d'))) ?>"></div>
        <div class="col-md-3"><label class="form-label">Due date</label><input type="date" name="due_date" class="form-control" value="<?= esc((string) $val('due_date', $inv ? '' : date('Y-m-d', strtotime('+7 days')))) ?>"></div>
        <?php if (\App\Libraries\Features::on('projects.subsidy')): ?>
        <div class="col-md-3"><label class="form-label">Subsidy type *</label>
            <select name="subsidy_type" class="form-select" required><?= select_options(['subsidy' => 'Subsidy customer (PM Surya Ghar)', 'non_subsidy' => 'Non-subsidy customer'], $val('subsidy_type', 'subsidy')) ?></select></div>
        <?php else: ?><input type="hidden" name="subsidy_type" value="non_subsidy"><?php endif ?>
        <div class="col-md-3"><label class="form-label">Installation type</label>
            <select name="installation_type_id" class="form-select"><?= select_options($types, $val('installation_type_id'), '— Select —') ?></select></div>
        <div class="col-md-3"><label class="form-label">System capacity (kW) *</label><input type="number" step="0.01" min="0.1" name="capacity_kw" id="capacity_kw" class="form-control" required value="<?= esc(($inv || $pre) && $val('capacity_kw') !== '' ? qty($val('capacity_kw')) : old('capacity_kw')) ?>"></div>
        <div class="col-md-9"><label class="form-label">Kit / item group (decides what the store loads for this project)</label>
            <div class="input-group"><select id="kit" name="kit_id" class="form-select"><option value="">— Auto (by kW) —</option><?php foreach ($kits as $k): ?><option value="<?= $k['id'] ?>" <?= (string) $val('kit_id') === (string) $k['id'] ? 'selected' : '' ?>><?= esc($k['name']) ?> (<?= qty($k['capacity_kw']) ?> kW)</option><?php endforeach ?></select>
                <button type="button" class="btn btn-outline-secondary" id="load-kit" title="B2B: list every item of the kit on the invoice">Load items (B2B)</button>
                <button type="button" class="btn btn-outline-primary" id="load-package" title="B2C: system supply line + installation service line">Fill as B2C package</button></div>
            <div class="form-text">B2B: <strong>Load items</strong> lists every item. B2C: <strong>Fill as B2C package</strong> adds "system supply" + "installation service" lines. Either way the dispatch / delivery note loads the full kit.</div></div>
    </div>
</div>
<div class="card card-body mb-3">
    <?= view('admin/partials/line_items', ['items' => $items, 'price' => 'sale_price', 'description' => true, 'rows' => $rows ?? []]) ?>
</div>
<div class="card card-body mb-3">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Invoice format</label>
            <select name="print_format" id="print_format" class="form-select"><?= select_options(\App\Libraries\InvoiceFormat::FORMATS, $val('print_format', 'cumulative')) ?></select>
            <div class="form-text">Set automatically from the customer type; you can change it.</div></div>
        <?php if (! $inv): ?><div class="col-md-4"><label class="form-label">Payment mode</label>
            <select name="finance_mode" id="finance_mode" class="form-select"><?= select_options(\App\Libraries\Finance::modes(), old('finance_mode', '')) ?></select>
            <div class="form-text">Can be decided later, but must be set before material is dispatched.</div></div><?php endif ?>
        <div class="col-md-4"><label class="form-label">EB / meter charges paid by</label>
            <div class="input-group"><select name="eb_charges_by" id="eb_by" class="form-select"><?= select_options(eb_charges_options(), (string) $val('eb_charges_by', '')) ?></select>
            <input type="number" step="0.01" min="0" name="eb_charges_amount" id="eb_amt" class="form-control" style="max-width:120px" placeholder="₹" value="<?= esc((string) ((float) $val('eb_charges_amount', 5000) ?: 5000)) ?>"></div>
            <div class="form-text">Company = shown as "–"; Customer = amount collected beyond this invoice. Filled from the customer.</div></div>
    </div>
    <div class="row g-3 mt-1 loan-fields" style="display:none">
        <div class="col-md-4"><label class="form-label">Bank / lender *</label><input name="loan_bank" class="form-control" value="<?= esc(old('loan_bank')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Loan amount (₹) *</label><input type="number" step="0.01" name="loan_amount" class="form-control" value="<?= esc(old('loan_amount')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Loan / sanction ref.</label><input name="loan_ref" class="form-control" value="<?= esc(old('loan_ref')) ?>"></div>
        <div class="col-12 form-text">The bank pays us directly. 3 disbursements are created; edit them or add more on the invoice page.</div>
    </div>
</div>
<div class="card card-body mb-3">
    <label class="form-label">Notes / terms (printed on the invoice)</label>
    <textarea name="notes" class="form-control" rows="2"><?= esc((string) $val('notes')) ?></textarea>
    <?php if (! $inv): ?>
    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="email_invoice" value="1" id="ei" <?= old('email_invoice') ? 'checked' : '' ?>>
        <label class="form-check-label" for="ei"><i class="bi bi-envelope"></i> Email this invoice (PDF) to the customer</label></div>
    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="create_project" value="1" id="cp" checked>
        <label class="form-check-label" for="cp">Start the installation project (site survey → dispatch → delivery → installation → EB → subsidy)</label></div>
    <?php endif ?>
</div>
<button class="btn btn-primary"><?= $inv ? 'Save changes' : 'Save invoice' ?></button> <a href="<?= site_url($inv ? 'admin/invoices/' . $inv['id'] : 'admin/invoices') ?>" class="btn btn-light">Cancel</a>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    const fm = document.getElementById('finance_mode'), loan = document.querySelector('.loan-fields');
    if (fm) { const toggle = () => loan.style.display = fm.value === 'loan' ? '' : 'none'; fm.addEventListener('change', toggle); toggle(); }
    const cust = document.querySelector('[name=customer_id]'), pf = document.getElementById('print_format');
    cust.addEventListener('change', () => { const t = cust.selectedOptions[0]?.dataset.type; if (t) pf.value = t === 'b2b' ? 'itemwise' : 'cumulative'; });
})();
</script>
<?= view('admin/partials/kit_loader', ['kits' => $kits]) ?>
<script>
// EB / meter charges: default from the customer; amount only when the customer pays.
(function () {
    const cust = document.querySelector('[name=customer_id]'), by = document.getElementById('eb_by'), amt = document.getElementById('eb_amt');
    const sync = () => { amt.hidden = by.value !== 'customer'; };
    const fromCustomer = () => { const o = cust.selectedOptions[0]; if (!o || !o.dataset.ebBy) return; by.value = o.dataset.ebBy; if (o.dataset.ebAmount) amt.value = parseFloat(o.dataset.ebAmount); sync(); };
    cust.addEventListener('change', fromCustomer); by.addEventListener('change', sync);
    if (!by.value) fromCustomer(); sync();
})();
</script>
<?= $this->endSection() ?>
