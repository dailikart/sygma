<?= $this->extend('admin/layout') ?>
<?php
use App\Libraries\QuickQuote as QQ;

$id  = $q['id'] ?? null;
$in  = $spec['input'] ?? [];
$pty = $spec['party'] ?? [];
$v   = static fn ($k, $d = '') => old($k, $q[$k] ?? $d);
$i   = static fn ($k, $d = '') => old('qq.' . $k, $in[$k] ?? $d);
$radio = static function (string $name, array $opts, $current, string $cls = 'col-6') {
    $html = '';
    foreach ($opts as $val => $label) {
        [$title, $sub] = is_array($label) ? $label : [$label, ''];
        $rid  = 'qq_' . $name . '_' . preg_replace('/\W/', '', (string) $val);
        $html .= '<div class="' . $cls . '"><input type="radio" class="btn-check" name="qq[' . $name . ']" id="' . $rid . '" value="' . esc((string) $val, 'attr') . '" ' . ((string) $current === (string) $val ? 'checked' : '') . '>'
            . '<label class="btn btn-outline-primary w-100 py-2" for="' . $rid . '"><span class="fw-semibold">' . esc($title) . '</span>' . ($sub ? '<br><small class="opacity-75">' . esc($sub) . '</small>' : '') . '</label></div>';
    }

    return $html;
};
$brandsBy = [];
foreach ($cfg['panels'] as $p) {
    $brandsBy[($p['dcr'] ? 'dcr' : 'ndcr') . '_' . $p['tech']][] = $p['brand'];
}
$brandsBy = array_map(static fn ($b) => array_values(array_unique($b)), $brandsBy);
$invSizes = ['ongrid' => [], 'hybrid' => []];
foreach ($cfg['inverters'] as $kind => $rows) {
    foreach ($rows as $r) {
        $invSizes[$kind][$r['phase']][] = $r['kw'];
    }
}
?>
<?= $this->section('title') ?><?= $id ? 'Revise Quick Quote ' . esc($q['quote_no']) : 'Quick Quote' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<style>
.qq-card .card-header { background: transparent; font-weight: 600; border-left: 3px solid var(--bs-primary); }
.qq-sum { position: sticky; top: 1rem; }
.qq-sum .big { font-size: 1.6rem; font-weight: 700; }
</style>
<form method="post" id="qqForm" action="<?= site_url($id ? 'admin/quotations/' . $id . '/quick' : 'admin/quotations/quick') ?>">
<?= csrf_field() ?>
<input type="hidden" name="lead_id" id="lead_id" value="<?= esc((string) $v('lead_id')) ?>">
<input type="hidden" name="customer_id" id="customer_id" value="<?= esc((string) $v('customer_id')) ?>">
<div class="row g-3">
<div class="col-lg-8">
    <div class="card qq-card mb-3"><div class="card-header">Customer Details</div><div class="card-body">
        <?php if (! $id && ($customers || $leads)): ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <select id="pick_customer" class="form-select form-select-sm" style="max-width:260px"><option value="">— Existing customer —</option>
                <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" data-p='<?= esc(json_encode($c), 'attr') ?>' <?= (string) $v('customer_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?> (<?= esc((string) $c['phone']) ?>)</option><?php endforeach ?></select>
            <select id="pick_lead" class="form-select form-select-sm" style="max-width:260px"><option value="">— Lead —</option>
                <?php foreach ($leads as $l): ?><option value="<?= $l['id'] ?>" data-customer="<?= (int) $l['customer_id'] ?>" data-p='<?= esc(json_encode($l), 'attr') ?>' <?= (string) $v('lead_id') === (string) $l['id'] ? 'selected' : '' ?>><?= esc($l['name']) ?> (<?= esc((string) $l['phone']) ?>)</option><?php endforeach ?></select>
            <span class="small text-muted align-self-center">or type a new enquiry below</span>
        </div>
        <?php endif ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Customer name *</label><input name="party_name" id="party_name" class="form-control" required placeholder="e.g. Mr. Ramesh Kumar" value="<?= esc((string) $v('party_name')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Phone number</label><input name="party_phone" id="party_phone" class="form-control" inputmode="tel" placeholder="9876543210" value="<?= esc((string) $v('party_phone')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Address / Area</label><input name="party_address" id="party_address" class="form-control" placeholder="e.g. Arakkonam, Vellore District" value="<?= esc((string) $v('party_address')) ?>"></div>
            <div class="col-md-3"><label class="form-label">City</label><input name="party_city" id="party_city" class="form-control" value="<?= esc((string) $v('party_city')) ?>"></div>
            <div class="col-md-3"><label class="form-label">PIN code</label><input name="pincode" class="form-control" inputmode="numeric" maxlength="6" placeholder="631001" value="<?= esc((string) old('pincode', $pty['pincode'] ?? '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">EB consumer number</label><input name="eb_number" class="form-control" placeholder="09-123-456-789" value="<?= esc((string) old('eb_number', $pty['eb_number'] ?? '')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="party_email" id="party_email" class="form-control" value="<?= esc((string) $v('party_email')) ?>"></div>
            <div class="col-md-4"><label class="form-label">State <small class="text-muted">(GST)</small></label><input name="party_state" id="party_state" class="form-control" value="<?= esc((string) $v('party_state', setting('Solar.companyState'))) ?>"></div>
        </div>
    </div></div>

    <div class="card qq-card mb-3"><div class="card-header">System Size</div><div class="card-body">
        <div class="row g-2 mb-3"><?= $radio('mode', ['bill' => 'From EB Bill', 'kw' => 'Enter kW Size'], $i('mode', 'bill')) ?></div>
        <div class="row g-3 mode-bill">
            <div class="col-md-6"><label class="form-label">Bi-monthly units (as per EB bill)</label><input type="number" min="0" step="1" name="qq[units]" class="form-control" placeholder="Units" value="<?= esc((string) $i('units')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Bi-monthly bill amount (₹)</label><input type="number" min="0" step="1" name="qq[bill_amount]" class="form-control" placeholder="Used when units are not known" value="<?= esc((string) $i('bill_amount')) ?>"></div>
        </div>
        <div class="row g-3 mode-kw"><div class="col-md-6"><label class="form-label">System size (kW)</label><input type="number" min="0.5" step="0.01" name="qq[kw]" class="form-control" placeholder="e.g. 3" value="<?= esc((string) $i('kw')) ?>">
            <div class="form-text">Panels are rounded up, so the system is never below this size.</div></div></div>
    </div></div>

    <div class="card qq-card mb-3"><div class="card-header">Building Type</div><div class="card-body">
        <div class="row g-2"><?= $radio('structure', array_map(static fn ($s) => $s[0], QQ::STRUCTURES), $i('structure', 'low')) ?></div>
        <div class="mt-3 struct-high" style="max-width:240px"><label class="form-label">Floors above ground</label><input type="number" min="0" max="50" name="qq[floors]" class="form-control" value="<?= esc((string) $i('floors', 0)) ?>"><div class="form-text">Adds <?= QQ::fmt($cfg['costs']['floor_cable_m']) ?> m of cable per floor.</div></div>
    </div></div>

    <div class="card qq-card mb-3"><div class="card-header">Wiring</div><div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">DC cable size</label><select name="qq[dc_cable]" class="form-select"><?php foreach (QQ::DC_CABLES as $c): ?><option <?= $i('dc_cable') === $c ? 'selected' : '' ?>><?= esc($c) ?></option><?php endforeach ?></select></div>
        <div class="col-md-6"><label class="form-label">AC copper cable size</label><select name="qq[ac_cable]" class="form-select"><?php foreach (QQ::AC_CABLES as $c): ?><option <?= $i('ac_cable', QQ::AC_CABLES[2]) === $c ? 'selected' : '' ?>><?= esc($c) ?></option><?php endforeach ?></select></div>
    </div></div>

    <div class="card qq-card mb-3"><div class="card-header">System Type</div><div class="card-body">
        <div class="row g-2"><?= $radio('system', QQ::SYSTEMS, $i('system', $cfg['default_system'])) ?></div>
        <div class="mt-3 sys-hybrid" style="max-width:240px"><label class="form-label">Battery (kWh)</label><input type="number" min="0" step="0.5" name="qq[battery_kwh]" class="form-control" value="<?= esc((string) $i('battery_kwh', 5)) ?>"></div>
        <div class="form-check mt-3"><input type="hidden" name="qq[subsidy]" value="0"><input class="form-check-input" type="checkbox" name="qq[subsidy]" value="1" id="qq_subsidy" <?= $i('subsidy', true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="qq_subsidy">Apply PM Surya Ghar<?= $cfg['state_subsidy'] ? ' + ' . esc($cfg['state_label']) : '' ?> subsidy (DCR systems, residential)</label></div>
    </div></div>

    <div class="card qq-card mb-3"><div class="card-header">Panel Technology</div><div class="card-body">
        <div class="row g-2 mb-3"><?= $radio('tech', QQ::TECHS, $i('tech', $cfg['default_tech']), 'col-4') ?></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Panel wattage</label><select name="qq[watt]" id="qq_watt" class="form-select"><?php foreach (array_unique(array_merge(QQ::WATTS, array_values($cfg['default_watt']))) as $w): ?><option value="<?= $w ?>" <?= (int) $i('watt') === (int) $w ? 'selected' : '' ?>><?= $w ?>W</option><?php endforeach ?></select></div>
            <div class="col-md-6"><label class="form-label">Panel brand</label><select name="qq[panel_brand]" id="qq_brand" class="form-select" data-current="<?= esc((string) $i('panel_brand')) ?>"></select></div>
        </div>
    </div></div>

    <div class="card qq-card mb-3"><div class="card-header">Inverter Options</div><div class="card-body">
        <label class="form-label">Phase type</label>
        <div class="row g-2 mb-3"><?= $radio('phase', ['1' => 'Single', '3' => 'Three'], (string) $i('phase', 1)) ?></div>
        <div class="row g-3">
            <div class="col-md-6 not-micro"><label class="form-label">Inverter capacity</label><select name="qq[inverter_kw]" id="qq_invkw" class="form-select" data-current="<?= esc((string) $i('inverter_kw', 'auto')) ?>"></select></div>
            <div class="col-md-6"><label class="form-label">Inverter brand</label><select name="qq[inverter_brand]" id="qq_invbrand" class="form-select" data-current="<?= esc((string) $i('inverter_brand')) ?>"></select></div>
        </div>
    </div></div>

    <div class="card qq-card mb-3"><div class="card-body row g-3">
        <?php if ($canAdjust): ?><div class="col-md-4"><label class="form-label">Adjust price (₹)</label><input type="number" step="1" name="qq[adjust]" class="form-control" placeholder="e.g. -5000 discount" value="<?= esc((string) $i('adjust')) ?>"><div class="form-text">Minus for a discount, plus for extra work.</div></div><?php endif ?>
        <div class="col"><label class="form-label">Extra note on the quotation (optional)</label><textarea name="notes" class="form-control" rows="2"><?= esc((string) $v('notes')) ?></textarea></div>
    </div></div>
</div>

<div class="col-lg-4"><div class="qq-sum">
    <div class="card mb-3"><div class="card-body" id="sum">
        <div class="text-muted small">Fill the details to see the price.</div>
    </div></div>
    <button class="btn btn-primary btn-lg w-100" id="genBtn"><i class="bi bi-lightning-charge"></i> <?= $id ? 'Save revised quotation' : 'Generate Quotation' ?></button>
    <div class="small text-muted mt-2">Makes the full proposal PDF: cover letter, bill of materials, price with subsidy, payback chart and terms. Prices come from <?= can('settings.manage') ? '<a href="' . site_url('admin/quotations/quick-settings') . '">Quick Quote Prices</a>' : 'the rate card set by your admin' ?>.</div>
</div></div>
</div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    const form = document.getElementById('qqForm'), $ = id => document.getElementById(id);
    const val = n => (form.querySelector('[name="qq[' + n + ']"]:checked') || form.querySelector('[name="qq[' + n + ']"]') || {}).value;
    const brandsBy = <?= json_encode($brandsBy) ?>, invSizes = <?= json_encode($invSizes) ?>;
    const invBrands = <?= json_encode($cfg['inverter_brands']) ?>, microBrands = <?= json_encode($cfg['micro_brands']) ?>, defWatt = <?= json_encode($cfg['default_watt']) ?>;
    const fillSelect = (sel, opts, keep) => {
        const cur = keep ? (sel.value || sel.dataset.current) : sel.dataset.current;
        sel.innerHTML = opts.map(o => { const [v, t] = Array.isArray(o) ? o : [o, o]; return '<option value="' + String(v).replace(/"/g, '&quot;') + '">' + t + '</option>'; }).join('');
        if (opts.some(o => String(Array.isArray(o) ? o[0] : o) === String(cur))) sel.value = cur;
        sel.dataset.current = '';
    };
    const money = n => '₹' + Math.round(n).toLocaleString('en-IN');
    function sync(e) {
        const sys = val('system'), tech = val('tech'), phase = val('phase');
        document.querySelectorAll('.mode-bill').forEach(el => el.hidden = val('mode') !== 'bill');
        document.querySelectorAll('.mode-kw').forEach(el => el.hidden = val('mode') !== 'kw');
        document.querySelectorAll('.struct-high').forEach(el => el.hidden = val('structure') !== 'high');
        document.querySelectorAll('.sys-hybrid').forEach(el => el.hidden = sys !== 'hybrid');
        document.querySelectorAll('.not-micro').forEach(el => el.hidden = sys === 'micro');
        if (e && e.target.name === 'qq[tech]' && defWatt[tech]) $('qq_watt').value = defWatt[tech];
        const brands = brandsBy[(sys === 'ongrid_ndcr' ? 'ndcr' : 'dcr') + '_' + tech] || [];
        fillSelect($('qq_brand'), brands.length ? brands : [['', '— No rate for this panel —']], true);
        const sizes = ((invSizes[sys === 'hybrid' ? 'hybrid' : 'ongrid'] || {})[phase] || []);
        fillSelect($('qq_invkw'), [['auto', 'Auto (based on system size)']].concat(sizes.map(k => [k, k + 'kW ' + phase + 'Ph'])), true);
        fillSelect($('qq_invbrand'), sys === 'micro' ? microBrands : invBrands, !(e && e.target.name === 'qq[system]'));
    }
    let timer;
    function preview() {
        clearTimeout(timer);
        timer = setTimeout(() => fetch('<?= site_url('admin/quotations/quick/preview') ?>', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(d => {
                if (!d.ok) { $('sum').innerHTML = '<div class="text-muted small">' + d.error + '</div>'; return; }
                const s = d.size, sub = d.subsidy;
                let h = '<div class="fw-semibold">' + d.title + '</div>'
                    + '<div class="small text-muted mb-2">' + s.panels + ' × ' + s.watt + 'W panels' + (s.inverter_kw ? ' · ' + s.inverter_kw + 'kW ' + s.phase + 'Ph inverter' : '') + '</div>'
                    + '<div class="small text-muted">Total (incl. GST)</div><div class="big">' + money(d.price.total) + '</div>'
                    + '<div class="small text-muted mb-2">' + money(d.price.per_kw) + ' per kW · GST ' + d.gst.blended + '% blended' + (d.price.package ? ' · package price' : '') + '</div>';
                if (sub.total > 0) h += '<div class="d-flex justify-content-between small text-success"><span>Subsidy (PM Surya Ghar' + (sub.state ? ' + ' + sub.state_label : '') + ')</span><span>− ' + money(sub.total) + '</span></div>'
                    + '<div class="d-flex justify-content-between fw-bold"><span>Net cost</span><span>' + money(d.net) + '</span></div>';
                h += '<hr class="my-2"><div class="d-flex justify-content-between small"><span>Bi-monthly saving</span><span>' + money(d.savings.bimonthly) + '</span></div>'
                    + '<div class="d-flex justify-content-between small"><span>Payback</span><span>' + (d.payback !== null ? d.payback + ' yrs' : '—') + '</span></div>';
                if (d.cost) h += '<div class="small text-muted mt-2">Cost ' + money(d.cost.total) + ' + ' + d.cost.margin + '% margin</div>';
                if (d.warnings.length) h += '<div class="alert alert-warning small py-1 px-2 mt-2 mb-0">' + d.warnings.join('<br>') + '</div>';
                $('sum').innerHTML = h;
            }).catch(() => {}), 300);
    }
    form.addEventListener('change', e => { sync(e); preview(); });
    form.addEventListener('input', preview);
    sync(); preview();
    const fill = p => ['name', 'phone', 'email', 'address', 'city', 'state'].forEach(k => { if (p[k]) $('party_' + k).value = p[k]; });
    const pc = $('pick_customer'), pl = $('pick_lead');
    if (pc) pc.addEventListener('change', function () { $('customer_id').value = this.value; $('lead_id').value = ''; if (pl) pl.value = ''; if (this.value) fill(JSON.parse(this.selectedOptions[0].dataset.p)); });
    if (pl) pl.addEventListener('change', function () {
        $('lead_id').value = this.value; $('customer_id').value = this.value && this.selectedOptions[0].dataset.customer !== '0' ? this.selectedOptions[0].dataset.customer : ''; if (pc) pc.value = '';
        if (this.value) { const p = JSON.parse(this.selectedOptions[0].dataset.p); fill(p); if (parseFloat(p.required_kw) > 0) { document.getElementById('qq_mode_kw').checked = true; form.querySelector('[name="qq[kw]"]').value = parseFloat(p.required_kw); sync(); preview(); } }
    });
    form.addEventListener('submit', () => { $('genBtn').disabled = true; $('genBtn').innerHTML = 'Preparing quotation…'; });
})();
</script>
<?= $this->endSection() ?>
