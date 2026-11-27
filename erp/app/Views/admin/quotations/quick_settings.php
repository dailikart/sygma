<?= $this->extend('admin/layout') ?>
<?php
use App\Libraries\QuickQuote as QQ;

$n = static fn ($name, $val, string $unit = '', string $step = 'any') => '<div class="input-group input-group-sm" style="max-width:190px">'
    . ($unit === '₹' ? '<span class="input-group-text">₹</span>' : '')
    . '<input type="number" step="' . $step . '" min="0" class="form-control text-end" name="' . $name . '" value="' . esc((string) QQ::fmt((float) $val), 'attr') . '">'
    . ($unit !== '' && $unit !== '₹' ? '<span class="input-group-text">' . esc($unit) . '</span>' : '') . '</div>';
$row = static fn (string $label, string $input, string $help = '') => '<tr><td>' . esc($label) . ($help ? '<div class="small text-muted">' . esc($help) . '</div>' : '') . '</td><td class="text-end" style="width:200px">' . $input . '</td></tr>';
$sel = static function (string $name, array $opts, $cur) {
    $h = '<select class="form-select form-select-sm" name="' . $name . '">';
    foreach ($opts as $k => $l) {
        $h .= '<option value="' . esc((string) $k, 'attr') . '" ' . ((string) $k === (string) $cur ? 'selected' : '') . '>' . esc(is_array($l) ? $l[0] : $l) . '</option>';
    }

    return $h . '</select>';
};
$c = $cfg['costs'];
$itemSel = static function (string $name, $cur) use ($items) {
    $h = '<select class="form-select form-select-sm" name="' . $name . '"><option value="0">— Auto: match by name —</option>';
    foreach ($items as $it) {
        $h .= '<option value="' . (int) $it['id'] . '" ' . ((int) $cur === (int) $it['id'] ? 'selected' : '') . '>' . esc($it['name']) . '</option>';
    }

    return $h . '</select>';
};
$systems = array_map(static fn ($s) => $s[0], QQ::SYSTEMS);
$techs   = array_map(static fn ($s) => $s[0], QQ::TECHS);
?>
<?= $this->section('title') ?>Quick Quote Prices<?= $this->endSection() ?>
<?= $this->section('content') ?>
<style>.qs td { vertical-align: middle; } .qs .card-header { font-weight: 600; background: transparent; border-left: 3px solid var(--bs-primary); }</style>
<form method="post" action="<?= site_url('admin/quotations/quick-settings') ?>" class="qs" style="max-width:980px" autocomplete="off">
<?= csrf_field() ?>
<input type="hidden" name="cfg[_form]" value="1">
<div class="d-flex align-items-center gap-2 mb-3">
    <div><div class="small text-muted">Saved for the whole team. Only admins can change prices. Every quick quote uses these numbers.</div>
        <?php if (! $saved): ?><div class="small text-warning-emphasis"><i class="bi bi-exclamation-circle"></i> These are sample prices. Check them and click Save once.</div><?php endif ?></div>
    <button class="btn btn-primary ms-auto">Save</button>
