<?php

namespace App\Libraries;

/**
 * One-click solar quotation: sizes the system (from the EB bill or a kW
 * figure), prices it from the admin's rate card (component cost + margin, or a
 * fixed package price), works out subsidy, blended GST, savings and a
 * year-by-year payback, and lists the bill of materials.
 *
 * Everything is driven by one settings array (Admin → Quotations → Quick quote
 * settings), saved as JSON in setting('Solar.quickQuote').
 */
class QuickQuote
{
    public const SYSTEMS = [
        'ongrid_dcr'  => ['On-Grid DCR', 'Govt subsidy eligible'],
        'ongrid_ndcr' => ['On-Grid NDCR', 'Cost-effective'],
        'hybrid'      => ['Hybrid', 'With battery backup'],
        'micro'       => ['Micro Inverter', 'Premium option'],
    ];

    public const TECHS = [
        'bifacial' => ['Bifacial', 'Standard'],
        'topcon'   => ['TOPCon', 'High efficiency'],
        'hjt'      => ['HJT', 'Ultra premium'],
    ];

    public const STRUCTURES = [
        'low'  => ['Low-Rise / Ground', 'Ground Mount GI'],
        'high' => ['High-Rise / Flat', 'Elevated GI Structure'],
    ];

    public const WATTS     = [440, 540, 545, 550, 555, 575, 580, 585, 590, 600, 610, 620, 625, 650];
    public const DC_CABLES = ['4 sq.mm', '6 sq.mm', '10 sq.mm'];
    public const AC_CABLES = ['2C, 4 sq.mm', '2C, 6 sq.mm', '3C, 4 sq.mm', '3C, 6 sq.mm', '4C, 4 sq.mm', '4C, 6 sq.mm', '4C, 10 sq.mm', '4C, 16 sq.mm'];

    /** Rate card used until an admin saves their own. */
    public static function defaults(): array
    {
        return [
            'margin'            => 10,
            'sun_hours'         => 4.5,
            'efficiency'        => 80,
            'default_system'    => 'ongrid_dcr',
            'default_tech'      => 'bifacial',
            'default_watt'      => ['bifacial' => 550, 'topcon' => 580, 'hjt' => 620],
            'use_packages'      => false,
            'adjust_admin_only' => false,
            'packages'          => [],
            'panels'            => [
                ['brand' => 'Adani', 'tech' => 'bifacial', 'dcr' => 1, 'rate' => 30],
                ['brand' => 'Waaree', 'tech' => 'bifacial', 'dcr' => 1, 'rate' => 30],
                ['brand' => 'Tata Power', 'tech' => 'bifacial', 'dcr' => 1, 'rate' => 32],
                ['brand' => 'Premier', 'tech' => 'bifacial', 'dcr' => 1, 'rate' => 29],
                ['brand' => 'Adani', 'tech' => 'topcon', 'dcr' => 1, 'rate' => 32],
                ['brand' => 'Waaree', 'tech' => 'topcon', 'dcr' => 1, 'rate' => 32],
                ['brand' => 'Rayzon', 'tech' => 'hjt', 'dcr' => 1, 'rate' => 36],
                ['brand' => 'Adani', 'tech' => 'bifacial', 'dcr' => 0, 'rate' => 18],
                ['brand' => 'Waaree', 'tech' => 'bifacial', 'dcr' => 0, 'rate' => 18],
                ['brand' => 'Waaree', 'tech' => 'topcon', 'dcr' => 0, 'rate' => 20],
                ['brand' => 'Rayzon', 'tech' => 'hjt', 'dcr' => 0, 'rate' => 24],
            ],
            'costs' => [
                'structure' => 5000, 'structure_high' => 1500, 'dcdb' => 2000, 'acdb' => 2000,
                'dc_cable' => 2000, 'ac_cable' => 2000, 'misc' => 2000, 'installation' => 3000, 'transport' => 1000,
                'la' => 1000, 'earthing' => 1000, 'earth_pits' => 3, 'earth_cable_m' => 160, 'earth_cable_len' => 30,
                'floor_cable_m' => 8, 'cable_per_m' => 150, 'micro_per_panel' => 8500, 'battery_per_kwh' => 15000,
            ],
            'inverters' => [
                'ongrid' => [
                    ['phase' => 1, 'kw' => 2, 'price' => 12000], ['phase' => 1, 'kw' => 3, 'price' => 14000],
                    ['phase' => 1, 'kw' => 4, 'price' => 17500], ['phase' => 1, 'kw' => 5, 'price' => 21000],
                    ['phase' => 3, 'kw' => 4, 'price' => 22000], ['phase' => 3, 'kw' => 5, 'price' => 26000],
                    ['phase' => 3, 'kw' => 6, 'price' => 34000], ['phase' => 3, 'kw' => 8, 'price' => 46000],
                    ['phase' => 3, 'kw' => 10, 'price' => 62000], ['phase' => 3, 'kw' => 15, 'price' => 78000],
                    ['phase' => 3, 'kw' => 20, 'price' => 95000],
                ],
                'hybrid' => [
                    ['phase' => 1, 'kw' => 3, 'price' => 38000], ['phase' => 1, 'kw' => 5, 'price' => 55000],
                    ['phase' => 1, 'kw' => 6, 'price' => 62000], ['phase' => 3, 'kw' => 8, 'price' => 110000],
                    ['phase' => 3, 'kw' => 10, 'price' => 130000],
                ],
            ],
            'extras'          => [],
            'item_map'        => array_fill_keys(array_keys(self::ITEM_KEYS), 0),
            'line_gst'        => 'items',
            'legal_name'      => '',
            'legal_address'   => '',
            'agreement_stamp' => true,
            'agreement_text'  => Agreement::defaultText(),
            'inverter_brands' => ['Growatt', 'Deye', 'Solis', 'Sungrow', 'Havells', 'Polycab'],
            'micro_brands'    => ['Enphase', 'Hoymiles', 'APsystems'],
            'makes'           => [
                'structure' => 'VRM/GreenField', 'dcdb' => 'Nordic/GreenField', 'acdb' => 'Nordic/GreenField',
                'dc_cable' => 'Polycab/Orbit', 'ac_cable' => 'Polycab/Orbit', 'conduit' => 'Finolex', 'earthing' => 'Customized',
                'earth_cable' => 'Polycab', 'la' => 'Branded', 'battery' => 'Exide', 'transport' => 'Roadways',
            ],
            'central_subsidy' => true,
            'state_label'     => 'TN Govt',
            'state_subsidy'   => [['kw' => 1, 'amount' => 5000], ['kw' => 2, 'amount' => 10000], ['kw' => 3, 'amount' => 22000]],
            // Bi-monthly domestic slabs (TNEB): units up to => ₹ per unit. kw 0 = everything above.
            'tariff'          => [
                ['upto' => 100, 'rate' => 0], ['upto' => 200, 'rate' => 2.35], ['upto' => 400, 'rate' => 4.70],
                ['upto' => 500, 'rate' => 6.30], ['upto' => 600, 'rate' => 8.40], ['upto' => 800, 'rate' => 9.45],
                ['upto' => 1000, 'rate' => 10.50], ['upto' => 0, 'rate' => 11.55],
            ],
            'escalation'  => 5,
            'degradation' => 0.5,
            'years'       => 25,
            'gst'         => ['goods' => 5, 'service' => 18, 'service_share' => 30, 'hsn' => '8541', 'sac' => '995442'],
            'stages'      => [
                ['name' => 'Booking Advance', 'pct' => 10, 'text' => 'at the time of booking and confirmation of the project'],
                ['name' => 'Material Payment', 'pct' => 75, 'text' => 'when the project materials are delivered to or made available at the site, before commencement of installation'],
                ['name' => 'Balance Payment (Upon Net Metering)', 'pct' => 15, 'text' => 'upon completion of the installation and completion of the net metering process, subject to applicable DISCOM procedures'],
            ],
            'timeline'    => "Materials Delivery — 1–2 weeks after order confirmation\nInstallation — 1 week\nNet Metering Processing — 3–4 weeks",
            'warranty'    => ['panel_perf' => 30, 'panel_product' => 12, 'amc' => 5, 'inverter' => '7–10 Years', 'bos' => '1 Year'],
            'bank'        => "Bank: \nBranch: \nA/C No: \nIFSC: ",
            'eb_meter'    => 5000,
        ];
    }

