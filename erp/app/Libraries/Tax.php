<?php

namespace App\Libraries;

/**
 * GST line calculations. Rates are tax-exclusive: amount = qty × rate,
 * tax = amount × tax% / 100.
 */
class Tax
{
    /** intra = CGST + SGST (same state as the company), inter = IGST. */
    public static function gstType(?string $partyState): string
    {
        $company = strtolower(trim((string) setting('Solar.companyState')));
        $party   = strtolower(trim((string) $partyState));

        return ($party === '' || $party === $company) ? 'intra' : 'inter';
    }

    /**
     * Normalises posted line rows and returns [lines, subtotal, taxTotal, total].
     * Each row needs qty, rate, tax_percent; other keys pass through.
     */
    public static function lines(array $rows): array
    {
        $lines = [];
        $sub   = 0.0;
        $tax   = 0.0;

        foreach ($rows as $r) {
            $qty  = round((float) ($r['qty'] ?? 0), 2);
            $rate = round((float) ($r['rate'] ?? 0), 2);
            if ($qty <= 0) {
                continue;
            }
            $pct    = round((float) ($r['tax_percent'] ?? 0), 2);
            $amount = round($qty * $rate, 2);
            $t      = round($amount * $pct / 100, 2);

            $lines[] = array_merge($r, [
                'qty' => $qty, 'rate' => $rate, 'tax_percent' => $pct,
                'tax_amount' => $t, 'amount' => $amount,
            ]);
            $sub += $amount;
            $tax += $t;
        }

        return [$lines, round($sub, 2), round($tax, 2), round($sub + $tax, 2)];
    }
}
