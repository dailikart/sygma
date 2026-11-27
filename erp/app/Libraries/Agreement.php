<?php

namespace App\Libraries;

/**
 * Consumer–vendor agreement for a quotation (PM Surya Ghar rooftop solar).
 *
 * The text is kept in the Quick Quote settings and can be edited. It uses a
 * small markup, one item per line:
 *   # Title                 ## Centred line            ### Underlined heading
 *   - Numbered clause       > Small note               **bold** anywhere
 *   {payment_schedule} / {signatures} on their own line insert those sections.
 * {placeholders} are filled from the quotation and the agreement form.
 */
class Agreement
{
    /** placeholder => label (shown as chips in the settings). */
    public const PLACEHOLDERS = [
        'agreement_date' => 'Agreement date', 'customer_name' => 'Customer name', 'father_name' => "Father's / husband's name",
        'aadhaar' => 'Aadhaar number', 'customer_address' => 'Customer address', 'pincode' => 'PIN code', 'eb_number' => 'EB consumer number',
        'system_kw' => 'System size (kW)', 'system_type' => 'System type', 'panel_count' => 'Number of panels', 'panel_make' => 'Panel make',
        'panel_watt' => 'Panel wattage', 'panel_type' => 'Panel type (DCR / technology)', 'inverter_make' => 'Inverter make', 'inverter_desc' => 'Inverter size',
        'total_cost' => 'Total cost (₹)', 'total_words' => 'Total cost in words', 'subsidy' => 'Expected subsidy (₹)',
        'panel_warranty' => 'Panel performance warranty (years)', 'amc_years' => 'Free AMC (years)',
        'quote_no' => 'Quotation no.', 'quote_date' => 'Quotation date', 'company_name' => 'Your company name', 'company_address' => 'Your company address',
        'payment_schedule' => 'Payment schedule (whole section)', 'signatures' => 'Signatures (whole section)',
    ];

    public static function defaultText(): string
    {
        return <<<'TXT'
# AGREEMENT
## Between the Consumer and the Vendor for installation of a Rooftop Solar System
## under PM Surya Ghar: Muft Bijli Yojana
This agreement is made and executed on **{agreement_date}** for the design, supply, installation, commissioning and five-year comprehensive maintenance of a rooftop solar (RTS) system, along with warranty, at the residence of the Consumer.
### BETWEEN
**{customer_name}**, son / daughter / wife of {father_name}, Aadhaar No. {aadhaar}, residing at {customer_address} – {pincode}, holding electricity consumer number **{eb_number}** (hereinafter called the "Consumer", which expression shall include legal heirs, successors and assigns) of the FIRST PART;
### AND
**{company_name}**, having its office at {company_address} (hereinafter called the "Vendor", which expression shall include its successors and assigns) of the SECOND PART.
Whereas the Consumer has applied for a rooftop solar system and has chosen the Vendor to install it, the Consumer and the Vendor agree as follows:
### TERMS AND CONDITIONS
- The Vendor shall design, supply, install, test and commission a **{system_kw} kW {system_type}** rooftop solar system at the above premises, consisting of {panel_count} nos. {panel_make} {panel_watt}W {panel_type} solar modules, {inverter_make} {inverter_desc} inverter, module mounting structure, DC and AC cables, distribution boxes, earthing, lightning arrestor and other balance of system, as per quotation {quote_no} dated {quote_date}.
- The total cost of the system agreed between the Consumer and the Vendor is **₹{total_cost}** ({total_words}), inclusive of GST. Fees and meter charges payable to the DISCOM for net metering are paid by the Consumer, unless agreed otherwise in writing.
- Where Central Financial Assistance (subsidy) is claimed, the solar modules and cells shall be domestically manufactured (DCR) and every component shall meet the technical specifications and standards notified by MNRE. The subsidy (expected ₹{subsidy}) is credited by the Government directly to the Consumer's bank account; the Vendor has no claim on it.
- The Consumer shall provide a shadow-free roof area, safe access to the roof, and water and electricity during installation, and shall keep the system and its surroundings free of later obstructions that cast shade on the modules.
- The Vendor shall help the Consumer with the application on the National Portal, the net metering application, inspection by the DISCOM and the documents needed for the release of the subsidy.
- The Vendor shall provide free comprehensive maintenance of the system for **{amc_years} years** from the date of commissioning. The solar modules carry the manufacturer's product warranty and a performance warranty of {panel_warranty} years; the inverter carries the manufacturer's warranty. The Vendor shall coordinate warranty claims with the manufacturers.
- The performance of the plant shall be tested at commissioning by the Vendor or its authorised agency. The Vendor must provide (on a returnable basis) a radiation sensor with a valid calibration certificate from any NABL / international laboratory at the time of commissioning / testing of the plant. The Vendor must maintain the performance ratio (PR) of the plant till the warranty of the project, i.e. 5 years from the date of commissioning.
- The Vendor shall follow all safety rules during installation and shall be responsible for the material until it is installed at the premises.
- Neither party shall be liable for delay caused by events beyond its reasonable control (force majeure), such as natural calamities, government orders or delay by the DISCOM in net metering.
- Any difference between the parties shall first be settled amicably. Failing that, it shall be subject to the courts having jurisdiction over the premises.
- **MUTUALLY AGREED TERMS OF PAYMENT**
{payment_schedule}
In witness whereof, the parties have signed this agreement on the date and at the place first written above.
{signatures}
> Disclaimer: This agreement is between the vendor and the consumer, and any dispute related to it shall not involve any third party, including MNRE and the Distribution Utilities.
TXT;
    }