    /** The saved rate card merged over the defaults. */
    public static function config(): array
    {
        $saved = json_decode((string) setting('Solar.quickQuote'), true);

        return is_array($saved) ? self::sanitize($saved) : self::defaults();
    }

    /** Cleans a posted / saved rate card. Unknown keys are dropped; missing ones fall back to defaults. */
    public static function sanitize(array $in): array
    {
        $d   = self::defaults();
        $num = static fn ($v, $def = 0) => is_numeric(str_replace(',', '', (string) $v)) ? (float) str_replace(',', '', (string) $v) : (float) $def;
        $str = static fn ($v) => trim(strip_tags((string) $v));
        // From the settings form a list with every row deleted is simply absent: treat it as empty, not as "use defaults".
        $form = ! empty($in['_form']);
        $lst  = static fn (string $k, $def) => $in[$k] ?? ($form ? [] : $def);
        $rows = static fn ($list, callable $map, callable $keep) => array_values(array_filter(array_map($map, is_array($list) ? $list : []), $keep));

        $out = [
            'margin'            => max(0.0, $num($in['margin'] ?? null, $d['margin'])),
            'sun_hours'         => max(0.0, $num($in['sun_hours'] ?? null, $d['sun_hours'])),
            'efficiency'        => min(100.0, max(0.0, $num($in['efficiency'] ?? null, $d['efficiency']))),
            'default_system'    => isset(self::SYSTEMS[$in['default_system'] ?? '']) ? $in['default_system'] : $d['default_system'],
            'default_tech'      => isset(self::TECHS[$in['default_tech'] ?? '']) ? $in['default_tech'] : $d['default_tech'],
            'default_watt'      => [],
            'use_packages'      => ! empty($in['use_packages']),
            'adjust_admin_only' => ! empty($in['adjust_admin_only']),
            'packages'          => $rows($in['packages'] ?? [], static fn ($r) => [
                'system' => isset(self::SYSTEMS[$r['system'] ?? '']) ? $r['system'] : 'ongrid_dcr',
                'kw' => $num($r['kw'] ?? 0), 'price' => $num($r['price'] ?? 0), 'name' => $str($r['name'] ?? ''),
            ], static fn ($r) => $r['kw'] > 0 && $r['price'] > 0),
            'panels'            => $rows($lst('panels', $d['panels']), static fn ($r) => [
                'brand' => $str($r['brand'] ?? ''), 'tech' => isset(self::TECHS[$r['tech'] ?? '']) ? $r['tech'] : 'bifacial',
                'dcr' => empty($r['dcr']) ? 0 : 1, 'rate' => $num($r['rate'] ?? 0), 'item_id' => (int) ($r['item_id'] ?? 0),
            ], static fn ($r) => $r['brand'] !== '' && $r['rate'] > 0),
            'costs'             => [],
            'inverters'         => [],
            'extras'            => $rows($in['extras'] ?? [], static fn ($r) => [
                'name' => $str($r['name'] ?? ''), 'make' => $str($r['make'] ?? ''), 'price' => $num($r['price'] ?? 0), 'per_kw' => empty($r['per_kw']) ? 0 : 1,
                'item_id' => (int) ($r['item_id'] ?? 0),
            ], static fn ($r) => $r['name'] !== ''),
            'item_map'          => [],
            'line_gst'          => in_array($in['line_gst'] ?? '', ['items', 'split'], true) ? $in['line_gst'] : $d['line_gst'],
            'legal_name'        => $str($in['legal_name'] ?? ''),
            'legal_address'     => trim(str_replace("\r\n", "\n", strip_tags((string) ($in['legal_address'] ?? '')))),
            'agreement_stamp'   => array_key_exists('agreement_stamp', $in) ? ! empty($in['agreement_stamp']) : $d['agreement_stamp'],
            'agreement_text'    => trim(str_replace("\r\n", "\n", (string) ($in['agreement_text'] ?? ''))) ?: $d['agreement_text'],
            'inverter_brands'   => self::list($in['inverter_brands'] ?? $d['inverter_brands']),
            'micro_brands'      => self::list($in['micro_brands'] ?? $d['micro_brands']),
            'makes'             => [],
            'central_subsidy'   => array_key_exists('central_subsidy', $in) ? ! empty($in['central_subsidy']) : $d['central_subsidy'],
            'state_label'       => $str($in['state_label'] ?? $d['state_label']),
            'state_subsidy'     => $rows($lst('state_subsidy', $d['state_subsidy']), static fn ($r) => ['kw' => $num($r['kw'] ?? 0), 'amount' => $num($r['amount'] ?? 0)],
                static fn ($r) => $r['kw'] > 0 && $r['amount'] > 0),
            'tariff'            => $rows($lst('tariff', $d['tariff']), static fn ($r) => ['upto' => $num($r['upto'] ?? 0), 'rate' => $num($r['rate'] ?? 0)],
                static fn ($r) => $r['upto'] > 0 || $r['rate'] > 0),
            'escalation'        => max(0.0, $num($in['escalation'] ?? null, $d['escalation'])),
            'degradation'       => max(0.0, $num($in['degradation'] ?? null, $d['degradation'])),
            'years'             => (int) min(40, max(5, $num($in['years'] ?? null, $d['years']))),
            'gst'               => [],
            'stages'            => $rows($lst('stages', $d['stages']), static fn ($r) => ['name' => $str($r['name'] ?? ''), 'pct' => $num($r['pct'] ?? 0), 'text' => $str($r['text'] ?? '')],
                static fn ($r) => $r['name'] !== '' && $r['pct'] > 0),
            'timeline'          => trim(str_replace("\r\n", "\n", strip_tags((string) ($in['timeline'] ?? $d['timeline'])))),
            'warranty'          => [],
            'bank'              => trim(str_replace("\r\n", "\n", strip_tags((string) ($in['bank'] ?? $d['bank'])))),
            'eb_meter'          => max(0.0, $num($in['eb_meter'] ?? null, $d['eb_meter'])),
        ];
        foreach (self::TECHS as $t => $_) {
            $w = (int) ($in['default_watt'][$t] ?? $d['default_watt'][$t]);
            $out['default_watt'][$t] = $w > 0 ? $w : $d['default_watt'][$t];
        }
        foreach ($d['costs'] as $k => $v) {
            $out['costs'][$k] = max(0.0, $num($in['costs'][$k] ?? null, $v));
        }
        foreach (['ongrid', 'hybrid'] as $kind) {
            $out['inverters'][$kind] = $rows($in['inverters'][$kind] ?? ($form ? [] : $d['inverters'][$kind]), static fn ($r) => [
                'phase' => (int) ($r['phase'] ?? 1) === 3 ? 3 : 1, 'kw' => $num($r['kw'] ?? 0), 'price' => $num($r['price'] ?? 0), 'item_id' => (int) ($r['item_id'] ?? 0),
            ], static fn ($r) => $r['kw'] > 0 && $r['price'] > 0);
            usort($out['inverters'][$kind], static fn ($a, $b) => [$a['phase'], $a['kw']] <=> [$b['phase'], $b['kw']]);
        }
        foreach (self::ITEM_KEYS as $k => $_) {
            $out['item_map'][$k] = max(0, (int) ($in['item_map'][$k] ?? 0));
        }
        foreach ($d['makes'] as $k => $v) {
            $out['makes'][$k] = $str($in['makes'][$k] ?? $v);
        }
        foreach ($d['gst'] as $k => $v) {
            $out['gst'][$k] = in_array($k, ['hsn', 'sac'], true) ? $str($in['gst'][$k] ?? $v) : max(0.0, $num($in['gst'][$k] ?? null, $v));
        }
        $out['gst']['service_share'] = min(100, $out['gst']['service_share']);
        foreach ($d['warranty'] as $k => $v) {
            $out['warranty'][$k] = is_int($v) ? (int) $num($in['warranty'][$k] ?? null, $v) : $str($in['warranty'][$k] ?? $v);
        }
        usort($out['state_subsidy'], static fn ($a, $b) => $a['kw'] <=> $b['kw']);
        // Slabs in ascending order; the "above" slab (upto 0) last.
        usort($out['tariff'], static fn ($a, $b) => ($a['upto'] ?: INF) <=> ($b['upto'] ?: INF));
        usort($out['packages'], static fn ($a, $b) => [$a['system'], $a['kw']] <=> [$b['system'], $b['kw']]);

        return $out;
    }

