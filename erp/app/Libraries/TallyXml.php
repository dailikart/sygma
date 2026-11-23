<?php

namespace App\Libraries;

/**
 * Builds Tally Prime / ERP 9 voucher import XML.
 *
 * Same structure as the school ERP's Tally report (dailikart/demo,
 * Tally_report.php): ENVELOPE → IMPORTDATA → TALLYMESSAGE → VOUCHER with
 * ALLLEDGERENTRIES.LIST. Debit amounts are negative with ISDEEMEDPOSITIVE = Yes.
 *
 * In Tally: Gateway → Import → Transactions → choose the XML file.
 * All ledgers used must already exist in the Tally company (create the
 * party, bank, GST, sales/purchase and expense ledgers first).
 */
class TallyXml
{
    private string $body = '';

    public static function esc($s): string
    {
        return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function entry(string $ledger, float $amount, bool $isDebit): string
    {
        $amt = $isDebit ? -abs($amount) : abs($amount);

        return '<ALLLEDGERENTRIES.LIST>'
            . '<LEDGERNAME>' . self::esc($ledger) . '</LEDGERNAME>'
            . '<ISDEEMEDPOSITIVE>' . ($isDebit ? 'Yes' : 'No') . '</ISDEEMEDPOSITIVE>'
            . '<AMOUNT>' . number_format($amt, 2, '.', '') . '</AMOUNT>'
            . '</ALLLEDGERENTRIES.LIST>';
    }

    /**
     * @param list<array{0:string,1:float,2:bool}> $entries [ledger, amount, isDebit]
     * @param string $reference supplier invoice no. (Tally "Supplier Invoice No."), with $referenceDate
     */
    public function voucher(string $type, string $date, string $number, string $narration, array $entries, string $party = '', string $reference = '', ?string $referenceDate = null): self
    {
        $d   = date('Ymd', strtotime($date));
        $xml = '';
        foreach ($entries as [$ledger, $amount, $isDebit]) {
            if (round((float) $amount, 2) == 0.0) {
                continue;
            }
            $xml .= self::entry($ledger, (float) $amount, $isDebit);
        }

        $this->body .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">'
            . '<VOUCHER VCHTYPE="' . self::esc($type) . '" ACTION="Create" OBJVIEW="Accounting Voucher View">'
            . "<DATE>{$d}</DATE><EFFECTIVEDATE>{$d}</EFFECTIVEDATE>"
            . '<VOUCHERTYPENAME>' . self::esc($type) . '</VOUCHERTYPENAME>'
            . '<VOUCHERNUMBER>' . self::esc($number) . '</VOUCHERNUMBER>'
            . ($reference !== '' ? '<REFERENCE>' . self::esc($reference) . '</REFERENCE>' : '')
            . ($reference !== '' && $referenceDate ? '<REFERENCEDATE>' . date('Ymd', strtotime($referenceDate)) . '</REFERENCEDATE>' : '')
            . ($party !== '' ? '<PARTYLEDGERNAME>' . self::esc($party) . '</PARTYLEDGERNAME>' : '')
            . '<NARRATION>' . self::esc($narration) . '</NARRATION>'
            . $xml
            . '</VOUCHER></TALLYMESSAGE>';

        return $this;
    }

    public function render(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<ENVELOPE><HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>'
            . '<BODY><IMPORTDATA><REQUESTDESC><REPORTNAME>Vouchers</REPORTNAME></REQUESTDESC>'
            . '<REQUESTDATA>' . $this->body . '</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>';
    }

    /**
     * GST ledger name from the settings pattern, e.g. "Output CGST @ 9%".
     *
     * @param string $kind output|input
     * @param string $tax  cgst|sgst|igst
     */
    public static function gstLedger(string $kind, string $tax, float $fullRate): string
    {
        $rate = $tax === 'igst' ? $fullRate : $fullRate / 2;
        $fmt  = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
        $key  = 'Solar.tally' . ucfirst($kind) . ucfirst($tax);

        return str_replace('{rate}', $fmt, (string) setting($key));
    }

    /**
     * Splits line taxes into GST ledgers.
     *
     * @param list<array{tax_percent:float|string,tax_amount:float|string}> $lines
     *
     * @return array<string,float> ledger => amount
     */
    public static function gstSplit(array $lines, string $gstType, string $kind): array
    {
        $out = [];
        foreach ($lines as $l) {
            $tax  = (float) $l['tax_amount'];
            $rate = (float) $l['tax_percent'];
            if ($tax <= 0) {
                continue;
            }
            if ($gstType === 'inter') {
                $name       = self::gstLedger($kind, 'igst', $rate);
                $out[$name] = ($out[$name] ?? 0) + $tax;
            } else {
                $half  = round($tax / 2, 2);
                $c     = self::gstLedger($kind, 'cgst', $rate);
                $s     = self::gstLedger($kind, 'sgst', $rate);
                $out[$c] = ($out[$c] ?? 0) + $half;
                $out[$s] = ($out[$s] ?? 0) + round($tax - $half, 2);
            }
        }

        return array_map(static fn ($v) => round($v, 2), $out);
    }
}
