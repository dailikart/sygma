<?php

namespace App\Libraries;

/**
 * Document numbers like INV/2026-27/0001 (Indian April–March financial year).
 */
class Numbering
{
    public static function financialYear(?string $date = null): string
    {
        $ts    = strtotime($date ?? date('Y-m-d'));
        $y     = (int) date('Y', $ts);
        $start = ((int) date('n', $ts) >= 4) ? $y : $y - 1;

        return $start . '-' . substr((string) ($start + 1), 2);
    }

    /**
     * Next number for $table.$column with the given prefix setting.
     */
    public static function next(string $table, string $column, string $prefix, ?string $date = null): string
    {
        $base = $prefix . '/' . self::financialYear($date) . '/';
        $row  = db_connect()->table($table)
            ->select($column)
            ->like($column, $base, 'after')
            ->notLike($column, '-R', 'both') // quotation versions (…/0001-R1) never set the next number
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        $n = $row ? ((int) substr($row[$column], strlen($base))) + 1 : 1;

        return $base . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