    /** "A, B\nC" or an array → clean unique list. */
    public static function list($v): array
    {
        $items = is_array($v) ? $v : preg_split('/[\n,]+/', (string) $v);

        return array_values(array_unique(array_filter(array_map(static fn ($x) => trim(strip_tags((string) $x)), $items), 'strlen')));
    }

    public static function panelBrands(array $cfg, ?string $tech = null, ?bool $dcr = null): array
    {
        $rows = array_filter($cfg['panels'], static fn ($p) => ($tech === null || $p['tech'] === $tech) && ($dcr === null || (bool) $p['dcr'] === $dcr));

        return array_values(array_unique(array_column($rows, 'brand')));
    }

    /** Units generated per kW per day = peak sun hours × efficiency factor. */
    public static function unitsPerKwDay(array $cfg): float
    {
        return round($cfg['sun_hours'] * $cfg['efficiency'] / 100, 3);
    }

    /** Bi-monthly bill (energy charges only) for a number of units, from the slabs. */
    public static function slabBill(float $units, array $slabs): float
    {
        $bill = 0.0;
        $prev = 0.0;
        foreach ($slabs as $s) {
            $top = $s['upto'] > 0 ? $s['upto'] : INF;
            if ($units <= $prev) {
                break;
            }
            $bill += (min($units, $top) - $prev) * $s['rate'];
            $prev = $top;
        }

        return round($bill, 2);
    }

    /** Units that give a bill amount (inverse of slabBill). */
    public static function unitsFromBill(float $amount, array $slabs): float
    {
        $prev = 0.0;
        $left = $amount;
        foreach ($slabs as $s) {
            $top  = $s['upto'] > 0 ? $s['upto'] : INF;
            $cost = ($top - $prev) * $s['rate'];
            if ($s['rate'] > 0 && $left <= $cost) {
                return round($prev + $left / $s['rate'], 1);
            }
            $left -= is_finite($cost) ? $cost : 0;
            $prev = $top;
            if (! is_finite($top)) {
                break;
            }
        }

        return round($prev, 1);
    }

