<?php

namespace App\Libraries;

/**
 * GST e-invoice (IRN) upload file for the government portal einvoice1.gst.gov.in
 * (e-Invoice → Bulk Upload). Builds the official JSON schema version 1.1 and
 * checks every field the portal checks, so the upload goes through first time.
 *
 * Only B2B invoices (buyer has a GSTIN) need an e-invoice.
 */
class EInvoice
{
    /** GST state codes. */
    public const STATES = [
        'jammu and kashmir' => '01', 'himachal pradesh' => '02', 'punjab' => '03', 'chandigarh' => '04', 'uttarakhand' => '05',
        'haryana' => '06', 'delhi' => '07', 'rajasthan' => '08', 'uttar pradesh' => '09', 'bihar' => '10', 'sikkim' => '11',
        'arunachal pradesh' => '12', 'nagaland' => '13', 'manipur' => '14', 'mizoram' => '15', 'tripura' => '16', 'meghalaya' => '17',
        'assam' => '18', 'west bengal' => '19', 'jharkhand' => '20', 'odisha' => '21', 'chhattisgarh' => '22', 'madhya pradesh' => '23',
        'gujarat' => '24', 'dadra and nagar haveli and daman and diu' => '26', 'maharashtra' => '27', 'karnataka' => '29', 'goa' => '30',
        'lakshadweep' => '31', 'kerala' => '32', 'tamil nadu' => '33', 'puducherry' => '34', 'andaman and nicobar islands' => '35',
        'telangana' => '36', 'andhra pradesh' => '37', 'ladakh' => '38',
        // common spellings
        'orissa' => '21', 'pondicherry' => '34', 'new delhi' => '07', 'nct of delhi' => '07', 'tamilnadu' => '33', 'j&k' => '01',
        'daman and diu' => '26', 'dadra and nagar haveli' => '26', 'uttaranchal' => '05', 'chattisgarh' => '22', 'andaman and nicobar' => '35',
    ];

    /** ERP unit → GST unit (UQC). */
    private const UNITS = [
        'nos' => 'NOS', 'no' => 'NOS', 'no.' => 'NOS', 'number' => 'NOS', 'pcs' => 'PCS', 'pc' => 'PCS', 'piece' => 'PCS', 'set' => 'SET', 'sets' => 'SET',
        'mtr' => 'MTR', 'm' => 'MTR', 'meter' => 'MTR', 'metre' => 'MTR', 'pair' => 'PRS', 'prs' => 'PRS', 'kg' => 'KGS', 'kgs' => 'KGS',
        'ltr' => 'LTR', 'l' => 'LTR', 'litre' => 'LTR', 'box' => 'BOX', 'roll' => 'ROL', 'rol' => 'ROL', 'bundle' => 'BDL', 'sqm' => 'SQM',
        'sqft' => 'SQF', 'sq.ft' => 'SQF', 'kw' => 'OTH', 'job' => 'OTH', 'lot' => 'OTH', 'unt' => 'UNT', 'unit' => 'UNT', 'units' => 'UNT',
    ];

    private const RATES = [0, 0.1, 0.25, 1, 1.5, 3, 5, 6, 7.5, 12, 18, 28, 40];