</div>
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-prices">Prices</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#t-items">Items</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#t-terms">Terms &amp; agreement</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#t-savings">Savings &amp; subsidy</button></li>
</ul>
<div class="tab-content">
<!-- ── Prices ── -->
<div class="tab-pane fade show active" id="t-prices">
    <div class="card mb-3"><div class="card-header">General</div><table class="table table-sm mb-0">
        <?= $row('Margin %', $n('cfg[margin]', $cfg['margin'], '%'), 'Added on top of the component cost below (all costs include GST).') ?>
        <?= $row('Peak sun hours', $n('cfg[sun_hours]', $cfg['sun_hours'], 'hrs')) ?>
        <?= $row('Panel efficiency factor', $n('cfg[efficiency]', $cfg['efficiency'], '%'), 'Units per kW per day = sun hours × efficiency (now ' . QQ::fmt(QQ::unitsPerKwDay($cfg)) . ').') ?>
        <?= $row('Default system type', $sel('cfg[default_system]', $systems, $cfg['default_system'])) ?>
        <?= $row('Default panel technology', $sel('cfg[default_tech]', $techs, $cfg['default_tech'])) ?>
        <?php foreach (QQ::TECHS as $t => [$tn]): ?><?= $row($tn . ' default wattage', $n('cfg[default_watt][' . $t . ']', $cfg['default_watt'][$t], 'W', '1')) ?><?php endforeach ?>
        <tr><td colspan="2"><div class="form-check"><input type="hidden" name="cfg[adjust_admin_only]" value="0"><input class="form-check-input" type="checkbox" name="cfg[adjust_admin_only]" value="1" id="adj" <?= $cfg['adjust_admin_only'] ? 'checked' : '' ?>><label class="form-check-label" for="adj">Only admins can change the price (Adjust)</label></div></td></tr>
    </table></div>

    <div class="card mb-3"><div class="card-header">Panel prices <span class="fw-normal small text-muted">₹ per watt, GST included · DCR = made in India, subsidy eligible · NDCR = imported, no subsidy</span></div>
        <table class="table table-sm mb-0"><thead><tr><th>Brand</th><th>Technology</th><th>Type</th><th class="text-end">₹ / W</th><th>Inventory item</th><th></th></tr></thead>
        <tbody data-rows="panels">
        <?php foreach ($cfg['panels'] as $k => $p): ?>
            <tr><td><input class="form-control form-control-sm" name="cfg[panels][<?= $k ?>][brand]" value="<?= esc($p['brand']) ?>"></td><td><?= $sel("cfg[panels][$k][tech]", $techs, $p['tech']) ?></td>
                <td><?= $sel("cfg[panels][$k][dcr]", ['1' => 'DCR', '0' => 'NDCR'], (string) $p['dcr']) ?></td><td><?= $n("cfg[panels][$k][rate]", $p['rate'], '₹') ?></td><td><?= $itemSel("cfg[panels][$k][item_id]", $p['item_id'] ?? 0) ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr>
        <?php endforeach ?></tbody></table>
        <template data-for="panels"><tr><td><input class="form-control form-control-sm" name="cfg[panels][__i__][brand]"></td><td><?= $sel('cfg[panels][__i__][tech]', $techs, 'bifacial') ?></td><td><?= $sel('cfg[panels][__i__][dcr]', ['1' => 'DCR', '0' => 'NDCR'], '1') ?></td><td><?= $n('cfg[panels][__i__][rate]', 0, '₹') ?></td><td><?= $itemSel('cfg[panels][__i__][item_id]', 0) ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr></template>
        <div class="card-body py-2"><button type="button" class="btn btn-sm btn-outline-primary add" data-for="panels">+ Add panel price</button></div></div>

    <div class="card mb-3"><div class="card-header">Common costs</div><table class="table table-sm mb-0">
        <?= $row('Mounting structure', $n('cfg[costs][structure]', $c['structure'], '₹/kW')) ?>
        <?= $row('High-rise / elevated structure extra', $n('cfg[costs][structure_high]', $c['structure_high'], '₹/kW')) ?>
        <?= $row('DCDB', $n('cfg[costs][dcdb]', $c['dcdb'], '₹')) ?>
        <?= $row('ACDB', $n('cfg[costs][acdb]', $c['acdb'], '₹')) ?>
        <?= $row('DC cable', $n('cfg[costs][dc_cable]', $c['dc_cable'], '₹')) ?>
        <?= $row('Copper (AC) cable', $n('cfg[costs][ac_cable]', $c['ac_cable'], '₹')) ?>
        <?= $row('Miscellaneous', $n('cfg[costs][misc]', $c['misc'], '₹')) ?>
        <?= $row('Installation', $n('cfg[costs][installation]', $c['installation'], '₹/kW')) ?>
        <?= $row('Transport', $n('cfg[costs][transport]', $c['transport'], '₹/kW')) ?>
        <?= $row('Lightning arrestor', $n('cfg[costs][la]', $c['la'], '₹')) ?>
        <?= $row('Earthing kit (per pit)', $n('cfg[costs][earthing]', $c['earthing'], '₹')) ?>
        <?= $row('Earth pits per system', $n('cfg[costs][earth_pits]', $c['earth_pits'], 'Nos', '1')) ?>
        <?= $row('Earth cable', $n('cfg[costs][earth_cable_m]', $c['earth_cable_m'], '₹/m')) ?>
        <?= $row('Earth cable length', $n('cfg[costs][earth_cable_len]', $c['earth_cable_len'], 'm')) ?>
        <?= $row('Extra cable per floor (high-rise)', $n('cfg[costs][floor_cable_m]', $c['floor_cable_m'], 'm')) ?>
        <?= $row('AC cable for extra length', $n('cfg[costs][cable_per_m]', $c['cable_per_m'], '₹/m')) ?>
        <?= $row('Micro inverter (per panel)', $n('cfg[costs][micro_per_panel]', $c['micro_per_panel'], '₹')) ?>
        <?= $row('Battery (hybrid)', $n('cfg[costs][battery_per_kwh]', $c['battery_per_kwh'], '₹/kWh')) ?>
    </table></div>

    <?php foreach (['ongrid' => 'On-grid inverter prices', 'hybrid' => 'Hybrid inverter prices'] as $kind => $label): ?>
    <div class="card mb-3"><div class="card-header"><?= $label ?></div>
        <table class="table table-sm mb-0"><thead><tr><th>Phase</th><th>Size (kW)</th><th class="text-end">Price</th><th>Inventory item</th><th></th></tr></thead><tbody data-rows="<?= $kind ?>">
        <?php foreach ($cfg['inverters'][$kind] as $k => $r): ?>
            <tr><td><?= $sel("cfg[inverters][$kind][$k][phase]", ['1' => 'Single (1Ph)', '3' => 'Three (3Ph)'], (string) $r['phase']) ?></td><td><?= $n("cfg[inverters][$kind][$k][kw]", $r['kw'], 'kW') ?></td><td><?= $n("cfg[inverters][$kind][$k][price]", $r['price'], '₹') ?></td><td><?= $itemSel("cfg[inverters][$kind][$k][item_id]", $r['item_id'] ?? 0) ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr>
        <?php endforeach ?></tbody></table>
        <template data-for="<?= $kind ?>"><tr><td><?= $sel("cfg[inverters][$kind][__i__][phase]", ['1' => 'Single (1Ph)', '3' => 'Three (3Ph)'], '1') ?></td><td><?= $n("cfg[inverters][$kind][__i__][kw]", 0, 'kW') ?></td><td><?= $n("cfg[inverters][$kind][__i__][price]", 0, '₹') ?></td><td><?= $itemSel("cfg[inverters][$kind][__i__][item_id]", 0) ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr></template>
        <div class="card-body py-2"><button type="button" class="btn btn-sm btn-outline-primary add" data-for="<?= $kind ?>">+ Add size &amp; price</button></div></div>
    <?php endforeach ?>

    <div class="card mb-3"><div class="card-header">Extra components <span class="fw-normal small text-muted">added to every quick quote</span></div>
        <table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Make</th><th class="text-end">Price</th><th>Per kW?</th><th>Inventory item</th><th></th></tr></thead><tbody data-rows="extras">
        <?php foreach ($cfg['extras'] as $k => $x): ?>
            <tr><td><input class="form-control form-control-sm" name="cfg[extras][<?= $k ?>][name]" value="<?= esc($x['name']) ?>"></td><td><input class="form-control form-control-sm" name="cfg[extras][<?= $k ?>][make]" value="<?= esc($x['make']) ?>"></td><td><?= $n("cfg[extras][$k][price]", $x['price'], '₹') ?></td><td><?= $sel("cfg[extras][$k][per_kw]", ['0' => 'Fixed', '1' => 'Per kW'], (string) $x['per_kw']) ?></td><td><?= $itemSel("cfg[extras][$k][item_id]", $x['item_id'] ?? 0) ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr>
        <?php endforeach ?></tbody></table>
        <template data-for="extras"><tr><td><input class="form-control form-control-sm" name="cfg[extras][__i__][name]" placeholder="e.g. Walkway"></td><td><input class="form-control form-control-sm" name="cfg[extras][__i__][make]"></td><td><?= $n('cfg[extras][__i__][price]', 0, '₹') ?></td><td><?= $sel('cfg[extras][__i__][per_kw]', ['0' => 'Fixed', '1' => 'Per kW'], '0') ?></td><td><?= $itemSel('cfg[extras][__i__][item_id]', 0) ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr></template>
        <div class="card-body py-2"><button type="button" class="btn btn-sm btn-outline-primary add" data-for="extras">+ Add component</button></div></div>

    <div class="card mb-3"><div class="card-header">Packages <span class="fw-normal small text-muted">fixed price, GST included</span></div><div class="card-body">
        <div class="form-check mb-2"><input type="hidden" name="cfg[use_packages]" value="0"><input class="form-check-input" type="checkbox" name="cfg[use_packages]" value="1" id="pk" <?= $cfg['use_packages'] ? 'checked' : '' ?>><label class="form-check-label" for="pk">Use package prices</label></div>
        <div class="small text-muted mb-2">A 3 kW package is used for any size from 3 kW up to below 3.5 kW of the same system type; other sizes use the cost + margin price.</div>
        <table class="table table-sm mb-0"><thead><tr><th>System type</th><th>Size (kW)</th><th>Name (optional)</th><th class="text-end">Price</th><th></th></tr></thead><tbody data-rows="packages">
        <?php foreach ($cfg['packages'] as $k => $p): ?>
            <tr><td><?= $sel("cfg[packages][$k][system]", $systems, $p['system']) ?></td><td><?= $n("cfg[packages][$k][kw]", $p['kw'], 'kW') ?></td><td><input class="form-control form-control-sm" name="cfg[packages][<?= $k ?>][name]" value="<?= esc($p['name']) ?>"></td><td><?= $n("cfg[packages][$k][price]", $p['price'], '₹') ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr>
        <?php endforeach ?></tbody></table>
        <template data-for="packages"><tr><td><?= $sel('cfg[packages][__i__][system]', $systems, 'ongrid_dcr') ?></td><td><?= $n('cfg[packages][__i__][kw]', 0, 'kW') ?></td><td><input class="form-control form-control-sm" name="cfg[packages][__i__][name]"></td><td><?= $n('cfg[packages][__i__][price]', 0, '₹') ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr></template>
        <button type="button" class="btn btn-sm btn-outline-primary add mt-2" data-for="packages">+ Add package</button></div></div>

    <div class="card mb-3"><div class="card-header">Brand names</div><div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label small">Inverter brands (one per line)</label><textarea class="form-control" rows="5" name="cfg[inverter_brands]"><?= esc(implode("\n", $cfg['inverter_brands'])) ?></textarea></div>
        <div class="col-md-6"><label class="form-label small">Micro inverter brands</label><textarea class="form-control" rows="5" name="cfg[micro_brands]"><?= esc(implode("\n", $cfg['micro_brands'])) ?></textarea></div>
        <?php foreach (['structure' => 'Mounting structure', 'dcdb' => 'DCDB', 'acdb' => 'ACDB', 'dc_cable' => 'DC cable', 'ac_cable' => 'AC cable', 'conduit' => 'Conduits', 'earthing' => 'Earth pit', 'earth_cable' => 'Earth cable', 'la' => 'Lightning arrestor', 'battery' => 'Battery', 'transport' => 'Transport'] as $k => $l): ?>
            <div class="col-md-4"><label class="form-label small"><?= esc($l) ?> make</label><input class="form-control form-control-sm" name="cfg[makes][<?= $k ?>]" value="<?= esc($cfg['makes'][$k]) ?>"></div>
        <?php endforeach ?>
        <div class="col-12 small text-muted">Panel brands come from the panel price list above.</div>
    </div></div>