    public static function stateSubsidy(float $kw, array $cfg): float
    {
        $amt = 0.0;
        foreach ($cfg['state_subsidy'] as $s) {
            if ($kw + 1e-6 >= $s['kw']) {
                $amt = $s['amount'];
            }
        }

        return $amt;
    }

    /**
     * Sizes and prices a system.
     *
     * @param array $in  form input: mode (bill|kw), units, bill_amount, kw, structure, floors, dc_cable, ac_cable,
     *                   system, tech, watt, panel_brand, phase, inverter_kw (auto|number), inverter_brand,
     *                   battery_kwh, adjust (₹ +/-), subsidy (bool)
     * @return array{ok: bool, error?: string, ...}
     */
    public static function build(array $in, array $cfg, string $installer = '', ?array $items = null): array
    {
        $num    = static fn ($k, $d = 0) => is_numeric(str_replace(',', '', (string) ($in[$k] ?? ''))) ? (float) str_replace(',', '', (string) $in[$k]) : (float) $d;
        $system = isset(self::SYSTEMS[$in['system'] ?? '']) ? $in['system'] : $cfg['default_system'];
        $tech   = isset(self::TECHS[$in['tech'] ?? '']) ? $in['tech'] : $cfg['default_tech'];
        $watt   = (int) $num('watt', $cfg['default_watt'][$tech]);
        $watt   = $watt >= 100 && $watt <= 1000 ? $watt : $cfg['default_watt'][$tech];
        $struct = isset(self::STRUCTURES[$in['structure'] ?? '']) ? $in['structure'] : 'low';
        $floors = $struct === 'high' ? (int) max(0, min(50, $num('floors', 0))) : 0;
        $mode   = ($in['mode'] ?? 'kw') === 'bill' ? 'bill' : 'kw';
        $upkd   = self::unitsPerKwDay($cfg);
        $dcr    = $system !== 'ongrid_ndcr';
        $warn   = [];

        // ── Size ──
        $units = $num('units');
        if ($mode === 'bill') {
            if ($units <= 0 && $num('bill_amount') > 0) {
                $units = self::unitsFromBill($num('bill_amount'), $cfg['tariff']);
            }
            if ($units <= 0 || $upkd <= 0) {
                return ['ok' => false, 'error' => 'Enter the bi-monthly units (or the bill amount) from the EB bill.'];
            }
            $target = $units / 60 / $upkd;
        } else {
            $target = $num('kw');
            if ($target <= 0) {
                return ['ok' => false, 'error' => 'Enter the system size in kW.'];
            }
        }
        if ($target > 500) {
            return ['ok' => false, 'error' => 'System size is too large for a quick quote (max 500 kW).'];
        }
        $panels = max(1, (int) ceil($target * 1000 / $watt - 1e-6));
        $kw     = round($panels * $watt / 1000, 2);

        // ── Inverter ──
        $phase = (int) ($in['phase'] ?? 0);
        $phase = in_array($phase, [1, 3], true) ? $phase : ($kw > 6 ? 3 : 1);
        $inv   = null;
        $micro = $system === 'micro';
        $kind  = $system === 'hybrid' ? 'hybrid' : 'ongrid';
        if (! $micro) {
            $table = array_values(array_filter($cfg['inverters'][$kind], static fn ($r) => $r['phase'] === $phase));
            $want  = ($in['inverter_kw'] ?? 'auto') !== 'auto' && $num('inverter_kw') > 0 ? $num('inverter_kw') : null;
            if ($want !== null) {
                foreach ($table as $r) {
                    if (abs($r['kw'] - $want) < 0.01) {
                        $inv = $r;
                    }
                }
                $inv ??= ['phase' => $phase, 'kw' => $want, 'price' => 0];
            } else {
                // Smallest size that covers DC/AC ratio ≤ 1.15 (4.4 kW of panels → 4 kW inverter).
                foreach ($table as $r) {
                    if ($r['kw'] + 1e-6 >= $kw / 1.15) {
                        $inv = $r;
                        break;
                    }
                }
                $inv ??= $table ? end($table) : ['phase' => $phase, 'kw' => round($kw / 1.15, 1), 'price' => 0];
            }
            if ($inv['price'] <= 0) {
                $warn[] = 'No price for a ' . self::fmt($inv['kw']) . ' kW ' . $phase . 'Ph ' . ($kind === 'hybrid' ? 'hybrid' : 'on-grid') . ' inverter in the rate card.';
            }
        }
        $brands   = $micro ? $cfg['micro_brands'] : $cfg['inverter_brands'];
        $invBrand = trim((string) ($in['inverter_brand'] ?? '')) ?: ($brands[0] ?? '');
        $battery  = $system === 'hybrid' ? max(0.0, $num('battery_kwh', 5)) : 0;

        // ── Panel rate ──
        $brand = trim((string) ($in['panel_brand'] ?? ''));
        $rate  = null;
        foreach ([[$brand, $tech], [null, $tech]] as [$b, $t]) {
            foreach ($cfg['panels'] as $p) {
                if ((bool) $p['dcr'] === $dcr && $p['tech'] === $t && ($b === null || strcasecmp($p['brand'], $b) === 0)) {
                    $rate = $p;
                    break 2;
                }
            }
        }
        if (! $rate) {
            $warn[] = 'No ' . ($dcr ? 'DCR' : 'NDCR') . ' ' . self::TECHS[$tech][0] . ' panel rate in the rate card.';
        }
        $brand = $brand !== '' ? $brand : ($rate['brand'] ?? '');

        // ── Components: what is supplied, its cost (₹, GST included) and the stock item used ──
        $c        = $cfg['costs'];
        $m        = $cfg['makes'];
        $map      = $cfg['item_map'];
        $extraLen = $floors * $c['floor_cable_m'];
        $earthLen = $c['earth_cable_len'] + $extraLen;
        $techName = self::TECHS[$tech][0];
        $sysName  = self::SYSTEMS[$system][0];
        $invDesc  = $micro ? 'Micro inverter (1 per panel)' : self::fmt($inv['kw']) . 'kW ' . $phase . 'Ph' . ($kind === 'hybrid' ? ' Hybrid' : '');
        $row      = static fn (string $key, string $item, string $desc, string $make, float $qty, string $uom, float $cost, int $itemId = 0) => [
            'key' => $key, 'item' => $item, 'desc' => $desc, 'make' => $make, 'qty' => round($qty, 2), 'uom' => $uom, 'cost' => round($cost, 2), 'item_id' => $itemId,
        ];
        $bom   = [];
        $bom[] = $row('panels', 'Module ' . $techName . ' ' . ($dcr ? 'DCR' : 'NDCR'), $techName . ' ' . $watt . 'W', $brand, $panels, 'Nos', $panels * $watt * ($rate['rate'] ?? 0), (int) ($rate['item_id'] ?? 0));
        $bom[] = $row('inverter', ($micro ? 'Micro' : ($kind === 'hybrid' ? 'Hybrid' : 'On-Grid')) . ' Inverter', $invDesc, $invBrand, $micro ? $panels : 1, $micro ? 'Nos' : 'No',
            $micro ? $panels * $c['micro_per_panel'] : (float) ($inv['price'] ?? 0), $micro ? $map['micro'] : (int) ($inv['item_id'] ?? 0));
        if ($battery > 0) {
            $bom[] = $row('battery', 'Battery Bank', self::fmt($battery) . ' kWh Lithium', $m['battery'], 1, 'Set', $battery * $c['battery_per_kwh'], $map['battery']);
        }
        $bom[] = $row('structure', 'Module Mounting Structure', self::STRUCTURES[$struct][1], $m['structure'], $panels, 'Nos',
            $kw * ($c['structure'] + ($struct === 'high' ? $c['structure_high'] : 0)), $struct === 'high' && $map['structure_high'] ? $map['structure_high'] : $map['structure']);
        if (! $micro) {
            $bom[] = $row('dcdb', 'DCDB', $kw > 6 ? '2 in 2 out' : '1 in 1 out', $m['dcdb'], 1, 'No', $c['dcdb'], $map['dcdb']);
        }
        $bom[] = $row('acdb', 'ACDB', $phase === 3 ? '3 Phase' : '1 in 1 out', $m['acdb'], 1, 'No', $c['acdb'], $map['acdb']);
        if (! $micro) {
            $bom[] = $row('dc_cable', 'DC Cable', (string) ($in['dc_cable'] ?? '') ?: self::DC_CABLES[0], $m['dc_cable'], 1, 'As Req', $c['dc_cable'], $map['dc_cable']);
        }
        $bom[] = $row('ac_cable', 'Copper Cable, PVC Insulated', (string) ($in['ac_cable'] ?? '') ?: self::AC_CABLES[2], $m['ac_cable'], 1, 'As Req', $c['ac_cable'] + $extraLen * $c['cable_per_m'], $map['ac_cable']);
        $bom[] = $row('conduit', 'Flexible Conduits', 'PVC', $m['conduit'], 1, 'As Req', 0, $map['conduit']);
        $bom[] = $row('earth_pit', 'Earth Pit', 'Earth pit & Chambers', $m['earthing'], $c['earth_pits'], 'Nos', $c['earth_pits'] * $c['earthing'], $map['earth_pit']);
        $bom[] = $row('earth_cable', 'Earth Cable', self::fmt($earthLen) . 'm', $m['earth_cable'], $earthLen, 'Mts', $earthLen * $c['earth_cable_m'], $map['earth_cable']);
        $bom[] = $row('la', 'Lightning Arrestor', 'Spike', $m['la'], 1, 'No', $c['la'], $map['la']);
        $bom[] = $row('misc', 'Miscellaneous Items', 'plugs, screws, MC4 connectors', 'Multiple', 1, 'Set', $c['misc'], $map['misc']);
        foreach ($cfg['extras'] as $i => $x) {
            $bom[] = $row('extra' . $i, $x['name'], '', $x['make'] ?: 'Branded', $x['per_kw'] ? $kw : 1, $x['per_kw'] ? 'KW' : 'No', $x['per_kw'] ? $x['price'] * $kw : $x['price'], (int) ($x['item_id'] ?? 0));
        }
        $bom[] = $row('transport', 'Transport', 'Roadways', $m['transport'], $kw, 'Lot', $kw * $c['transport'], $map['transport']);
        $bom[] = $row('installation', 'Installation', 'End to End', $installer ?: 'Our team', $kw, 'KW', $kw * $c['installation'], $map['installation']);
        $bom   = array_values(array_filter($bom, static fn ($b) => $b['qty'] > 0));
        if ($items !== null) {
            $bom = self::attachItems($bom, $items, ['brand' => $brand, 'watt' => $watt, 'tech' => $tech, 'dcr' => $dcr, 'inv_kw' => $micro ? null : $inv['kw'], 'kind' => $micro ? 'micro' : $kind, 'phase' => $phase]);
        }
        $cost = array_column($bom, 'cost', 'key');
        $costTotal = round(array_sum($cost), 2);
        $price     = round($costTotal * (1 + $cfg['margin'] / 100));
        $package   = null;
        if ($cfg['use_packages']) {
            foreach ($cfg['packages'] as $p) {
                // A 3 kW package covers 3 kW up to (not including) 3.5 kW of the same system type.
                if ($p['system'] === $system && $kw + 1e-6 >= $p['kw'] && $kw < $p['kw'] + 0.5 - 1e-6) {
                    $package = $p;
                }
            }
            if ($package) {
                $price = round($package['price']);
            }
        }
        $adjust = round($num('adjust'));
        $total  = max(0, $price + $adjust);

        // ── Quotation lines and GST ──
        $lines = self::lines($bom, $total, $cfg, self::fmt($kw) . ' kW ' . $sysName, $panels, $watt);
        [, $lSub, $lTax] = Tax::lines($lines);
        $g = $cfg['gst'];

        // ── Subsidy ──
        $wantSub = ! array_key_exists('subsidy', $in) || ! empty($in['subsidy']);
        $central = $dcr && $wantSub && $cfg['central_subsidy'] ? Quote::suggestedSubsidy($kw) : 0.0;
        $state   = $dcr && $wantSub ? self::stateSubsidy($kw, $cfg) : 0.0;

        // ── Savings ──
        $genYear  = round($kw * $upkd * 365);
        $genBm    = $genYear / 6;
        $consume  = $mode === 'bill' ? $units : $genBm;
        $saveBm   = round(self::slabBill($consume, $cfg['tariff']) - self::slabBill(max(0, $consume - $genBm), $cfg['tariff']));
        $saveYear = $saveBm * 6;

        return [
            'ok'     => true,
            'warnings' => $warn,
            'input'  => [
                'mode' => $mode, 'units' => $mode === 'bill' ? $units : null, 'bill_amount' => $num('bill_amount') ?: null, 'kw' => $mode === 'kw' ? $target : null,
                'structure' => $struct, 'floors' => $floors, 'dc_cable' => (string) ($in['dc_cable'] ?? ''), 'ac_cable' => (string) ($in['ac_cable'] ?? ''),
                'system' => $system, 'tech' => $tech, 'watt' => $watt, 'panel_brand' => $brand, 'phase' => $phase,
                'inverter_kw' => (string) ($in['inverter_kw'] ?? 'auto'), 'inverter_brand' => $invBrand, 'battery_kwh' => $battery,
                'adjust' => $adjust, 'subsidy' => $wantSub,
            ],
            'size'   => ['target_kw' => round($target, 2), 'panels' => $panels, 'watt' => $watt, 'kw' => $kw, 'phase' => $phase,
                'inverter_kw' => $micro ? null : $inv['kw'], 'dcr' => $dcr],
            'title'  => self::fmt($kw) . 'kW ' . $sysName,
            'system_name' => $sysName,
            'structure_name' => self::STRUCTURES[$struct][1],
            'bom'    => $bom,
            'cost'   => ['lines' => array_map(static fn ($v) => round($v, 2), $cost), 'total' => $costTotal, 'margin' => $cfg['margin']],
            'price'  => ['package' => $package, 'list' => $price, 'adjust' => $adjust, 'total' => $total, 'per_kw' => $kw > 0 ? round($total / $kw) : 0],
            'lines'  => $lines,
            'gst'    => ['mode' => $cfg['line_gst'], 'blended' => $lSub > 0 ? round($lTax / $lSub * 100, 1) : 0.0, 'goods_rate' => $g['goods'], 'service_rate' => $g['service'],
                'subtotal' => $lSub, 'tax' => $lTax],
            'subsidy' => ['central' => $central, 'state' => $state, 'state_label' => $cfg['state_label'], 'total' => $central + $state],
            'savings' => ['units_per_kw_day' => $upkd, 'gen_year' => $genYear, 'gen_bimonthly' => round($genBm), 'bimonthly' => $saveBm, 'year' => $saveYear,
                'tariff_avg' => $genYear > 0 ? round($saveYear / $genYear, 2) : 0],
            'assume' => ['escalation' => $cfg['escalation'], 'degradation' => $cfg['degradation'], 'years' => $cfg['years'], 'eb_meter' => $cfg['eb_meter']],
        ];
    }

