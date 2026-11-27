<?php
/** One-click proposal: cover letter, offer, savings & payback, terms. $qq = QuickQuote::proposal($q). */
use App\Libraries\QuickQuote as QQ;

$co    = (string) setting('Solar.companyName');
$cfg   = QQ::config();
$size  = $qq['size'];
$sub   = $qq['subsidy'];
$sav   = $qq['savings'];
$proj  = $qq['projection'];
$title = $qq['title'];
$first = trim(preg_replace('/^(mr|mrs|ms|dr)\.?\s+/i', '', (string) $q['party_name']));
$gstPct = (float) $q['subtotal'] > 0 ? round((float) $q['tax_total'] / (float) $q['subtotal'] * 100, 1) : 0;
$rs    = static fn ($n) => preg_replace('/\.00$/', '', money(round((float) $n)));
$w     = $cfg['warranty'];
$th    = static fn (string $t) => '<table style="width:100%;margin-top:4mm"><tr><td style="height:4mm"></td></tr><tr><td style="border-left:0.9mm solid #f0a020;padding:0.5mm 0 0.5mm 2mm;color:#0f3d4c;font-weight:bold;font-size:8.8pt;letter-spacing:.5pt">' . strtoupper($t) . '</td></tr><tr><td style="height:1.5mm"></td></tr></table>';
$scheme = $size['dcr'] && $sub['total'] > 0 ? ' in PM Surya Ghar Scheme' : '';
?>
<style>
@page { margin: 30mm 15mm 16mm 15mm; header: html_hdr; footer: html_ftr; }
body { font-family: dejavusans, sans-serif; font-size: 9.6pt; color: #1f2937; line-height: 1.45; }
.navy { color: #0f3d4c; } .amber { color: #b45309; } .muted { color: #6b7280; } .r { text-align: right; } .c { text-align: center; }
h2 { color: #0f3d4c; font-size: 14pt; text-align: center; margin: 0 0 2mm 0; }
.rule { width: 18mm; height: 0.9mm; background: #f0a020; margin: 0 auto 5mm auto; }
.to { background: #fdf8ee; border-left: 1mm solid #f0a020; padding: 3mm 4mm; margin-bottom: 4mm; }
.subj { background: #f5f5f4; border-left: 1mm solid #0f3d4c; padding: 3mm 4mm; margin-bottom: 5mm; border: 0.2mm solid #e5e7eb; }
table { border-collapse: collapse; width: 100%; }
.bom th { background: #0f3d4c; color: #fff; font-size: 8pt; text-align: left; padding: 2.4mm 2mm; text-transform: uppercase; }
.bom td { padding: 2.1mm 2mm; border-bottom: 0.2mm solid #eceff1; font-size: 9pt; vertical-align: top; }
.bom tr.alt td { background: #f8fafb; }
.tot td { padding: 2.4mm 2mm; }
.grand td { background: #0f3d4c; color: #fff; font-size: 13pt; font-weight: bold; padding: 3.5mm 2mm; }
.grand td.v { color: #f5b400; font-size: 16pt; }
.note { background: #fafaf9; border-left: 1mm solid #f0a020; padding: 3mm 4mm; margin-top: 4mm; font-size: 8.8pt; }
.sec { text-align: center; color: #9ca3af; font-size: 7.5pt; letter-spacing: 1.5pt; margin: 4mm 0 2.5mm 0; }
.flow td { padding: 3mm; text-align: center; font-size: 7.5pt; color: #374151; font-weight: bold; letter-spacing: .5pt; }
.ico { font-size: 18pt; color: #0f3d4c; font-weight: normal; }
.stat { padding: 3.5mm 4mm; border: 0.2mm solid #e5e7eb; vertical-align: top; }
.stat .k { font-size: 7pt; color: #6b7280; letter-spacing: 1pt; text-transform: uppercase; }
.stat .v { font-size: 17pt; font-weight: bold; color: #0f3d4c; }
.stat .s { font-size: 7.5pt; color: #9ca3af; }
.th { color: #0f3d4c; font-weight: bold; font-size: 8.8pt; border-left: 0.9mm solid #f0a020; padding-left: 2mm; margin: 5mm 0 2mm 0; text-transform: uppercase; letter-spacing: .5pt; }
.terms td { vertical-align: top; font-size: 8.8pt; }
.wbox { border: 0.4mm solid #f0a020; background: #fffbeb; padding: 2mm 3mm; }
</style>
<htmlpageheader name="hdr">
<table style="border-bottom:0.5mm solid #dbe4ea;padding-bottom:2mm"><tr>
    <td style="width:16mm"><?= brand_img('logo', 'max-height:13mm;max-width:16mm') ?></td>
    <td><div style="font-size:15pt;font-weight:bold;color:#0f3d4c"><?= esc($co) ?></div><div style="font-size:7.5pt;color:#0f3d4c"><?= esc(trim(str_replace("\n", ', ', (string) setting('Solar.companyAddress')))) ?></div></td>
    <td class="r" style="font-size:8pt;color:#0f3d4c"><?= esc((string) setting('Solar.companyPhone')) ?><br><?= esc((string) setting('Solar.companyEmail')) ?><?= setting('Solar.companyGstin') ? '<br>GSTIN: ' . esc(setting('Solar.companyGstin')) : '' ?></td>
</tr></table>
</htmlpageheader>
<htmlpagefooter name="ftr"><div style="border-top:0.2mm solid #e5e7eb;text-align:center;font-size:7pt;color:#6b7280;padding-top:1.5mm;letter-spacing:1pt"><?= esc(strtoupper($co)) ?> · <?= esc($q['quote_no']) ?> · PAGE {PAGENO} OF {nbpg}</div></htmlpagefooter>

<!-- ── Page 1: cover letter ── -->
<table style="margin-bottom:5mm;border-bottom:0.2mm solid #e5e7eb"><tr><td class="muted" style="padding-bottom:2mm">Ref: <?= esc($q['quote_no']) ?></td><td class="r muted" style="padding-bottom:2mm">Date: <?= fdate($q['quote_date']) ?></td></tr></table>
<div class="to"><div style="font-size:12pt;font-weight:bold;color:#0f3d4c"><?= esc($q['party_name']) ?></div>
    <div class="muted"><?= esc(trim(implode(', ', array_filter([(string) $q['party_address'], (string) $q['party_city'], (string) ($qq['party']['pincode'] ?? '')])), ', ')) ?>
    <?= $q['party_phone'] ? ' · Mobile: ' . esc($q['party_phone']) : '' ?><?= ! empty($qq['party']['eb_number']) ? '<br>EB consumer no: ' . esc($qq['party']['eb_number']) : '' ?></div></div>
<div class="subj"><b class="navy">Sub:</b> Quote for <b><?= esc($title) ?></b> Solar Power Plant<?= $scheme ?></div>
<p>Dear <?= esc($first ?: $q['party_name']) ?>,</p>
<p>We thank you for giving us this opportunity to be of service to you. Further to your discussion, find below our quotation for <b><?= QQ::fmt($size['kw']) ?> kW</b> with <?= esc($qq['structure_name']) ?> for your residence.</p>
<?php if ($w['panel_perf'] > 0): ?><p>Our solar panels carry a <b><?= (int) $w['panel_perf'] ?>-year performance warranty</b>, ensuring long-term reliability — and the savings projections in this document are backed by that guarantee.</p><?php endif ?>
<p>You can be rest assured of the quality of the product and we assure you of our best services while meeting your requirements.</p>
<p>Feel free to get in touch with the undersigned in case of any further queries and clarifications.</p>
<p>Thanking you and assuring you our best services at all the times,</p>
<p>Yours Faithfully</p>
<div style="margin-top:6mm;border-top:0.2mm solid #e5e7eb;padding-top:4mm">
    <?= brand_img('signature', 'max-height:16mm;max-width:45mm') ?>
    <div style="font-weight:bold;color:#0f3d4c;font-size:11pt">For <?= esc($co) ?></div>
    <?php if (! empty($qq['prepared_by'])): ?><div class="muted"><?= esc($qq['prepared_by']) ?><?= setting('Solar.companyPhone') ? ' · ' . esc(setting('Solar.companyPhone')) : '' ?></div><?php endif ?>
</div>

<!-- ── Page 2: offer ── -->
<pagebreak />
<h2>Offer for <?= esc($title) ?> Solar Power Plant</h2><div class="rule"></div>
<table class="bom">
    <thead><tr><th style="width:8%">S.No</th><th style="width:28%">Content</th><th style="width:27%">Description</th><th style="width:20%">Make</th><th class="c" style="width:8%">Qty</th><th class="c" style="width:9%">UOM</th></tr></thead>
    <tbody>
    <?php foreach ($qq['bom'] as $n => $b): ?>
        <tr class="<?= $n % 2 ? 'alt' : '' ?>"><td class="c muted"><?= $n + 1 ?></td><td><b><?= esc($b['item']) ?></b></td><td><?= esc((string) $b['desc']) ?></td><td><?= esc((string) $b['make']) ?></td><td class="c"><?= esc(QQ::fmt((float) $b['qty'])) ?></td><td class="c"><?= esc($b['uom']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<table class="tot" style="margin-top:1mm">
    <tr><td style="background:#f3f6f8;font-weight:bold;color:#0f3d4c">Sub Total (Before GST)</td><td class="r" style="background:#f3f6f8;font-weight:bold;color:#0f3d4c"><?= $rs($q['subtotal']) ?></td></tr>
    <tr><td style="background:#fffbeb;color:#92400e;font-weight:bold">GST @ <?= QQ::fmt($gstPct) ?>% (blended) included in total</td><td class="r" style="background:#fffbeb;color:#92400e;font-weight:bold"><?= $rs($q['tax_total']) ?></td></tr>
</table>
<table class="grand"><tr><td>TOTAL (Incl. GST)</td><td class="r v"><?= $rs($q['total']) ?></td></tr></table>
<?php if ((float) $q['subsidy_amount'] > 0): ?>
<table class="tot"><tr><td style="background:#fffbeb;color:#92400e;font-weight:bold">Less: Govt Subsidy (credited to your bank account)</td><td class="r" style="background:#fffbeb;color:#92400e;font-weight:bold">− <?= $rs($q['subsidy_amount']) ?></td></tr></table>
<table class="grand"><tr><td>NET COST AFTER SUBSIDY</td><td class="r v"><?= $rs($qq['net_cost']) ?></td></tr></table>
<?php endif ?>
<div class="muted" style="margin-top:1.5mm;font-size:8pt"><?= amount_in_words((float) $q['total']) ?></div>
<div class="note"><b class="navy">Note:</b>
    <ol style="margin:1mm 0 0 0">
        <?php if ((float) $q['subsidy_amount'] > 0): ?><li>For this Solar Plant, total Subsidy will be <b><?= $rs($q['subsidy_amount']) ?></b><?= $sub['central'] > 0 && $sub['state'] > 0 ? ' (PM Surya Ghar ' . $rs($sub['central']) . ' + ' . esc($sub['state_label']) . ' ' . $rs($sub['state']) . ')' : '' ?>, which will be directly credited to your bank account.</li><?php endif ?>
        <?php if ($qq['assume']['eb_meter'] > 0): ?><li>EB Meter Cost is Additional (Approx <?= $rs($qq['assume']['eb_meter']) ?>)</li><?php endif ?>
        <?php if ($size['dcr']): ?><li>We will facilitate the Net Metering and subsidy application process.</li><?php endif ?>
        <li>Price is valid for <?= (int) setting('Solar.quoteValidDays') ?> days from date of quotation (till <?= fdate($q['valid_until']) ?>).</li>
        <?php if ($q['notes']): ?><li><?= nl2br(esc($q['notes'])) ?></li><?php endif ?>
    </ol>
</div>

<!-- ── Page 3: how it works + return on investment ── -->
<pagebreak />
<div class="sec">HOW YOUR SYSTEM WORKS</div>
<table class="flow" style="background:#fffbea;border:0.3mm solid #fde68a"><tr>
    <td style="width:16%"><span class="ico" style="color:#f59e0b">☀</span><br><span style="color:#b45309;font-weight:normal">Daytime</span></td>
    <td><span class="ico">▦</span><br>SOLAR MODULE</td><td style="color:#9ca3af">- - - ►</td>
    <td><span class="ico">⌂</span><br>YOUR HOME</td><td style="color:#9ca3af">- - - ►</td>
    <td><span class="ico" style="color:#16a34a">⚡</span><br>GRID (EXCESS)</td></tr></table>
<table class="flow" style="background:#eef0fb;border:0.3mm solid #c7d2fe;margin-top:2.5mm"><tr>
    <td style="width:16%"><span class="ico" style="color:#4338ca">☾</span><br><span style="color:#7c3aed;font-weight:normal">Nighttime</span></td>
    <td><span class="ico" style="color:#16a34a">⚡</span><br><?= $qq['input']['system'] === 'hybrid' ? 'BATTERY / GRID' : 'GRID' ?></td><td style="color:#9ca3af">- - - ►</td>
    <td><span class="ico">⌂</span><br>YOUR HOME</td></tr></table>
<?php if ($qq['breakeven']): ?>
<table style="margin-top:3mm;background:#fff7ed;border:0.3mm solid #fed7aa;border-left:1.2mm solid #ea580c"><tr>
    <td style="padding:3.5mm 5mm;width:50%"><div style="font-size:7pt;color:#9ca3af;letter-spacing:1pt">INVESTMENT RECOVERED BY</div><div style="font-size:20pt;font-weight:bold;color:#b45309">Year <?= (int) $qq['breakeven'] ?></div></td>
    <td style="padding:3.5mm 5mm;border-left:0.2mm solid #fed7aa"><div style="font-size:7pt;color:#9ca3af;letter-spacing:1pt">THEN IT EARNS YOU</div><div style="font-size:16pt;font-weight:bold;color:#0f3d4c">Every Month</div><div class="s muted" style="font-size:7.5pt">for the next <?= max(0, (int) $qq['assume']['years'] - (int) ceil((float) $proj['payback'])) ?> years</div></td>
</tr></table>
<?php endif ?>
<div class="sec">YOUR RETURN ON INVESTMENT</div>
<table>
    <tr><td class="stat" style="background:#fffbeb;border-left:1mm solid #ea580c"><div style="font-size:7pt;color:#6b7280;letter-spacing:1pt">BI-MONTHLY SAVING</div><div style="font-size:17pt;font-weight:bold;color:#b45309"><?= $rs($sav['bimonthly']) ?></div><div style="font-size:7.5pt;color:#9ca3af">avg. per EB bill cycle</div></td>
        <td class="stat"><div style="font-size:7pt;color:#6b7280;letter-spacing:1pt">ANNUAL SAVING</div><div style="font-size:17pt;font-weight:bold;color:#0f3d4c"><?= $rs($sav['year']) ?></div><div style="font-size:7.5pt;color:#9ca3af">per year · <?= number_format($sav['gen_year']) ?> units generated</div></td></tr>
    <tr><td class="stat"><div style="font-size:7pt;color:#6b7280;letter-spacing:1pt">PAYBACK PERIOD</div><div style="font-size:17pt;font-weight:bold;color:#0f3d4c"><?= $proj['payback'] !== null ? QQ::fmt($proj['payback']) . ' yrs' : '—' ?></div><div style="font-size:7.5pt;color:#9ca3af">investment recovered</div></td>
        <td class="stat"><div style="font-size:7pt;color:#6b7280;letter-spacing:1pt"><?= (int) $qq['assume']['years'] ?>-YEAR NET GAIN</div><div style="font-size:17pt;font-weight:bold;color:#0f3d4c"><?= $rs($proj['net_gain']) ?></div><div style="font-size:7.5pt;color:#9ca3af">total benefit after cost</div></td></tr>
</table>
<table style="margin-top:4mm"><tr><td style="font-weight:bold;color:#374151;font-size:8.5pt">BREAKEVEN CHART — NET POSITION OVER <?= (int) $qq['assume']['years'] ?> YEARS</td>
    <td class="r" style="font-size:7.5pt"><span style="color:#ef4444">■</span> Pre-Breakeven &nbsp; <span style="color:#d4a72c">■</span> Breakeven &nbsp; <span style="color:#4ade80">■</span> Net Profit</td></tr></table>
<img src="data:image/svg+xml;base64,<?= base64_encode($qq['chart']) ?>" style="width:100%">
<div class="muted" style="font-size:7pt">Estimate: <?= QQ::fmt($sav['units_per_kw_day']) ?> units per kW per day, EB slab tariff, tariff rising <?= QQ::fmt($qq['assume']['escalation']) ?>% a year, panel output falling <?= QQ::fmt($qq['assume']['degradation']) ?>% a year. Actual savings depend on usage, shading and weather.</div>

<!-- ── Page 4: terms ── -->
<pagebreak />
<h2>Terms &amp; Conditions</h2><div class="rule"></div>
<table style="width:100%"><tr><td style="width:50%;vertical-align:top;padding-right:5mm">
    <?= $th('Validity of offer') ?>
    This offer is valid for <b><?= (int) setting('Solar.quoteValidDays') ?> days</b> from the date of offer and thereafter subject to our reconfirmation.
    <?php if ($cfg['timeline']): ?><?= $th('Project timeline') ?><div style="line-height:1.7"><?= nl2br(esc($cfg['timeline'])) ?></div><?php endif ?>
    <?php if ($size['dcr']): ?>
    <?= $th('Government subsidy (PM Surya Ghar)') ?>
    We will facilitate the Subsidy Process. Subsidy will be directly credited to your bank account by DISCOM after successful installation and inspection.<br>
    <b>Central (PM Surya Ghar):</b> ₹30,000 per kW up to 2 kW, ₹18,000 for the 3rd kW — maximum <b>₹78,000</b> for 3 kW and above.
    <?php if ($cfg['state_subsidy']): ?><br><b><?= esc($cfg['state_label']) ?>:</b> <?= esc(implode(', ', array_map(static fn ($s) => $rs($s['amount']) . ' (' . QQ::fmt($s['kw']) . ' kW)', $cfg['state_subsidy']))) ?> and above, for eligible applicants.<?php endif ?>
    <?php endif ?>
    <?php if ($cfg['stages']): ?><?= $th('Payment terms') ?>
        <?php foreach ($cfg['stages'] as $k => $st): ?><div style="padding-top:<?= $k ? '2mm' : '0' ?>"><b><?= QQ::fmt($st['pct']) ?>%</b> <?= esc($st['name']) ?><?= $st['text'] ? ', ' . esc($st['text']) : '' ?> — <b><?= $rs(round((float) $q['total'] * $st['pct'] / 100)) ?></b></div><?php endforeach ?>
    <?php endif ?>
    <?php if (trim($cfg['bank'])): ?><?= $th('Bank details') ?><b><?= nl2br(esc($cfg['bank'])) ?></b><?php endif ?>
</td><td style="width:50%;vertical-align:top">
    <?= $th('Warranty') ?>
    <b>Solar Panel</b>
    <?php if ($w['panel_perf'] > 0): ?><table style="width:100%;margin:2mm 0"><tr><td style="height:2mm"></td></tr><tr><td style="border:0.4mm solid #f0a020;background:#fffbeb;padding:2.5mm 3mm"><span style="font-size:16pt;font-weight:bold;color:#d97706"><?= (int) $w['panel_perf'] ?></span> <b style="color:#92400e">Year Performance Warranty</b><br><span style="color:#92400e;font-size:8pt">80% Power Output Guaranteed</span></td></tr><tr><td style="height:2mm"></td></tr></table><?php endif ?>
    <?php if ($w['panel_product'] > 0): ?>Product Warranty: <?= (int) $w['panel_product'] ?> Years<br><br><?php endif ?>
    <?php if ($w['amc'] > 0): ?><b>Free AMC: <?= (int) $w['amc'] ?> Years</b> (as per MNRE Guidelines)<br><br><?php endif ?>
    <?php if ($w['inverter'] !== ''): ?><b>Inverter</b> — <?= esc($w['inverter']) ?> Warranty<br><br><?php endif ?>
    <?php if ($w['bos'] !== ''): ?><b>Balance Of System</b> — <?= esc($w['bos']) ?> Warranty<br><br><?php endif ?>
    Parts and Components, which are repaired or replaced during such period, are warranted for the original warranty period only. The warranty shall not apply to defects resulting from:
    <ul style="margin:1mm 0 0 0"><li>Wilful damage or negligence, normal wear and tear</li><li>Installation and/or maintenance by third party without supplier's prior written consent</li><li>Misuse or abuse of Equipment</li><li>Modifications or alterations without supplier's written consent</li></ul>
</td></tr></table>
<?php if ($q['design_path'] && preg_match('/\.(jpe?g|png)$/i', $q['design_path']) && is_file(WRITEPATH . 'uploads/' . $q['design_path'])): ?>
<pagebreak />
<h2>Proposed Layout</h2><div class="rule"></div>
<div class="c"><img src="<?= esc(WRITEPATH . 'uploads/' . $q['design_path']) ?>" style="max-width:100%;max-height:200mm"></div>
<?php endif ?>