</div>

<!-- ── Items ── -->
<div class="tab-pane fade" id="t-items">
    <div class="card mb-3"><div class="card-header">Quotation lines</div><div class="card-body">
        <div class="form-check"><input class="form-check-input" type="radio" name="cfg[line_gst]" value="items" id="lg1" <?= $cfg['line_gst'] === 'items' ? 'checked' : '' ?>>
            <label class="form-check-label" for="lg1"><b>Inventory items</b> (recommended): one line per part, using the item below with its own HSN and GST rate. The invoice made from the quotation then dispatches the right stock. The price is shared across the lines by their cost; the B2C invoice still prints as one system line.</label></div>
        <div class="form-check mt-2"><input class="form-check-input" type="radio" name="cfg[line_gst]" value="split" id="lg2" <?= $cfg['line_gst'] === 'split' ? 'checked' : '' ?>>
            <label class="form-check-label" for="lg2"><b>Two lines</b>: supply at the goods GST rate and installation at the services GST rate (the 70 : 30 split under Terms &amp; agreement → GST split). No stock items.</label></div>
    </div></div>
    <div class="card mb-3"><div class="card-header">Inventory item for each part <span class="fw-normal small text-muted">panels, inverters and extra components are linked on their own rows in Prices</span></div>
        <?php if (! $items): ?><div class="card-body text-muted small">No items in Inventory yet. Add them in Inventory → Items, then link them here.</div><?php endif ?>
        <table class="table table-sm mb-0">
        <?php foreach (\App\Libraries\QuickQuote::ITEM_KEYS as $k => $label): ?>
            <tr><td><?= esc($label) ?></td><td style="width:55%"><?= $itemSel("cfg[item_map][$k]", $cfg['item_map'][$k] ?? 0) ?></td></tr>
        <?php endforeach ?>
        </table>
        <div class="card-body small text-muted">"Auto" picks the first item whose name fits: for panels the brand and wattage (e.g. <i>Adani 550W Bifacial DCR Module</i>), for inverters the brand and kW (e.g. <i>Growatt 4kW On-Grid Inverter</i>), for the rest keywords such as DCDB, ACDB, structure, earth pit, lightning arrestor. The quick quote shows each match with a <i class="bi bi-magic"></i> so salespeople can see it.</div>
    </div>
