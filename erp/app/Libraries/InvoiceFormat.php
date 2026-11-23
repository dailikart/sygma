<?php

namespace App\Libraries;

/**
 * Invoice print layouts.
 *   itemwise   – every item with qty, rate, GST (usually B2B)
 *   cumulative – one line for the whole solar system + GST summary (usually B2C)
 */
class InvoiceFormat
{
    public const FORMATS = ['cumulative' => 'Cumulative (one line for the system) – B2C', 'itemwise' => 'Item-wise (every item) – B2B'];

    /** Lines to show for the chosen format. */
    public static function lines(array $inv, array $lines, string $type = ''): array
    {
        $plain = static fn ($l) => $l + ['tax_label' => 'GST ' . qty($l['tax_percent']) . '%'];
        if (($inv['print_format'] ?? 'itemwise') !== 'cumulative') {
            return array_map($plain, $lines);
        }
        // Lines typed by hand (no stock item) stay as they are, e.g. "Installation service".
        // Stock-item lines are merged into one system line.
        $stock  = array_values(array_filter($lines, static fn ($l) => ! empty($l['item_id'])));
        $manual = array_values(array_filter($lines, static fn ($l) => empty($l['item_id'])));
        if (count($stock) <= 1) {
            return array_map($plain, $lines);
        }
        $hsn = array_count_values(array_filter(array_column($stock, 'hsn')));
        arsort($hsn);
        $taxes = array_unique(array_map(static fn ($l) => qty($l['tax_percent']), $stock));
        $amt   = round(array_sum(array_column($stock, 'amount')), 2);

        return array_merge([[
            'description' => trim(qty($inv['capacity_kw']) . ' kW ' . $type) . ' Solar Power System – supply',
            'hsn'         => (string) (array_key_first($hsn) ?? ''),
            'qty'         => 1,
            'rate'        => $amt,
            'tax_label'   => count($taxes) === 1 ? 'GST ' . reset($taxes) . '%' : 'As per GST summary',
            'amount'      => $amt,
        ]], array_map($plain, $manual));
    }

    /** GST summary by rate: [rate => [taxable, cgst, sgst, igst]]. */
    public static function gstSummary(array $lines, string $gstType): array
    {
        $out = [];
        foreach ($lines as $l) {
            $r = qty($l['tax_percent']);
            $out[$r] ??= ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
            $out[$r]['taxable'] += (float) $l['amount'];
            if ($gstType === 'inter') {
                $out[$r]['igst'] += (float) $l['tax_amount'];
            } else {
                $half = round((float) $l['tax_amount'] / 2, 2);
                $out[$r]['cgst'] += $half;
                $out[$r]['sgst'] += round((float) $l['tax_amount'] - $half, 2);
            }
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }
}
