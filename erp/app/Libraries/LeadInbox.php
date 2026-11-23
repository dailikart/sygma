<?php

namespace App\Libraries;

/**
 * One place where leads from outside come in (IndiaMART, Meta Lead Ads, Google Ads):
 *  - never imported twice (external_ref, e.g. "IM-123", "FB-456", "GA-789");
 *  - a repeat enquiry from a phone that already has an open lead becomes a follow-up on that lead;
 *  - otherwise a new lead with today's follow-up date; marketing is notified (once per batch).
 */
class LeadInbox
{
    /** @var array<string, list<string>> source => new lead labels, for one notification per source */
    private static array $batch = [];

    /**
     * @param array{name?:string, phone?:string, email?:string, city?:string, address?:string, company?:string, note?:string, kw?:float, title?:string} $f
     *
     * @return 'added'|'merged'|'skipped'
     */
    public static function add(string $source, string $ref, array $f): string
    {
        $db = db_connect();
        if ($ref === '' || $db->table('leads')->where('external_ref', $ref)->countAllResults()
            || $db->table('lead_followups')->like('note', '[' . $ref . ']')->countAllResults()) {
            return 'skipped';
        }
        $phone   = self::phone((string) ($f['phone'] ?? ''));
        $name    = trim((string) ($f['name'] ?? '')) ?: $source . ' enquiry';
        $company = trim((string) ($f['company'] ?? ''));
        $note    = trim((string) ($f['note'] ?? '')) . ($company !== '' && ! str_contains((string) ($f['note'] ?? ''), $company) ? "\nCompany: " . $company : '');
        $now     = date('Y-m-d H:i:s');

        $existing = $phone !== '' ? $db->table('leads')->where('phone', $phone)->where('deleted_at', null)->whereNotIn('status', ['lost'])->orderBy('id', 'DESC')->get()->getRowArray() : null;
        if ($existing) {
            $db->table('lead_followups')->insert([
                'lead_id' => $existing['id'], 'note' => 'New ' . $source . ' enquiry [' . $ref . "]:\n" . mb_substr(trim($note), 0, 2000),
                'status_after' => $existing['status'], 'next_followup_date' => date('Y-m-d'), 'created_by' => null, 'created_at' => $now,
            ]);
            $db->table('leads')->where('id', $existing['id'])->update(['next_followup_date' => date('Y-m-d'), 'updated_at' => $now]);

            return 'merged';
        }
        $kw = (float) ($f['kw'] ?? 0);
        if ($kw <= 0 && preg_match('/(\d+(?:\.\d+)?)\s*(?:kw|kilo\s*watt)/i', $note . ' ' . ($f['title'] ?? ''), $m)) {
            $kw = (float) $m[1];
        }
        $db->table('leads')->insert([
            'name' => mb_substr($name, 0, 150), 'phone' => $phone !== '' ? $phone : null,
            'email' => filter_var(trim((string) ($f['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null,
            'address' => trim((string) ($f['address'] ?? '')) ?: null, 'city' => trim((string) ($f['city'] ?? '')) ?: null,
            'source' => $source, 'external_ref' => $ref, 'customer_type' => $company !== '' ? 'b2b' : 'b2c',
            'required_kw' => $kw > 0 && $kw < 10000 ? $kw : 0, 'monthly_bill' => 0, 'status' => 'new',
            'next_followup_date' => date('Y-m-d'), 'notes' => mb_substr(trim($note), 0, 2000) ?: null,
            'created_by' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);
        self::$batch[$source][] = $name . (! empty($f['title']) ? ' – ' . $f['title'] : '');

        return 'added';
    }

    /** One notification per source for the leads added since the last call. */
    public static function notify(): void
    {
        foreach (self::$batch as $source => $names) {
            Notifier::role('marketing', count($names) === 1 ? 'New ' . $source . ' lead: ' . $names[0] : count($names) . ' new ' . $source . ' leads',
                implode("\n", array_slice($names, 0, 5)), 'admin/leads?source=' . rawurlencode($source));
        }
        self::$batch = [];
    }

    /** Indian mobile as 10 digits ("+91-98765 43210" → "9876543210"). */
    public static function phone(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);
        if (strlen($d) > 10 && str_starts_with($d, '91')) {
            $d = substr($d, -10);
        }
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = substr($d, 1);
        }

        return $d;
    }

    /** "Add to settings" helper: a random secret stored once in the given Solar setting. */
    public static function secret(string $settingKey): string
    {
        $t = (string) setting('Solar.' . $settingKey);
        if ($t === '') {
            $t = bin2hex(random_bytes(16));
            setting('Solar.' . $settingKey, $t);
        }

        return $t;
    }
}