</div>

<!-- ── Terms & agreement ── -->
<div class="tab-pane fade" id="t-terms">
    <div class="card mb-3"><div class="card-header">Payment stages <span class="fw-normal small text-muted">percent of the total; must add up to 100%</span></div>
        <div class="card-body"><div data-rows="stages">
        <?php foreach ($cfg['stages'] as $k => $st): ?>
            <div class="border rounded p-2 mb-2 stage"><div class="d-flex gap-2 mb-2"><input class="form-control form-control-sm" name="cfg[stages][<?= $k ?>][name]" value="<?= esc($st['name']) ?>">
                <div style="width:130px"><?= $n("cfg[stages][$k][pct]", $st['pct'], '%') ?></div><button type="button" class="btn btn-sm btn-outline-danger del">✕</button></div>
                <textarea class="form-control form-control-sm" rows="2" name="cfg[stages][<?= $k ?>][text]"><?= esc($st['text']) ?></textarea></div>
        <?php endforeach ?></div>
        <template data-for="stages"><div class="border rounded p-2 mb-2 stage"><div class="d-flex gap-2 mb-2"><input class="form-control form-control-sm" name="cfg[stages][__i__][name]" placeholder="Stage name"><div style="width:130px"><?= $n('cfg[stages][__i__][pct]', 0, '%') ?></div><button type="button" class="btn btn-sm btn-outline-danger del">✕</button></div><textarea class="form-control form-control-sm" rows="2" name="cfg[stages][__i__][text]" placeholder="When it is paid"></textarea></div></template>
        <button type="button" class="btn btn-sm btn-outline-primary add" data-for="stages">+ Add stage</button> <span class="ms-2 fw-semibold small" id="stageTotal"></span></div></div>
    <div class="card mb-3"><div class="card-header">Agreement <span class="fw-normal small text-muted">consumer–vendor agreement made from a quotation (Quotation → Agreement)</span></div><div class="card-body">
        <div class="row g-3 mb-2">
            <div class="col-md-6"><label class="form-label small">Company legal name (blank = company name in Settings)</label><input class="form-control form-control-sm" name="cfg[legal_name]" value="<?= esc($cfg['legal_name']) ?>" placeholder="<?= esc((string) setting('Solar.companyName')) ?>"></div>
            <div class="col-md-6"><label class="form-label small">Registered office address (blank = address in Settings)</label><textarea class="form-control form-control-sm" rows="2" name="cfg[legal_address]"><?= esc($cfg['legal_address']) ?></textarea></div>
        </div>
        <div class="form-check mb-2"><input type="hidden" name="cfg[agreement_stamp]" value="0"><input class="form-check-input" type="checkbox" name="cfg[agreement_stamp]" value="1" id="ast" <?= $cfg['agreement_stamp'] ? 'checked' : '' ?>><label class="form-check-label" for="ast">Print on stamp paper: leave the top half of page 1 blank (default; can be changed per agreement)</label></div>
        <div class="small text-muted mb-1">Click where the detail should go in the text, then click the detail. The app fills it in for each customer.</div>
        <div class="d-flex flex-wrap gap-1 mb-2"><?php foreach (\App\Libraries\Agreement::PLACEHOLDERS as $k => $l): ?><button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ph" data-ph="{<?= $k ?>}"><?= esc($l) ?></button><?php endforeach ?></div>
        <div class="small text-muted mb-1"><code># title</code> · <code>## centred line</code> · <code>### underlined heading</code> · <code>- numbered clause</code> · <code>&gt; small note</code> · <code>**bold**</code></div>
        <textarea class="form-control font-monospace" rows="16" name="cfg[agreement_text]" id="agText"><?= esc($cfg['agreement_text']) ?></textarea>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="agReset" data-text="<?= esc(\App\Libraries\Agreement::defaultText(), 'attr') ?>">Reset to the standard PM Surya Ghar text</button>
        <div class="small text-muted mt-1">Check the text with your DISCOM's current format before use.</div>
    </div></div>
    <div class="card mb-3"><div class="card-header">Project timeline</div><div class="card-body"><textarea class="form-control" rows="3" name="cfg[timeline]"><?= esc($cfg['timeline']) ?></textarea></div></div>
    <div class="card mb-3"><div class="card-header">Warranty</div><table class="table table-sm mb-0">
        <?= $row('Panel performance warranty', $n('cfg[warranty][panel_perf]', $cfg['warranty']['panel_perf'], 'years', '1')) ?>
        <?= $row('Panel product warranty', $n('cfg[warranty][panel_product]', $cfg['warranty']['panel_product'], 'years', '1')) ?>
        <?= $row('Free AMC', $n('cfg[warranty][amc]', $cfg['warranty']['amc'], 'years', '1')) ?>
        <tr><td>Inverter warranty</td><td><input class="form-control form-control-sm" name="cfg[warranty][inverter]" value="<?= esc($cfg['warranty']['inverter']) ?>"></td></tr>
        <tr><td>Balance of system warranty</td><td><input class="form-control form-control-sm" name="cfg[warranty][bos]" value="<?= esc($cfg['warranty']['bos']) ?>"></td></tr>
    </table></div>
    <div class="card mb-3"><div class="card-header">Bank details (printed on the quotation)</div><div class="card-body"><textarea class="form-control" rows="4" name="cfg[bank]"><?= esc($cfg['bank']) ?></textarea></div></div>
    <div class="card mb-3"><div class="card-header">GST split</div><table class="table table-sm mb-0">
        <?= $row('Goods (supply) GST', $n('cfg[gst][goods]', $cfg['gst']['goods'], '%')) ?>
        <?= $row('Services (installation) GST', $n('cfg[gst][service]', $cfg['gst']['service'], '%')) ?>
        <?= $row('Services share of the price', $n('cfg[gst][service_share]', $cfg['gst']['service_share'], '%'), '70 : 30 rule: 70% goods + 30% services. Blended GST now ' . QQ::fmt((1 - $cfg['gst']['service_share'] / 100) * $cfg['gst']['goods'] + $cfg['gst']['service_share'] / 100 * $cfg['gst']['service']) . '%.') ?>
        <tr><td>HSN for supply / SAC for installation</td><td><div class="d-flex gap-1"><input class="form-control form-control-sm" name="cfg[gst][hsn]" value="<?= esc($cfg['gst']['hsn']) ?>"><input class="form-control form-control-sm" name="cfg[gst][sac]" value="<?= esc($cfg['gst']['sac']) ?>"></div></td></tr>
    </table></div>