    /**
     * Year-by-year net position: year 0 = −net cost, then savings grow with the
     * tariff escalation and shrink with panel degradation.
     *
     * @return array{rows: list<array{year:int, saving:float, net:float}>, payback: ?float, net_gain: float}
     */
    public static function projection(float $yearOne, float $netCost, float $escalation, float $degradation, int $years): array
    {
        $rows    = [['year' => 0, 'saving' => 0.0, 'net' => -$netCost]];
        $net     = -$netCost;
        $payback = $netCost <= 0 ? 0.0 : null;
        for ($y = 1; $y <= $years; $y++) {
            $s   = $yearOne * (1 + $escalation / 100) ** ($y - 1) * (1 - $degradation / 100) ** ($y - 1);
            $was = $net;
            $net += $s;
            if ($payback === null && $net >= 0 && $s > 0) {
                $payback = round($y - 1 + (-$was) / $s, 1);
            }
            $rows[] = ['year' => $y, 'saving' => round($s), 'net' => round($net)];
        }

        return ['rows' => $rows, 'payback' => $payback, 'net_gain' => round($net)];
    }

    /**
     * Everything the proposal PDF needs for a saved quick quotation, or null
     * for an ordinary quotation. Net cost and totals come from the quotation
     * itself, so a later manual edit of the price is respected.
     */
    public static function proposal(array $q): ?array
    {
        $spec = json_decode((string) ($q['quick_spec'] ?? ''), true);
        if (! is_array($spec) || empty($spec['savings'])) {
            return null;
        }
        // Quotations saved before items were linked kept each row as a plain list.
        $spec['bom'] = array_map(static fn ($b) => isset($b[0]) ? ['key' => '', 'item' => $b[0], 'desc' => (string) $b[1], 'make' => (string) $b[2], 'qty' => $b[3], 'uom' => $b[4], 'cost' => 0, 'item_id' => 0] : $b, $spec['bom'] ?? []);
        $net  = round((float) $q['total'] - (float) $q['subsidy_amount'], 2);
        $a    = $spec['assume'];
        $proj = self::projection((float) $spec['savings']['year'], $net, (float) $a['escalation'], (float) $a['degradation'], (int) $a['years']);
        $year = (int) date('Y', strtotime((string) $q['quote_date']));

        return $spec + [
            'net_cost'   => $net,
            'projection' => $proj,
            'breakeven'  => $proj['payback'] !== null ? $year + (int) ceil($proj['payback']) : null,
            'chart'      => self::chartSvg($proj['rows']),
        ];
    }

