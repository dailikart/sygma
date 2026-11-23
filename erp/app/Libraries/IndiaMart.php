<?php

namespace App\Libraries;

use RuntimeException;

/**
 * IndiaMART enquiries → Leads.
 *
 * Pull API: GET <api>?glusr_crm_key=KEY&start_time=..&end_time=.. (max 7 days per call,
 *           IndiaMART allows one call every 5 minutes).
 * Push API: IndiaMART POSTs each new enquiry to site_url('indiamart/push/<token>').
 *
 * Each enquiry becomes a lead (source "IndiaMART", reference = UNIQUE_QUERY_ID, never
 * imported twice). When the phone number already belongs to an open lead, the enquiry is
 * added to that lead as a follow-up instead of creating a duplicate.
 */
class IndiaMart
{
    public const MIN_GAP_SECONDS = 300;

    public static function pushToken(): string
    {
        return LeadInbox::secret('indiamartPushToken');
    }

    public static function pushUrl(): string
    {
        return site_url('indiamart/push/' . self::pushToken());
    }

    /**
     * Fetches enquiries since the last pull (or the last 24 hours) and imports them.
     *
     * @return array{added:int, merged:int, skipped:int, message:string}
     */
    public static function pull(bool $force = false): array
    {
        $key = trim((string) setting('Solar.indiamartKey'));
        if (! setting('Solar.indiamartEnabled') || $key === '') {
            throw new RuntimeException('Switch on IndiaMART import and enter the CRM key in Settings first.');
        }
        $lastCall = (string) setting('Solar.indiamartLastCall');
        if (! $force && $lastCall !== '' && time() - strtotime($lastCall) < self::MIN_GAP_SECONDS) {
            throw new RuntimeException('IndiaMART allows one check every 5 minutes. Last check: ' . date('h:i A', strtotime($lastCall)) . '.');
        }
        $end   = time();
        $from  = (string) setting('Solar.indiamartLastPull');
        $start = $from !== '' ? max(strtotime($from) - 600, $end - 7 * 86400) : $end - 86400; // 10 min overlap; IndiaMART allows max 7 days
        $url   = rtrim((string) setting('Solar.indiamartApiUrl'), '?') . '?' . http_build_query([
            'glusr_crm_key' => $key, 'start_time' => date('d-M-Y H:i:s', $start), 'end_time' => date('d-M-Y H:i:s', $end),
        ]);
        setting('Solar.indiamartLastCall', date('Y-m-d H:i:s'));

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $body === '') {
            return self::finish(['added' => 0, 'merged' => 0, 'skipped' => 0], 'Could not reach IndiaMART: ' . ($err ?: 'HTTP ' . $http), false);
        }
        $json = json_decode((string) $body, true);
        if (! is_array($json)) {
            return self::finish(['added' => 0, 'merged' => 0, 'skipped' => 0], 'IndiaMART sent an unexpected reply (HTTP ' . $http . ').', false);
        }
        $code = (int) ($json['CODE'] ?? $http);
        if ($code !== 200) {
            // 204 = no new enquiries in the period; anything else is an error message from IndiaMART.
            $msg = $code === 204 ? 'No new enquiries.' : 'IndiaMART: ' . trim((string) ($json['MESSAGE'] ?? 'error ' . $code));

            return self::finish(['added' => 0, 'merged' => 0, 'skipped' => 0], $msg, $code === 204, $code === 204 ? $end : null);
        }
        $stats = self::import((array) ($json['RESPONSE'] ?? []));

        return self::finish($stats, null, true, $end);
    }

    private static function finish(array $stats, ?string $message, bool $ok, ?int $pulledUntil = null): array
    {
        $message ??= sprintf('%d new lead(s), %d added to existing leads, %d already imported.', $stats['added'], $stats['merged'], $stats['skipped']);
        if ($pulledUntil) {
            setting('Solar.indiamartLastPull', date('Y-m-d H:i:s', $pulledUntil));
        }
        setting('Solar.indiamartLastResult', date('d-m-Y h:i A') . ' – ' . $message);

        return $stats + ['message' => $message, 'ok' => $ok];
    }

    /**
     * @param list<array<string, mixed>> $rows IndiaMART enquiry records
     *
     * @return array{added:int, merged:int, skipped:int}
     */
    public static function import(array $rows): array
    {
        $stats = ['added' => 0, 'merged' => 0, 'skipped' => 0];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $ref     = trim((string) ($r['UNIQUE_QUERY_ID'] ?? ''));
            $product = trim((string) ($r['QUERY_PRODUCT_NAME'] ?? '') ?: (string) ($r['SUBJECT'] ?? ''));
            $message = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) ($r['QUERY_MESSAGE'] ?? ''))));
            $type    = ! empty($r['QUERY_TYPE']) ? "\nType: " . (['W' => 'Direct enquiry', 'B' => 'Buy lead', 'P' => 'Phone call', 'V' => 'Catalog view', 'WA' => 'WhatsApp'][$r['QUERY_TYPE']] ?? (string) $r['QUERY_TYPE']) : '';
            $result  = LeadInbox::add('IndiaMART', $ref === '' ? '' : 'IM-' . $ref, [
                'name' => (string) ($r['SENDER_NAME'] ?? ''), 'email' => (string) ($r['SENDER_EMAIL'] ?? ''),
                'phone' => LeadInbox::phone((string) ($r['SENDER_MOBILE'] ?? '')) ?: LeadInbox::phone((string) ($r['SENDER_PHONE'] ?? '')) ?: (string) ($r['SENDER_MOBILE_ALT'] ?? ''),
                'city' => (string) ($r['SENDER_CITY'] ?? ''), 'address' => (string) ($r['SENDER_ADDRESS'] ?? ''), 'company' => (string) ($r['SENDER_COMPANY'] ?? ''),
                'title' => $product, 'note' => ($product !== '' ? 'Enquiry: ' . $product . "\n" : '') . $message . $type,
            ]);
            $stats[$result]++;
        }
        LeadInbox::notify();

        return $stats;
    }

    /** Indian mobile as 10 digits. */
    public static function phone(string $raw): string
    {
        return LeadInbox::phone($raw);
    }
}