    public static function stateCode(?string $state): ?string
    {
        $s = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('&', 'and', (string) $state))));

        return self::STATES[$s] ?? self::STATES[str_replace('and', '&', $s)] ?? null;
    }

    /** True for invoices that need an e-invoice: buyer has a GSTIN and the invoice is not cancelled. */
    public static function applies(array $inv, array $customer): bool
    {
        return trim((string) $customer['gstin']) !== '' && $inv['status'] !== 'cancelled';
    }

    /** Seller block from Settings → Company, with any missing fields. @return array{0: array, 1: list<string>} */
    public static function seller(): array
    {
        $errors = [];
        $gstin  = strtoupper(trim((string) setting('Solar.companyGstin')));
        $addr   = self::address((string) setting('Solar.companyAddress'));
        $pin    = preg_replace('/\D/', '', (string) setting('Solar.companyPincode')) ?: self::pinFrom((string) setting('Solar.companyAddress'));
        $loc    = trim((string) setting('Solar.companyCity'));
        $stcd   = self::stateCode((string) setting('Solar.companyState'));
        if (! self::validGstin($gstin)) {
            $errors[] = 'Company GSTIN is missing or not 15 characters (Settings → Company).';
        }
        if ($addr[0] === '') {
            $errors[] = 'Company address is missing (Settings → Company).';
        }
        if (strlen((string) $pin) !== 6) {
            $errors[] = 'Company PIN code is missing (Settings → Company → PIN code).';
        }
        if (mb_strlen($loc) < 3) {
            $errors[] = 'Company city / place is missing (Settings → Company → City).';
        }
        if (! $stcd) {
            $errors[] = 'Company state is not a valid Indian state (Settings → Company → State).';
        } elseif (self::validGstin($gstin) && substr($gstin, 0, 2) !== $stcd) {
            $errors[] = "Company GSTIN starts with " . substr($gstin, 0, 2) . " but the company state's code is {$stcd}.";
        }
        $s = ['Gstin' => $gstin, 'LglNm' => self::cut((string) setting('Solar.companyName'), 100), 'Addr1' => $addr[0]];
        if ($addr[1] !== '') {
            $s['Addr2'] = $addr[1];
        }
        $s += ['Loc' => self::cut($loc, 50), 'Pin' => (int) $pin, 'Stcd' => (string) $stcd];
        if ($ph = self::phone((string) setting('Solar.companyPhone'))) {
            $s['Ph'] = $ph;
        }
        if (filter_var($em = trim((string) setting('Solar.companyEmail')), FILTER_VALIDATE_EMAIL)) {
            $s['Em'] = $em;
        }

        return [$s, $errors];
    }

    /**
     * One invoice in the portal's JSON format (schema 1.1), or the reasons it cannot be uploaded yet.
     *
     * @return array{0: array|null, 1: list<string>}
     */
    public static function build(int $invoiceId): array
    {
        $db  = db_connect();
        $inv = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        if (! $inv) {
            return [null, ['Invoice not found.']];
        }
        $c      = $db->table('customers')->where('id', $inv['customer_id'])->get()->getRowArray();
        $lines  = $db->table('invoice_items l')->select('l.*, i.unit, i.hsn AS item_hsn')->join('items i', 'i.id = l.item_id', 'left')
            ->where('l.invoice_id', $invoiceId)->orderBy('l.id')->get()->getResultArray();
        [$seller, $errors] = self::seller();

        if ($inv['status'] === 'cancelled') {
            $errors[] = 'The invoice is cancelled.';
        }
        $no = strtoupper(trim((string) $inv['invoice_no']));
        if (! preg_match('#^[A-Z1-9][A-Z0-9/-]{0,15}$#', $no)) {
            $errors[] = "Invoice no. {$no} is not allowed by the portal: max 16 characters, only letters, digits, / and -, and it cannot start with 0, / or -. Change the invoice prefix in Settings → Numbering.";
        }

        // Buyer
        $gstin = strtoupper(trim((string) $c['gstin']));
        if ($gstin === '') {
            $errors[] = 'The customer has no GSTIN: e-invoice is only for B2B. Add the GSTIN on the customer if they are registered.';
        } elseif (! self::validGstin($gstin)) {
            $errors[] = "Customer GSTIN {$gstin} is not a valid 15-character GSTIN.";
        }
        $buyerSt = self::validGstin($gstin) ? substr($gstin, 0, 2) : null;
        $pos     = self::stateCode((string) $c['state']) ?? $buyerSt;
        if (! $pos) {
            $errors[] = 'Customer state "' . $c['state'] . '" is not a valid Indian state.';
        }
        $addr = self::address((string) $c['address']);
        if ($addr[0] === '') {
            $errors[] = 'Customer address is missing.';
        }
        $pin = preg_replace('/\D/', '', (string) $c['pincode']) ?: self::pinFrom((string) $c['address']);
        if (strlen((string) $pin) !== 6 || $pin[0] === '0') {
            $errors[] = 'Customer PIN code is missing or not 6 digits.';
        }
        $loc = trim((string) $c['city']) ?: trim((string) $c['state']);
        if (mb_strlen($loc) < 3) {
            $errors[] = 'Customer city is missing.';
        }
        $legal = trim((string) ($c['company'] ?: $c['name']));
        if (mb_strlen($legal) < 3) {
            $errors[] = 'Customer name / company is shorter than 3 letters.';
        }
        $inter = $inv['gst_type'] === 'inter';
        if ($pos && ! empty($seller['Stcd']) && $inter === ($pos === $seller['Stcd'])) {
            $errors[] = $inter ? 'The invoice has IGST but the customer is in your own state: change the customer state or edit the invoice.'
                : 'The invoice has CGST + SGST but the customer is in another state: change the customer state or edit the invoice.';
        }
        $buyer = ['Gstin' => $gstin, 'LglNm' => self::cut($legal, 100), 'Pos' => (string) $pos, 'Addr1' => $addr[0]];
        if ($addr[1] !== '') {
            $buyer['Addr2'] = $addr[1];
        }
        $buyer += ['Loc' => self::cut($loc, 50), 'Pin' => (int) $pin, 'Stcd' => (string) ($buyerSt ?? $pos)];
        if ($ph = self::phone((string) $c['phone'])) {
            $buyer['Ph'] = $ph;
        }
        if (filter_var($em = trim((string) $c['email']), FILTER_VALIDATE_EMAIL)) {
            $buyer['Em'] = $em;
        }

        // Items
        $items = [];
        $tot   = ['AssVal' => 0.0, 'CgstVal' => 0.0, 'SgstVal' => 0.0, 'IgstVal' => 0.0];
        foreach ($lines as $n => $l) {
            $sl  = $n + 1;
            $hsn = preg_replace('/\D/', '', (string) ($l['hsn'] ?: $l['item_hsn']));
            if (! in_array(strlen($hsn), [4, 6, 8], true)) {
                $errors[] = "Line {$sl} (" . self::cut((string) $l['description'], 40) . '): HSN / SAC code missing or not 4, 6 or 8 digits.';
            }
            $rate = (float) $l['tax_percent'];
            if (! in_array($rate, array_map('floatval', self::RATES), true)) {
                $errors[] = "Line {$sl}: GST rate {$rate}% is not a GST rate.";
            }
            $ass  = round((float) $l['amount'], 2);
            $tax  = round((float) $l['tax_amount'], 2);
            $cgst = $inter ? 0.0 : round($tax / 2, 2);
            $sgst = $inter ? 0.0 : round($tax - $cgst, 2);
            $igst = $inter ? $tax : 0.0;
            $items[] = [
                'SlNo' => (string) $sl, 'PrdDesc' => self::cut((string) $l['description'], 300) ?: 'Item ' . $sl,
                'IsServc' => str_starts_with($hsn, '99') ? 'Y' : 'N', 'HsnCd' => $hsn,
                'Qty' => round((float) $l['qty'], 3), 'Unit' => self::unit($l['unit'] ?? null),
                'UnitPrice' => round((float) $l['rate'], 3), 'TotAmt' => $ass, 'Discount' => 0, 'AssAmt' => $ass,
                'GstRt' => $rate, 'IgstAmt' => $igst, 'CgstAmt' => $cgst, 'SgstAmt' => $sgst,
                'CesRt' => 0, 'CesAmt' => 0, 'CesNonAdvlAmt' => 0, 'StateCesRt' => 0, 'StateCesAmt' => 0, 'StateCesNonAdvlAmt' => 0, 'OthChrg' => 0,
                'TotItemVal' => round($ass + $tax, 2),
            ];
            $tot['AssVal'] += $ass;
            $tot['CgstVal'] += $cgst;
            $tot['SgstVal'] += $sgst;
            $tot['IgstVal'] += $igst;
        }
        if (! $items) {
            $errors[] = 'The invoice has no lines.';
        }
        $tot   = array_map(static fn ($v) => round($v, 2), $tot);
        $total = round((float) $inv['total'], 2);
        $round = round($total - ($tot['AssVal'] + $tot['CgstVal'] + $tot['SgstVal'] + $tot['IgstVal']), 2);
        if (abs($round) > 1) {
            $errors[] = 'Invoice total does not match its lines (difference ' . $round . '). Open and save the invoice again.';
        }

        $json = [
            'Version'    => '1.1',
            'TranDtls'   => ['TaxSch' => 'GST', 'SupTyp' => 'B2B', 'RegRev' => 'N', 'IgstOnIntra' => 'N'],
            'DocDtls'    => ['Typ' => 'INV', 'No' => $no, 'Dt' => date('d/m/Y', strtotime($inv['invoice_date']))],
            'SellerDtls' => $seller,
            'BuyerDtls'  => $buyer,
            'ItemList'   => $items,
            'ValDtls'    => $tot + ['CesVal' => 0, 'StCesVal' => 0, 'Discount' => 0, 'OthChrg' => 0, 'RndOffAmt' => abs($round) <= 1 ? $round : 0, 'TotInvVal' => $total],
        ];

        return [$errors ? null : $json, array_values(array_unique($errors))];
    }

    /** JSON text for the portal (an array of invoices). */
    public static function encode(array $invoices): string
    {
        return json_encode(array_values($invoices), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function validGstin(string $g): bool
    {
        return (bool) preg_match('/^[0-9]{2}[0-9A-Z]{13}$/', $g);
    }

    // ------------------------------------------------------------------

    private static function unit(?string $u): string
    {
        $k = strtolower(trim((string) $u));

        return self::UNITS[$k] ?? (preg_match('/^[A-Z]{3}$/', strtoupper($k)) ? strtoupper($k) : 'OTH');
    }

    /** [line 1, line 2] of up to 100 characters each. */
    private static function address(string $a): array
    {
        $a = trim(preg_replace('/\s*[\r\n]+\s*/', ', ', $a), " ,\t");
        $a = preg_replace('/\s+/', ' ', $a);
        if (mb_strlen($a) <= 100) {
            return [$a, ''];
        }
        $cut = mb_strrpos(mb_substr($a, 0, 100), ',') ?: mb_strrpos(mb_substr($a, 0, 100), ' ') ?: 100;

        return [trim(mb_substr($a, 0, $cut), ' ,'), self::cut(trim(mb_substr($a, $cut), ' ,'), 100)];
    }

    private static function pinFrom(string $text): string
    {
        return preg_match('/\b([1-9][0-9]{2}\s?[0-9]{3})\b/', $text, $m) ? str_replace(' ', '', $m[1]) : '';
    }

    private static function phone(string $p): ?string
    {
        $d = preg_replace('/\D/', '', $p);
        if (strlen($d) > 10 && str_starts_with($d, '91')) {
            $d = substr($d, -10);
        }

        return strlen($d) >= 6 && strlen($d) <= 12 ? $d : null;
    }

    private static function cut(string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $s)), 0, $max);
    }
}