    /** Bar chart of the net position per year (red before break-even, amber the break-even year, green after). */
    public static function chartSvg(array $rows, int $w = 720, int $h = 330): string
    {
        $vals = array_column($rows, 'net');
        $max  = max(1, max($vals));
        $min  = min(0, min($vals));
        $pl   = 52;
        $pb   = 26;
        $pt   = 10;
        $ch   = $h - $pb - $pt;
        $span = $max - $min ?: 1;
        $y    = static fn ($v) => $pt + ($max - $v) / $span * $ch;
        $n    = count($rows);
        $slot = ($w - $pl - 6) / max(1, $n);
        $bw   = max(3, $slot * 0.62);
        $lakh = static fn ($v) => ($v < 0 ? '−' : '+') . '₹' . rtrim(rtrim(number_format(abs($v) / 100000, 1), '0'), '.') . 'L';
        $step = 10 ** floor(log10($span / 5));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($span / ($step * $m) <= 7) {
                $step *= $m;
                break;
            }
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" font-family="DejaVu Sans, sans-serif">';
        for ($t = ceil($min / $step) * $step; $t <= $max + 1e-6; $t += $step) {
            $yy = round($y($t), 1);
            $svg .= '<line x1="' . $pl . '" x2="' . $w . '" y1="' . $yy . '" y2="' . $yy . '" stroke="#e5e7eb" stroke-width="1"/>'
                . '<text x="' . ($pl - 6) . '" y="' . ($yy + 3) . '" font-size="9" fill="#6b7280" text-anchor="end">' . ($t == 0 ? '₹0' : $lakh($t)) . '</text>';
        }
        $breakeven = null;
        foreach ($rows as $i => $r) {
            if ($r['net'] >= 0 && $breakeven === null) {
                $breakeven = $i;
            }
        }
        $every = $n > 26 ? 2 : 1;
        foreach ($rows as $i => $r) {
            $x     = $pl + $i * $slot + ($slot - $bw) / 2;
            $top   = min($y($r['net']), $y(0));
            $hgt   = max(1, abs($y($r['net']) - $y(0)));
            $color = $r['net'] < 0 ? '#ef4444' : ($i === $breakeven ? '#d4a72c' : '#4ade80');
            $svg  .= '<rect x="' . round($x, 1) . '" y="' . round($top, 1) . '" width="' . round($bw, 1) . '" height="' . round($hgt, 1) . '" fill="' . $color . '" rx="1.5"/>';
            if ($i % $every === 0) {
                $svg .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($h - 8) . '" font-size="8" fill="#6b7280" text-anchor="middle">Yr ' . $r['year'] . '</text>';
            }
        }
        $z = round($y(0), 1);

        return $svg . '<line x1="' . $pl . '" x2="' . $w . '" y1="' . $z . '" y2="' . $z . '" stroke="#d4a72c" stroke-width="1.5"/></svg>';
    }

