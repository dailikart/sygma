<?php

namespace App\Controllers;

use App\Libraries\LeadInbox;

/**
 * Meta (Facebook / Instagram) Lead Ads and Google Ads lead forms → Leads.
 *
 * Meta:   GET  webhooks/meta  – subscription check (hub.verify_token must match).
 *         POST webhooks/meta  – "leadgen" events; each lead's answers are then read from the
 *                                Graph API with the Page access token. Signed with the app secret.
 * Google: POST webhooks/google – the lead's answers in the body; "google_key" must match.
 */
class LeadWebhooks extends BaseController
{
    private const META_FIELDS = [
        'full_name' => 'name', 'phone_number' => 'phone', 'email' => 'email', 'city' => 'city',
        'street_address' => 'address', 'company_name' => 'company',
    ];
    private const GOOGLE_FIELDS = [
        'FULL_NAME' => 'name', 'PHONE_NUMBER' => 'phone', 'WORK_PHONE' => 'phone', 'EMAIL' => 'email', 'WORK_EMAIL' => 'email',
        'CITY' => 'city', 'STREET_ADDRESS' => 'address', 'COMPANY_NAME' => 'company',
    ];

    public function metaVerify()
    {
        helper('app');
        $q = $this->request->getGet();
        if (($q['hub_mode'] ?? $q['hub.mode'] ?? '') === 'subscribe' && setting('Solar.metaEnabled')
            && hash_equals(LeadInbox::secret('metaVerifyToken'), (string) ($q['hub_verify_token'] ?? $q['hub.verify_token'] ?? ''))) {
            return $this->response->setContentType('text/plain')->setBody((string) ($q['hub_challenge'] ?? $q['hub.challenge'] ?? ''));
        }

        return $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function meta()
    {
        helper('app');
        if (! setting('Solar.metaEnabled')) {
            return $this->response->setStatusCode(404);
        }
        $raw    = (string) $this->request->getBody();
        $secret = (string) setting('Solar.metaAppSecret');
        if ($secret !== '') {
            $sig = (string) $this->request->getHeaderLine('X-Hub-Signature-256');
            if (! hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $sig)) {
                return $this->response->setStatusCode(403)->setBody('Bad signature');
            }
        }
        $json  = json_decode($raw, true) ?: [];
        $stats = ['added' => 0, 'merged' => 0, 'skipped' => 0];
        foreach ((array) ($json['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $ch) {
                if (($ch['field'] ?? '') !== 'leadgen' || empty($ch['value']['leadgen_id'])) {
                    continue;
                }
                $id   = (string) $ch['value']['leadgen_id'];
                $lead = $this->metaLead($id);
                $stats[LeadInbox::add('Facebook / Instagram', 'FB-' . $id, $lead)]++;
            }
        }
        LeadInbox::notify();
        setting('Solar.metaLastResult', date('d-m-Y h:i A') . sprintf(' – %d new, %d added to existing, %d already imported.', $stats['added'], $stats['merged'], $stats['skipped']));

        return $this->response->setJSON(['ok' => true]);
    }

    public function google()
    {
        helper('app');
        $json = json_decode((string) $this->request->getBody(), true) ?: [];
        if (! setting('Solar.googleEnabled') || ! hash_equals(LeadInbox::secret('googleKey'), (string) ($json['google_key'] ?? ''))) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Invalid key']);
        }
        $f = ['note' => ''];
        $first = $last = '';
        foreach ((array) ($json['user_column_data'] ?? []) as $col) {
            $id  = strtoupper((string) ($col['column_id'] ?? ''));
            $val = trim((string) ($col['string_value'] ?? ''));
            if ($val === '') {
                continue;
            }
            if ($id === 'FIRST_NAME') {
                $first = $val;
            } elseif ($id === 'LAST_NAME') {
                $last = $val;
            } elseif (isset(self::GOOGLE_FIELDS[$id]) && empty($f[self::GOOGLE_FIELDS[$id]])) {
                $f[self::GOOGLE_FIELDS[$id]] = $val;
            } else {
                $f['note'] .= ($col['column_name'] ?? $id) . ': ' . $val . "\n";
            }
        }
        $f['name'] = ($f['name'] ?? '') ?: trim($first . ' ' . $last);
        if (! empty($json['is_test'])) {
            $f['name'] = ($f['name'] ?: 'Google') . ' (Google test lead)';
        }
        $f['note'] = trim($f['note'] . (! empty($json['campaign_id']) ? 'Google Ads campaign ' . $json['campaign_id'] : ''));
        $ref       = 'GA-' . (string) ($json['lead_id'] ?? '');
        $result    = LeadInbox::add('Google Ads', $ref === 'GA-' ? '' : $ref, $f);
        LeadInbox::notify();
        setting('Solar.googleLastResult', date('d-m-Y h:i A') . ' – ' . ['added' => 'new lead added', 'merged' => 'added to an existing lead', 'skipped' => 'already imported'][$result] . (! empty($json['is_test']) ? ' (test)' : '') . '.');

        return $this->response->setJSON(new \stdClass());
    }

    /** A Meta lead's answers from the Graph API; if that fails the lead is still saved with the reason. */
    private function metaLead(string $id): array
    {
        $token = (string) setting('Solar.metaPageToken');
        $error = 'no Page access token in Settings';
        if ($token !== '') {
            $url = rtrim((string) setting('Solar.metaGraphUrl'), '/') . '/' . rawurlencode($id) . '?' . http_build_query([
                'fields' => 'field_data,created_time,ad_name,campaign_name,form_id,platform', 'access_token' => $token,
            ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
            $body = curl_exec($ch);
            $error = $body === false ? curl_error($ch) : '';
            curl_close($ch);
            $data = is_string($body) ? json_decode($body, true) : null;
            if (is_array($data) && isset($data['field_data'])) {
                $f = ['note' => ''];
                $first = $last = '';
                foreach ((array) $data['field_data'] as $fd) {
                    $name = strtolower((string) ($fd['name'] ?? ''));
                    $val  = trim(implode(', ', (array) ($fd['values'] ?? [])));
                    if ($val === '') {
                        continue;
                    }
                    if ($name === 'first_name') {
                        $first = $val;
                    } elseif ($name === 'last_name') {
                        $last = $val;
                    } elseif (isset(self::META_FIELDS[$name]) && empty($f[self::META_FIELDS[$name]])) {
                        $f[self::META_FIELDS[$name]] = $val;
                    } else {
                        $f['note'] .= ucfirst(str_replace('_', ' ', $name)) . ': ' . $val . "\n";
                    }
                }
                $f['name']  = ($f['name'] ?? '') ?: trim($first . ' ' . $last);
                $f['title'] = (string) ($data['ad_name'] ?? '');
                $f['note']  = trim($f['note'] . (! empty($data['campaign_name']) ? 'Campaign: ' . $data['campaign_name'] . "\n" : '')
                    . (! empty($data['ad_name']) ? 'Ad: ' . $data['ad_name'] . "\n" : '') . (($data['platform'] ?? '') === 'ig' ? 'Platform: Instagram' : 'Platform: Facebook'));

                return $f;
            }
            if (is_array($data) && isset($data['error']['message'])) {
                $error = 'Meta: ' . $data['error']['message'];
            } elseif ($error === '') {
                $error = 'unexpected reply from Meta';
            }
        }
        log_message('error', 'Meta lead ' . $id . ' details not read: ' . $error);

        return ['name' => 'Facebook lead ' . $id, 'note' => 'Details could not be read automatically (' . $error . '). Open Meta Business Suite → Leads Center to see this lead, then fix the token in Settings.'];
    }
}