    /** Agreement details saved on the quotation, with defaults filled from the quotation. */
    public static function data(array $q, ?array $spec, array $cfg): array
    {
        $saved = json_decode((string) ($q['agreement'] ?? ''), true);
        $saved = is_array($saved) ? $saved : [];
        $party = $spec['party'] ?? [];
        $addr  = trim(implode(', ', array_filter([(string) $q['party_address'], (string) $q['party_city']])), ', ');
        $total = (float) $q['total'];

        $a = $saved + [
            'date'        => date('Y-m-d'),
            'father_name' => '',
            'aadhaar'     => '',
            'address'     => $addr,
            'pincode'     => (string) ($party['pincode'] ?? ''),
            'eb_number'   => (string) ($party['eb_number'] ?? ''),
            'stamp'       => (bool) $cfg['agreement_stamp'],
            'stages'      => $cfg['stages'],
            'text'        => $cfg['agreement_text'],
            'saved'       => false,
        ];
        // Amounts always follow the quotation's current total.
        $a['stages'] = array_map(static fn ($s) => ['amount' => round($total * (float) $s['pct'] / 100)] + array_diff_key($s, ['amount' => 1]), $a['stages']);

        return $a;
    }

    /** Values for every placeholder. */
    public static function vars(array $q, ?array $spec, array $a, array $cfg): array
    {
        $bom   = [];
        foreach ($spec['bom'] ?? [] as $b) {
            if (isset($b['key'])) {
                $bom[$b['key']] = $b;
            }
        }
        $size  = $spec['size'] ?? [];
        $tech  = QuickQuote::TECHS[$spec['input']['tech'] ?? ''][0] ?? '';
        $money = static fn ($n) => self::inr($n);

        return [
            'agreement_date'   => $a['date'] ? date('d-m-Y', strtotime($a['date'])) : '',
            'customer_name'    => (string) $q['party_name'],
            'father_name'      => (string) $a['father_name'],
            'aadhaar'          => trim(chunk_split(preg_replace('/\D/', '', (string) $a['aadhaar']), 4, ' ')),
            'customer_address' => (string) $a['address'],
            'pincode'          => (string) $a['pincode'],
            'eb_number'        => (string) $a['eb_number'],
            'system_kw'        => QuickQuote::fmt((float) $q['capacity_kw']),
            'system_type'      => (string) ($spec['system_name'] ?? 'Solar Power Plant'),
            'panel_count'      => (string) ($size['panels'] ?? ''),
            'panel_make'       => (string) ($bom['panels']['make'] ?? ''),
            'panel_watt'       => (string) ($size['watt'] ?? ''),
            'panel_type'       => trim($tech . ' ' . (isset($size['dcr']) ? ($size['dcr'] ? 'DCR' : 'non-DCR') : '')),
            'inverter_make'    => (string) ($bom['inverter']['make'] ?? ''),
            'inverter_desc'    => (string) ($bom['inverter']['desc'] ?? ''),
            'total_cost'       => $money($q['total']),
            'total_words'      => amount_in_words((float) $q['total']),
            'subsidy'          => $money($q['subsidy_amount']),
            'panel_warranty'   => (string) $cfg['warranty']['panel_perf'],
            'amc_years'        => (string) $cfg['warranty']['amc'],
            'quote_no'         => (string) $q['quote_no'],
            'quote_date'       => date('d-m-Y', strtotime((string) $q['quote_date'])),
            'company_name'     => $cfg['legal_name'] ?: (string) setting('Solar.companyName'),
            'company_address'  => str_replace("\n", ', ', $cfg['legal_address'] ?: (string) setting('Solar.companyAddress')),
        ];
    }