    /** Component keys that are services (installation-side GST rate and SAC code). */
    public const SERVICE_KEYS = ['installation', 'transport'];

    /** Components an admin can link to an inventory item (panels, inverters and extras are linked on their own rows). */
    public const ITEM_KEYS = [
        'structure' => 'Mounting structure', 'structure_high' => 'Elevated / high-rise structure', 'dcdb' => 'DCDB', 'acdb' => 'ACDB',
        'dc_cable' => 'DC cable', 'ac_cable' => 'AC copper cable', 'conduit' => 'Flexible conduits', 'earth_pit' => 'Earth pit / earthing kit',
        'earth_cable' => 'Earth cable', 'la' => 'Lightning arrestor', 'misc' => 'Miscellaneous (MC4 etc.)', 'battery' => 'Battery (hybrid)',
        'micro' => 'Micro inverter', 'transport' => 'Transport (service)', 'installation' => 'Installation (service)',
    ];

    /**
     * Quotation lines (tax-exclusive rate per line) that add up to $total incl. GST.
     *
     * items mode: one line per component; linked inventory items keep their item,
     * HSN and GST rate, so the invoice and dispatch use real stock. The price is
     * shared out in proportion to each component's cost; installation takes the
     * rounding so the total is exact.
     * split mode: two lines, supply (goods rate) and installation (service rate),
     * in the admin's goods : services ratio.
     */
    public static function lines(array $bom, float $total, array $cfg, string $sysLabel, int $panels, int $watt): array
    {
        $g    = $cfg['gst'];
        $sum  = array_sum(array_column($bom, 'cost'));
        if ($cfg['line_gst'] === 'split' || $sum <= 0 || $total <= 0) {
            $s       = $g['service_share'] / 100;
            $blended = (1 - $s) * $g['goods'] + $s * $g['service'];
            $goods   = round($total / (1 + $blended / 100) * (1 - $s), 2);
            $serv    = round(($total - $goods * (1 + $g['goods'] / 100)) / (1 + $g['service'] / 100), 2);

            return [
                ['item_id' => null, 'description' => 'Supply of ' . $sysLabel . ' Solar Power Plant (' . $panels . ' × ' . $watt . 'W modules, inverter and balance of system)',
                    'hsn' => $g['hsn'], 'qty' => 1, 'rate' => $goods, 'tax_percent' => $g['goods']],
                ['item_id' => null, 'description' => 'Installation, testing & commissioning of ' . $sysLabel . ' Solar Power Plant',
                    'hsn' => $g['sac'], 'qty' => 1, 'rate' => $serv, 'tax_percent' => $g['service']],
            ];
        }
        // The line that absorbs rounding: installation, else the last line with a cost.
        $absorb = null;
        $rank   = static fn ($b) => $b['key'] === 'installation' ? 3 : (in_array($b['key'], self::SERVICE_KEYS, true) ? 2 : (empty($b['item_id']) ? 1 : 0));
        foreach ($bom as $i => $b) {
            if ($b['cost'] > 0 && ($absorb === null || $rank($b) >= $rank($bom[$absorb]))) {
                $absorb = $i;
            }
        }
        $out  = [];
        $done = 0.0;
        foreach ($bom as $i => $b) {
            if ($b['cost'] <= 0 && empty($b['item_id'])) {
                continue; // nothing to charge and nothing to take from stock
            }
            $service = in_array($b['key'], self::SERVICE_KEYS, true);
            $tax     = isset($b['tax']) && $b['tax'] !== null ? (float) $b['tax'] : (float) ($service ? $g['service'] : $g['goods']);
            $line    = [
                'item_id'     => ! empty($b['item_id']) ? (int) $b['item_id'] : null,
                'description' => ! empty($b['item_name']) ? $b['item_name'] : trim($b['item'] . ($b['desc'] !== '' ? ' – ' . $b['desc'] : '') . ($b['make'] !== '' && $b['make'] !== 'Multiple' ? ' (' . $b['make'] . ')' : '')),
                'hsn'         => ($b['hsn'] ?? '') ?: ($service ? $g['sac'] : $g['hsn']),
                'qty'         => $b['qty'],
                'tax_percent' => $tax,
                '_i'          => $i,
            ];
            if ($service && empty($b['item_name'])) {
                $line['description'] = ($b['key'] === 'installation' ? 'Installation, testing & commissioning' : 'Transport') . ' – ' . $sysLabel;
            }
            if ($service || ($i === $absorb && empty($b['item_id']))) {
                $line['qty'] = 1; // a lump sum, so it can carry the rounding exactly
            }
            $out[] = $line;
        }
        foreach ($out as &$l) {
            if ($l['_i'] === $absorb) {
                continue;
            }
            $incl      = $total * $bom[$l['_i']]['cost'] / $sum;
            $l['rate'] = round($incl / (1 + $l['tax_percent'] / 100) / $l['qty'], 2);
            $amount    = round($l['qty'] * $l['rate'], 2);
            $done     += $amount + round($amount * $l['tax_percent'] / 100, 2);
        }
        unset($l);
        foreach ($out as &$l) {
            if ($l['_i'] === $absorb) {
                $l['rate'] = max(0, round(($total - $done) / (1 + $l['tax_percent'] / 100) / $l['qty'], 2));
            }
            unset($l['_i']);
        }
        unset($l);

        return $out;
    }