</div>

<!-- ── Savings & subsidy ── -->
<div class="tab-pane fade" id="t-savings">
    <div class="card mb-3"><div class="card-header">Subsidy</div><div class="card-body">
        <div class="form-check mb-2"><input type="hidden" name="cfg[central_subsidy]" value="0"><input class="form-check-input" type="checkbox" name="cfg[central_subsidy]" value="1" id="cs" <?= $cfg['central_subsidy'] ? 'checked' : '' ?>><label class="form-check-label" for="cs">PM Surya Ghar central subsidy (₹30,000/kW up to 2 kW, ₹18,000 for the 3rd kW, max ₹78,000)</label></div>
        <label class="form-label small">State subsidy name</label><input class="form-control form-control-sm mb-2" style="max-width:300px" name="cfg[state_label]" value="<?= esc($cfg['state_label']) ?>">
        <table class="table table-sm mb-0"><thead><tr><th>From system size</th><th class="text-end">State subsidy</th><th></th></tr></thead><tbody data-rows="state">
        <?php foreach ($cfg['state_subsidy'] as $k => $s): ?>
            <tr><td><?= $n("cfg[state_subsidy][$k][kw]", $s['kw'], 'kW') ?></td><td><?= $n("cfg[state_subsidy][$k][amount]", $s['amount'], '₹') ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr>
        <?php endforeach ?></tbody></table>
        <template data-for="state"><tr><td><?= $n('cfg[state_subsidy][__i__][kw]', 0, 'kW') ?></td><td><?= $n('cfg[state_subsidy][__i__][amount]', 0, '₹') ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr></template>
        <button type="button" class="btn btn-sm btn-outline-primary add mt-2" data-for="state">+ Add slab</button>
        <div class="small text-muted mt-1">Remove all rows if your state gives no subsidy.</div>
        <hr><?= '<table class="table table-sm mb-0">' . $row('EB meter cost (printed as additional)', $n('cfg[eb_meter]', $cfg['eb_meter'], '₹')) . '</table>' ?>
    </div></div>
    <div class="card mb-3"><div class="card-header">EB tariff slabs <span class="fw-normal small text-muted">bi-monthly, domestic; used for the saving on the bill</span></div>
        <table class="table table-sm mb-0"><thead><tr><th>Units up to (0 = above the last slab)</th><th class="text-end">₹ per unit</th><th></th></tr></thead><tbody data-rows="tariff">
        <?php foreach ($cfg['tariff'] as $k => $t): ?>
            <tr><td><?= $n("cfg[tariff][$k][upto]", $t['upto'], 'units', '1') ?></td><td><?= $n("cfg[tariff][$k][rate]", $t['rate'], '₹') ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr>
        <?php endforeach ?></tbody></table>
        <template data-for="tariff"><tr><td><?= $n('cfg[tariff][__i__][upto]', 0, 'units', '1') ?></td><td><?= $n('cfg[tariff][__i__][rate]', 0, '₹') ?></td><td><button type="button" class="btn btn-sm btn-link text-danger del">✕</button></td></tr></template>
        <div class="card-body py-2"><button type="button" class="btn btn-sm btn-outline-primary add" data-for="tariff">+ Add slab</button></div></div>
    <div class="card mb-3"><div class="card-header">Payback chart</div><table class="table table-sm mb-0">
        <?= $row('Tariff increase per year', $n('cfg[escalation]', $cfg['escalation'], '%')) ?>
        <?= $row('Panel output loss per year', $n('cfg[degradation]', $cfg['degradation'], '%')) ?>
        <?= $row('Years shown', $n('cfg[years]', $cfg['years'], 'years', '1')) ?>
    </table></div>
