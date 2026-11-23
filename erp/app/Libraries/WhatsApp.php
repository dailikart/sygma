<?php

namespace App\Libraries;

/**
 * WeBase WhatsApp Cloud API gateway (same provider and API as the school ERP,
 * dailikart/demo application/libraries/Webase_whatsapp.php).
 *
 *   POST {base_url}/{vendor_uid}/contact/send-template-message?token={api_token}
 *
 * Business-initiated messages must use a Meta-approved template. Credentials
 * are set in Admin → Settings → WhatsApp.
 */
class WhatsApp
{
    public static function isEnabled(): bool
    {
        return Features::on('whatsapp') && (bool) setting('Solar.whatsappEnabled')
            && setting('Solar.whatsappBaseUrl') && setting('Solar.whatsappVendorUid') && setting('Solar.whatsappApiToken');
    }

    /** Digits with country code. A bare 10-digit number is Indian (91). */
    public static function normalizePhone(?string $phone, string $cc = '91'): string
    {
        $d = ltrim(preg_replace('/\D+/', '', (string) $phone), '0');

        return strlen($d) === 10 ? $cc . $d : $d;
    }

    /**
     * @param list<string> $params ordered body params → field_1, field_2, …
     */
    public static function sendTemplate(string $phone, string $template, array $params = [], ?string $language = null): array
    {
        if (! self::isEnabled()) {
            return ['ok' => false, 'error' => 'WhatsApp is not configured or is switched off.'];
        }
        $to = self::normalizePhone($phone);
        if ($to === '') {
            return ['ok' => false, 'error' => 'Missing phone number.'];
        }
        $payload = [
            'phone_number'      => $to,
            'template_name'     => $template,
            'template_language' => $language ?: 'en',
        ];
        if ($from = setting('Solar.whatsappFromPhoneId')) {
            $payload['from_phone_number_id'] = $from;
        }
        $i = 1;
        foreach (array_values($params) as $p) {
            // The API rejects empty params, so send a dash instead.
            $payload['field_' . $i++] = trim((string) $p) === '' ? '-' : (string) $p;
        }

        return self::post('contact/send-template-message', $payload);
    }

    private static function post(string $endpoint, array $payload): array
    {
        $token = (string) setting('Solar.whatsappApiToken');
        $url   = rtrim((string) setting('Solar.whatsappBaseUrl'), '/') . '/' . rawurlencode((string) setting('Solar.whatsappVendorUid'))
            . '/' . ltrim($endpoint, '/') . '?token=' . rawurlencode($token);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => $err, 'http' => $code];
        }

        return ['ok' => $code >= 200 && $code < 300, 'http' => $code, 'response' => json_decode((string) $resp, true) ?? $resp];
    }
}