    /**
     * Fills item_id / item_name / unit / tax / hsn / stock on each component:
     * the admin's link first, else a match on the item name (brand, wattage, kW,
     * keywords). Items: [id, name, unit, hsn, tax_percent, stock].
     */
    public static function attachItems(array $bom, array $items, array $ctx): array
    {
        $byId = array_column($items, null, 'id');
        foreach ($bom as &$b) {
            $it = ! empty($b['item_id']) ? ($byId[$b['item_id']] ?? null) : null;
            $b['item_auto'] = false;
            if (! $it && ($it = self::matchItem($b['key'], $ctx, $items))) {
                $b['item_auto'] = true;
            }
            $b['item_id']   = $it ? (int) $it['id'] : 0;
            $b['item_name'] = $it['name'] ?? null;
            $b['unit']      = $it['unit'] ?? null;
            $b['tax']       = $it ? (float) $it['tax_percent'] : null;
            $b['hsn']       = $it['hsn'] ?? null;
            $b['stock']     = $it && isset($it['stock']) ? (float) $it['stock'] : null;
        }
        unset($b);

        return $bom;
    }

    /** Best inventory item for a component, guessed from item names; null when nothing fits. */
    public static function matchItem(string $key, array $ctx, array $items): ?array
    {
        $q     = static fn (string $w) => preg_quote($w, '/');
        $brand = trim((string) ($ctx['brand'] ?? ''));
        $watt  = (int) ($ctx['watt'] ?? 0);
        $kw    = isset($ctx['inv_kw']) && $ctx['inv_kw'] !== null ? (float) $ctx['inv_kw'] : null;
        $kwRe  = $kw !== null ? '(?<![\d.])' . $q(self::fmt($kw)) . '(\.0+)?\s*k\s*(w|va)?\b' : null;
        $wRe   = $watt ? '(?<!\d)' . $watt . '\s*(w|wp)?\b' : null;
        $tech  = ['bifacial' => 'bifacial|bi-facial', 'topcon' => 'topcon|top-con|n-type', 'hjt' => 'hjt|heterojunction'][$ctx['tech'] ?? ''] ?? null;
        $bRe   = $brand !== '' ? $q($brand) : null;
        // Each rule: list of regexes that must all match; first rule with a hit wins. 'not' excludes.
        [$rules, $not] = match ($key) {
            'panels' => [array_filter([
                $bRe && $wRe && $tech ? [$bRe, $wRe, $tech] : null, $bRe && $wRe ? [$bRe, $wRe] : null,
                $wRe ? [$wRe, 'panel|module'] : null, $bRe ? [$bRe, 'panel|module'] : null,
            ]), 'inverter|cable|structure'],
            'inverter' => match ($ctx['kind'] ?? 'ongrid') {
                'micro'  => [[array_filter([$bRe, 'micro']), ['micro.*inverter|inverter.*micro']], null],
                'hybrid' => [array_filter([$bRe && $kwRe ? [$bRe, $kwRe, 'hybrid'] : null, $kwRe ? [$kwRe, 'hybrid'] : null]), 'micro'],
                default  => [array_filter([$bRe && $kwRe ? [$bRe, $kwRe, 'inverter|grid|tie'] : null, $bRe && $kwRe ? [$bRe, $kwRe] : null, $kwRe ? [$kwRe, 'inverter'] : null]), 'hybrid|micro|battery'],
            },
            'battery'        => [[['battery']], 'inverter'],
            'structure'      => [[['structure|mounting'], ['\bmms\b']], 'elevat|high.?rise'],
            'structure_high' => [[['elevat|high.?rise', 'structure|mounting']], null],
            'dcdb'           => [[['\bdcdb\b|dc\s*distribution|dc\s*junction']], null],
            'acdb'           => [[['\bacdb\b|ac\s*distribution']], null],
            'dc_cable'       => [[['\bdc\b|solar', 'cable|wire']], 'earth|\bac\b|dcdb'],
            'ac_cable'       => [[['\bac\b|copper|armou?red', 'cable|wire']], 'earth|\bdc\b|solar|acdb'],
            'conduit'        => [[['conduit'], ['\bpipe\b|flexible']], null],
            'earth_pit'      => [[['earth|earthing|grounding', 'pit|electrode|rod|chemical|kit|chamber']], 'cable|wire|strip'],
            'earth_cable'    => [[['earth|earthing', 'cable|wire|strip']], null],
            'la'             => [[['lightning|arrest']], null],
            'misc'           => [[['\bmc4\b|misc|accessor|connector']], null],
            'transport'      => [[['transport|freight']], null],
            'installation'   => [[['install|commission']], null],
            default          => [[], null],
        };
        foreach ($rules as $rule) {
            foreach ($items as $it) {
                $name = (string) $it['name'];
                if ($not && preg_match('/' . $not . '/i', $name)) {
                    continue;
                }
                foreach ($rule as $re) {
                    if (! preg_match('/' . $re . '/i', $name)) {
                        continue 2;
                    }
                }

                return $it;
            }
        }

        return null;
    }

    public static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