    /** The markup as HTML for mPDF. Empty values print as a blank to fill by hand. */
    public static function render(string $text, array $vars, array $stages, float $total, array $sign): string
    {
        $blank = '<span style="color:#9ca3af">________________</span>';
        $fill  = static function (string $line) use ($vars, $blank): string {
            $h = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            $h = preg_replace_callback('/\{([a-z_]+)\}/', static fn ($m) => array_key_exists($m[1], $vars)
                ? ($vars[$m[1]] !== '' ? htmlspecialchars((string) $vars[$m[1]], ENT_QUOTES, 'UTF-8') : $blank) : $m[0], $h);

            return preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $h);
        };
        $out = '';
        $n   = 0;
        foreach (preg_split('/\R/', $text) as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }
            if ($t === '{payment_schedule}') {
                $out .= self::schedule($stages, $total);
            } elseif ($t === '{signatures}') {
                $out .= self::signatures($sign);
            } elseif (str_starts_with($t, '### ')) {
                $out .= '<p style="font-weight:bold;text-decoration:underline;margin:4mm 0 2mm 0">' . $fill(substr($t, 4)) . '</p>';
            } elseif (str_starts_with($t, '## ')) {
                $out .= '<p style="text-align:center;font-weight:bold;margin:0 0 1mm 0">' . $fill(substr($t, 3)) . '</p>';
            } elseif (str_starts_with($t, '# ')) {
                $out .= '<p style="text-align:center;font-size:15pt;font-weight:bold;letter-spacing:2pt;margin:0 0 2mm 0">' . $fill(substr($t, 2)) . '</p>';
            } elseif (str_starts_with($t, '- ')) {
                $out .= '<table style="width:100%;margin:0 0 1.5mm 0"><tr><td style="width:8mm;vertical-align:top">' . (++$n) . '.</td><td style="vertical-align:top;text-align:left">' . $fill(substr($t, 2)) . '</td></tr></table>';
            } elseif (str_starts_with($t, '> ')) {
                $out .= '<p style="font-size:8pt;color:#4b5563;margin-top:4mm">' . $fill(substr($t, 2)) . '</p>';
            } else {
                $out .= '<p style="text-align:left;margin:0 0 2mm 0">' . $fill($t) . '</p>';
            }
        }

        return $out;
    }

    private static function schedule(array $stages, float $total): string
    {
        $rs = static fn ($n) => '₹' . self::inr($n);
        $h  = '<table style="width:100%;border-collapse:collapse;margin:1mm 0 3mm 0;font-size:9pt">'
            . '<tr><th style="border:0.2mm solid #999;padding:1.5mm;text-align:left">Stage</th><th style="border:0.2mm solid #999;padding:1.5mm">%</th>'
            . '<th style="border:0.2mm solid #999;padding:1.5mm;text-align:right">Amount</th><th style="border:0.2mm solid #999;padding:1.5mm;text-align:left">When</th></tr>';
        $sum = 0.0;
        foreach ($stages as $s) {
            $amt  = isset($s['amount']) ? (float) $s['amount'] : round($total * $s['pct'] / 100);
            $sum += $amt;
            $h   .= '<tr><td style="border:0.2mm solid #999;padding:1.5mm"><b>' . esc($s['name']) . '</b></td><td style="border:0.2mm solid #999;padding:1.5mm;text-align:center">' . QuickQuote::fmt((float) $s['pct']) . '%</td>'
                . '<td style="border:0.2mm solid #999;padding:1.5mm;text-align:right">' . $rs($amt) . '</td><td style="border:0.2mm solid #999;padding:1.5mm">' . esc((string) $s['text']) . '</td></tr>';
        }

        return $h . '<tr><td style="border:0.2mm solid #999;padding:1.5mm"><b>Total</b></td><td style="border:0.2mm solid #999;padding:1.5mm;text-align:center">'
            . QuickQuote::fmt(array_sum(array_column($stages, 'pct'))) . '%</td><td style="border:0.2mm solid #999;padding:1.5mm;text-align:right"><b>' . $rs($sum) . '</b></td><td style="border:0.2mm solid #999"></td></tr></table>';
    }

    /** Whole rupees in Indian grouping: 2,35,866. */
    public static function inr($n): string
    {
        $int = (string) abs((int) round((float) $n));
        if (strlen($int) > 3) {
            $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3)) . ',' . substr($int, -3);
        }

        return ((float) $n < 0 ? '-' : '') . $int;
    }

    private static function signatures(array $s): string
    {
        $line = static fn (string $inside = '') => '<td style="width:46%;height:13mm;border-bottom:0.2mm solid #333;vertical-align:bottom">' . $inside . '</td>';

        return '<table style="width:100%;margin-top:4mm;font-size:9pt;border-collapse:collapse">'
            . '<tr>' . $line() . '<td style="width:8%"></td>' . $line((string) ($s['signature'] ?? '')) . '</tr>'
            . '<tr><td style="vertical-align:top;padding-top:1mm"><b>Consumer</b><br>' . esc((string) ($s['customer'] ?? '')) . '<br>Date:</td><td></td>'
            . '<td style="vertical-align:top;padding-top:1mm"><b>Vendor</b><br>For ' . esc((string) ($s['company'] ?? '')) . ', Authorised Signatory<br>Date:</td></tr>'
            . '<tr>' . $line() . '<td></td>' . $line() . '</tr>'
            . '<tr><td style="padding-top:1mm">Witness 1: name &amp; address</td><td></td><td style="padding-top:1mm">Witness 2: name &amp; address</td></tr></table>';
    }
}
