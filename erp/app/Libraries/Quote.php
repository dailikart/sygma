<?php

namespace App\Libraries;

/** Quotation maths: PM Surya Ghar subsidy and savings / payback. */
class Quote
{
    /**
     * PM Surya Ghar residential central subsidy: ₹30,000 per kW for the first
     * 2 kW, ₹18,000 for the 3rd kW, capped at ₹78,000 (3 kW and above).
     * Shown as a suggestion; the amount on the quotation can be changed.
     */
    public static function suggestedSubsidy(float $kw): float
    {
        if ($kw <= 0) {
            return 0.0;
        }

        return round(min($kw, 2) * 30000 + max(0, min($kw, 3) - 2) * 18000, 2);
    }

    /**
     * @return array{units_year: float, savings_year: float, extra: float, net_cost: float, payback_years: ?float, savings_25: float}
     */
    public static function roi(array $q): array
    {
        $kw      = (float) $q['capacity_kw'];
        $units   = round($kw * (float) $q['units_per_kw_day'] * 365);
        $savings = round($units * (float) $q['tariff'], 2);
        $extra   = ($q['eb_charges_by'] ?? null) === 'customer' ? (float) ($q['eb_charges_amount'] ?? 0) : 0.0;
        $net     = round((float) $q['total'] + $extra - (float) $q['subsidy_amount'], 2);

        return [
            'units_year'    => $units,
            'savings_year'  => $savings,
            'extra'         => $extra,
            'net_cost'      => $net,
            'payback_years' => $savings > 0 ? round($net / $savings, 1) : null,
            'savings_25'    => round($savings * 25, 2),
        ];
    }
}
