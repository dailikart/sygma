<?php

namespace App\Libraries;

/**
 * Specific yield (kWh per kWp per year, and per month) for a location, tilt and
 * direction. Uses PVGIS (EU Joint Research Centre, free, worldwide) and falls
 * back to the quotation setting "units per kW per day" when PVGIS cannot be
 * reached. Results are cached for 30 days.
 */
class SolarYield
{
    private const PVGIS = 'https://re.jrc.ec.europa.eu/api/v5_3/PVcalc';

    /** Days per month, for the fallback estimate. */
    private const DAYS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    /**
     * @param float $azimuth Direction the panels face, degrees from north (180 = south).
     *
     * @return array{kwh_per_kwp: float, monthly: list<float>, source: string}
     */
    public static function get(?float $lat, ?float $lng, float $tilt, float $azimuth): array
    {
        $tilt = max(0.0, min(90.0, $tilt));
        $azimuth = fmod(fmod($azimuth, 360) + 360, 360);
        if ($lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180 && ($lat != 0.0 || $lng != 0.0)) {
            $key = 'pvgis_' . md5(sprintf('%.2f|%.2f|%d|%d', $lat, $lng, round($tilt), round($azimuth)));
            $hit = cache($key);
            if (is_array($hit)) {
                return $hit;
            }
            $res = self::pvgis($lat, $lng, $tilt, $azimuth);
            if ($res !== null) {
                cache()->save($key, $res, 30 * DAY);

                return $res;
            }
        }

        return self::estimate();
    }

    /** Fallback: units per kW per day from Settings → Quotation, spread by days in the month. */
    public static function estimate(): array
    {
        $perDay  = (float) (setting('Solar.quoteUnitsPerKw') ?: 4);
        $monthly = array_map(static fn ($d) => round($perDay * $d, 1), self::DAYS);

        return ['kwh_per_kwp' => round(array_sum($monthly), 1), 'monthly' => $monthly, 'source' => 'estimate'];
    }

    private static function pvgis(float $lat, float $lng, float $tilt, float $azimuth): ?array
    {
        // PVGIS "aspect": 0 = south, 90 = west, -90 = east.
        $aspect = $azimuth - 180;
        $url    = self::PVGIS . '?' . http_build_query([
            'lat' => round($lat, 5), 'lon' => round($lng, 5), 'peakpower' => 1, 'loss' => 14,
            'angle' => round($tilt, 1), 'aspect' => round($aspect, 1), 'outputformat' => 'json',
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'SygmaSolarSuite/1.0']);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) {
            log_message('warning', 'PVGIS unavailable (HTTP ' . $code . ') for ' . $lat . ',' . $lng);

            return null;
        }
        $d     = json_decode((string) $body, true);
        $year  = $d['outputs']['totals']['fixed']['E_y'] ?? null;
        $month = array_column($d['outputs']['monthly']['fixed'] ?? [], 'E_m');
        if (! is_numeric($year) || count($month) !== 12) {
            return null;
        }

        return ['kwh_per_kwp' => round((float) $year, 1), 'monthly' => array_map(static fn ($m) => round((float) $m, 1), $month), 'source' => 'pvgis'];
    }
}