</div>
</div>
<div class="d-flex gap-2 mb-4"><button class="btn btn-primary px-5">Save</button>
    <button class="btn btn-outline-danger ms-auto" name="reset" value="1" formnovalidate data-confirm="Reset every quick quote price and term to the sample defaults?">↺ Reset to defaults</button></div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    let seq = 1000;
    document.querySelectorAll('.add').forEach(b => b.addEventListener('click', () => {
        const tpl = document.querySelector('template[data-for="' + b.dataset.for + '"]');
        const box = document.querySelector('[data-rows="' + b.dataset.for + '"]');
        box.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', 'n' + (seq++)));
        stages();
    }));
    document.addEventListener('click', e => { if (e.target.classList.contains('del')) { (e.target.closest('tr') || e.target.closest('.stage')).remove(); stages(); } });
    function stages() {
        const t = [...document.querySelectorAll('[name$="[pct]"]')].reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
        const el = document.getElementById('stageTotal');
        el.textContent = 'Total: ' + t + '%' + (Math.abs(t - 100) < 0.01 ? ' ✓' : ' (should be 100%)');
        el.className = 'ms-2 fw-semibold small ' + (Math.abs(t - 100) < 0.01 ? 'text-success' : 'text-danger');
    }
    document.addEventListener('input', e => { if (e.target.name && e.target.name.endsWith('[pct]')) stages(); });
    stages();
    const ta = document.getElementById('agText');
    document.querySelectorAll('.ph').forEach(b => b.addEventListener('click', () => {
        const st = ta.selectionStart, en = ta.selectionEnd, t = b.dataset.ph;
        ta.value = ta.value.slice(0, st) + t + ta.value.slice(en); ta.focus(); ta.selectionStart = ta.selectionEnd = st + t.length;
    }));
    document.getElementById('agReset').addEventListener('click', e => { if (confirm('Replace the agreement text with the standard text?')) ta.value = e.target.dataset.text; });
})();
</script>
<?= $this->endSection() ?>
