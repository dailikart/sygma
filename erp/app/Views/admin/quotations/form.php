<?= $this->extend('admin/layout') ?>
<?php $id = $q['id'] ?? null; $v = static fn ($k, $d = '') => old($k, $q[$k] ?? $d); ?>
<?= $this->section('title') ?><?= $id ? 'Edit Quotation ' . esc($q['quote_no']) : 'New Quotation' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url($id ? 'admin/quotations/' . $id . '/update' : 'admin/quotations') ?>" enctype="multipart/form-data">
<?= csrf_field() ?>
<input type="hidden" name="lead_id" id="lead_id" value="<?= esc((string) $v('lead_id')) ?>">
<input type="hidden" name="customer_id" id="customer_id" value="<?= esc((string) $v('customer_id')) ?>">
<div class="card card-body mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <h6 class="mb-0">Quotation for</h6>
        <?php if (! $id): ?>
        <select id="pick_customer" class="form-select form-select-sm" style="max-width:260px"><option value="">— Existing customer —</option>
            <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" data-p='<?= esc(json_encode(['name' => $c['name'], 'phone' => $c['phone'], 'email' => $c['email'], 'address' => $c['address'], 'city' => $c['city'], 'state' => $c['state'], 'type' => $c['type'], 'eb_by' => $c['eb_charges_by'], 'eb_amt' => $c['eb_charges_amount']]), 'attr') ?>' <?= (string) $v('customer_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?> (<?= esc((string) $c['phone']) ?>)</option><?php endforeach ?></select>
        <span class="small text-muted">or</span>
        <select id="pick_lead" class="form-select form-select-sm" style="max-width:260px"><option value="">— Lead —</option>
            <?php foreach ($leads as $l): ?><option value="<?= $l['id'] ?>" data-customer="<?= (int) $l['customer_id'] ?>" data-p='<?= esc(json_encode(['name' => $l['name'], 'phone' => $l['phone'], 'email' => $l['email'], 'address' => $l['address'], 'city' => $l['city'], 'type' => $l['customer_type'], 'kw' => $l['required_kw']]), 'attr') ?>' <?= (string) $v('lead_id') === (string) $l['id'] ? 'selected' : '' ?>><?= esc($l['name']) ?> (<?= esc((string) $l['phone']) ?>)</option><?php endforeach ?></select>
        <span class="small text-muted">or type the details below for a new enquiry</span>
        <?php endif ?>
    </div>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Name *</label><input name="party_name" id="party_name" class="form-control" required value="<?= esc((string) $v('party_name')) ?>"></div>
        <div class="col-md-2"><label class="form-label">Type</label><select name="customer_type" id="customer_type" class="form-select"><?= select_options(['b2c' => 'B2C (home)', 'b2b' => 'B2B (business)'], $v('customer_type', 'b2c')) ?></select></div>
        <div class="col-md-3"><label class="form-label">Phone</label><input name="party_phone" id="party_phone" class="form-control" value="<?= esc((string) $v('party_phone')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="party_email" id="party_email" class="form-control" value="<?= esc((string) $v('party_email')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input name="party_address" id="party_address" class="form-control" value="<?= esc((string) $v('party_address')) ?>"></div>
        <div class="col-md-3"><label class="form-label">City</label><input name="party_city" id="party_city" class="form-control" value="<?= esc((string) $v('party_city')) ?>"></div>
        <div class="col-md-3"><label class="form-label">State <small class="text-muted">(GST)</small></label><input name="party_state" id="party_state" class="form-control" value="<?= esc((string) $v('party_state', setting('Solar.companyState'))) ?>"></div>
    </div>
</div>
<div class="card card-body mb-3">
    <div class="row g-3">
        <div class="col-md-2"><label class="form-label">Quotation date *</label><input type="date" name="quote_date" class="form-control" required value="<?= esc((string) $v('quote_date', date('Y-m-d'))) ?>"></div>
        <div class="col-md-2"><label class="form-label">Valid until</label><input type="date" name="valid_until" class="form-control" value="<?= esc((string) $v('valid_until')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Subsidy type *</label><select name="subsidy_type" id="subsidy_type" class="form-select"><?= select_options(['subsidy' => 'Subsidy (PM Surya Ghar)', 'non_subsidy' => 'Non-subsidy'], $v('subsidy_type', 'subsidy')) ?></select></div>
        <div class="col-md-3"><label class="form-label">Installation type</label><select name="installation_type_id" class="form-select"><?= select_options($types, $v('installation_type_id'), '— Select —') ?></select></div>
        <div class="col-md-2"><label class="form-label">Capacity (kW) *</label><input type="number" step="0.01" min="0.1" name="capacity_kw" id="capacity_kw" class="form-control" required value="<?= esc($v('capacity_kw') !== '' && $v('capacity_kw') !== null ? qty($v('capacity_kw')) : '') ?>"></div>
        <div class="col-md-9"><label class="form-label">Kit / item group</label>
            <div class="input-group"><select id="kit" name="kit_id" class="form-select"><option value="">— Choose the kit —</option><?php foreach ($kits as $k): ?><option value="<?= $k['id'] ?>" <?= (string) $v('kit_id') === (string) $k['id'] ? 'selected' : '' ?>><?= esc($k['name']) ?> (<?= qty($k['capacity_kw']) ?> kW)</option><?php endforeach ?></select>
                <button type="button" class="btn btn-outline-secondary" id="load-kit">Load items (B2B)</button>
                <button type="button" class="btn btn-outline-primary" id="load-package">Fill as B2C package</button></div></div>
        <div class="col-md-3"><label class="form-label">Print as</label><select name="print_format" id="print_format" class="form-select"><?= select_options(\App\Libraries\InvoiceFormat::FORMATS, $v('print_format', 'cumulative')) ?></select></div>
    </div>
</div>
<div class="card card-body mb-3"><?= view('admin/partials/line_items', ['items' => $items, 'price' => 'sale_price', 'description' => true, 'rows' => $rows]) ?></div>
<div class="card card-body mb-3">
    <h6>Subsidy &amp; savings</h6>
    <div class="row g-3">
        <div class="col-md-3 subsidy-only"><label class="form-label">Expected subsidy (₹)</label><input type="number" step="0.01" min="0" name="subsidy_amount" id="subsidy_amount" class="form-control" value="<?= esc((string) ((float) $v('subsidy_amount', 0) ?: '')) ?>">
            <div class="form-text">Suggested from kW: <a href="#" id="subsidy_fill">₹<span id="subsidy_suggest">0</span></a> (₹30,000/kW up to 2 kW, ₹18,000 for the 3rd kW, max ₹78,000).</div></div>
        <div class="col-md-4"><label class="form-label">EB / meter charges paid by</label>
            <div class="input-group"><select name="eb_charges_by" id="eb_by" class="form-select"><?= select_options(eb_charges_options(), (string) $v('eb_charges_by', '')) ?></select>
            <input type="number" step="0.01" min="0" name="eb_charges_amount" id="eb_amt" class="form-control" style="max-width:110px" value="<?= esc((string) ((float) $v('eb_charges_amount', 5000) ?: 5000)) ?>"></div></div>
        <div class="col-md-2"><label class="form-label">Units / kW / day</label><input type="number" step="0.1" min="0" name="units_per_kw_day" class="form-control" value="<?= esc((string) $v('units_per_kw_day', setting('Solar.quoteUnitsPerKw'))) ?>"></div>
        <div class="col-md-3"><label class="form-label">Tariff (₹ per unit)</label><input type="number" step="0.01" min="0" name="tariff" class="form-control" value="<?= esc((string) $v('tariff', setting('Solar.quoteTariff'))) ?>"></div>
        <div class="col-12 form-text">The quotation shows yearly generation, yearly savings, net cost after subsidy, payback in years and 25-year savings, worked out from these numbers.</div>
    </div>
</div>
<div class="card card-body mb-3">
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Terms / notes (printed)</label><textarea name="notes" class="form-control" rows="4"><?= esc((string) $v('notes')) ?></textarea></div>
        <div class="col-md-4"><label class="form-label">Design / layout (image or PDF, optional)</label><input type="file" name="design" class="form-control" accept="image/*,application/pdf">
            <?php if (! empty($q['design_path'])): ?><div class="form-text"><a href="<?= site_url('admin/quotations/' . $id . '/design') ?>" target="_blank">Current design</a> · <label><input type="checkbox" name="remove_design" value="1" class="form-check-input"> remove</label></div><?php endif ?>
            <div class="form-text">E.g. a roof layout from your design tool. Images are printed on the quotation.</div></div>
    </div>
</div>
<button class="btn btn-primary"><?= $id ? 'Save changes' : 'Save quotation' ?></button> <a href="<?= site_url($id ? 'admin/quotations/' . $id : 'admin/quotations') ?>" class="btn btn-light">Cancel</a>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<?= view('admin/partials/kit_loader', ['kits' => $kits]) ?>
<script>
(function () {
    const $ = id => document.getElementById(id);
    const fill = (p, sourceCustomer) => {
        const set = (id, val) => { if (val !== undefined && val !== null && val !== '') $(id).value = val; };
        set('party_name', p.name); set('party_phone', p.phone); set('party_email', p.email); set('party_address', p.address); set('party_city', p.city); set('party_state', p.state);
        if (p.type) { $('customer_type').value = p.type; $('print_format').value = p.type === 'b2b' ? 'itemwise' : 'cumulative'; }
        if (p.kw && !$('capacity_kw').value) { $('capacity_kw').value = parseFloat(p.kw); suggest(); }
        if (p.eb_by) { $('eb_by').value = p.eb_by; if (p.eb_amt) $('eb_amt').value = parseFloat(p.eb_amt); ebSync(); }
    };
    const pc = $('pick_customer'), pl = $('pick_lead');
    if (pc) pc.addEventListener('change', function () {
        $('customer_id').value = this.value; $('lead_id').value = ''; if (pl) pl.value = '';
        if (this.value) fill(JSON.parse(this.selectedOptions[0].dataset.p));
    });
    if (pl) pl.addEventListener('change', function () {
        $('lead_id').value = this.value; $('customer_id').value = this.value ? (this.selectedOptions[0].dataset.customer !== '0' ? this.selectedOptions[0].dataset.customer : '') : ''; if (pc) pc.value = '';
        if (this.value) fill(JSON.parse(this.selectedOptions[0].dataset.p));
    });
    // PM Surya Ghar suggested subsidy
    const suggestValue = kw => kw <= 0 ? 0 : Math.min(kw, 2) * 30000 + Math.max(0, Math.min(kw, 3) - 2) * 18000;
    let subsidyTouched = $('subsidy_amount').value !== '';
    $('subsidy_amount').addEventListener('input', () => { subsidyTouched = true; });
    const kits = <?= json_encode(array_map(static fn ($k) => ['id' => (string) $k['id'], 'kw' => (float) $k['capacity_kw']], $kits)) ?>;
    function suggest() {
        const kw = parseFloat($('capacity_kw').value) || 0;
        $('subsidy_suggest').textContent = suggestValue(kw).toLocaleString('en-IN');
        if (!subsidyTouched) $('subsidy_amount').value = kw > 0 ? suggestValue(kw) : '';
        const match = kits.find(k => k.kw === kw);
        if (match && !$('kit').dataset.touched) $('kit').value = match.id;
    }
    if ($('kit').value) $('kit').dataset.touched = '1';
    $('kit').addEventListener('change', function () { this.dataset.touched = '1'; });
    $('capacity_kw').addEventListener('input', suggest); suggest();
    $('subsidy_fill').addEventListener('click', e => { e.preventDefault(); $('subsidy_amount').value = suggestValue(parseFloat($('capacity_kw').value) || 0); });
    const subSync = () => document.querySelectorAll('.subsidy-only').forEach(el => el.hidden = $('subsidy_type').value !== 'subsidy');
    $('subsidy_type').addEventListener('change', subSync); subSync();
    function ebSync() { $('eb_amt').hidden = $('eb_by').value !== 'customer'; }
    $('eb_by').addEventListener('change', ebSync); ebSync();
    $('customer_type').addEventListener('change', function () { $('print_format').value = this.value === 'b2b' ? 'itemwise' : 'cumulative'; });
})();
</script>
<?= $this->endSection() ?>
