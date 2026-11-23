<?php

namespace App\Libraries;

use Throwable;

/**
 * Sends email with the SMTP settings from Admin → Settings → Email (SMTP).
 * Falls back to the .env / app/Config/Email.php settings when SMTP is not
 * switched on in the app.
 */
class Mailer
{
    /**
     * @param list<array{path:string,name:string}> $attachments
     *
     * @return array{ok:bool, error?:string}
     */
    public static function send(string $to, string $subject, string $body, array $attachments = []): array
    {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid email address: ' . $to];
        }

        try {
            $email = service('email', null, false);
            $cfg   = [];
            if (setting('Solar.smtpEnabled')) {
                $cfg = self::smtpConfig();
            }
            $email->initialize($cfg + ['mailType' => 'html', 'charset' => 'UTF-8', 'newline' => "\r\n", 'CRLF' => "\r\n"]);

            $fromEmail = (string) (setting('Solar.mailFromEmail') ?: setting('Solar.smtpUser') ?: config('Email')->fromEmail);
            $fromName  = (string) (setting('Solar.mailFromName') ?: setting('Solar.companyName'));
            $email->setFrom($fromEmail, $fromName);
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage(nl2br(esc($body)));
            foreach ($attachments as $a) {
                if (is_file($a['path'])) {
                    $email->attach($a['path'], 'attachment', $a['name']);
                }
            }

            if ($email->send(false)) {
                return ['ok' => true];
            }

            return ['ok' => false, 'error' => self::explain(strip_tags($email->printDebugger([])), $cfg)];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * SMTP settings with common mistakes corrected: encryption matched to the
     * port (465 = ssl, 587 = tls), "ssl://" removed from the host, and the
     * spaces Google shows in an App Password removed.
     */
    public static function smtpConfig(): array
    {
        $host   = preg_replace('#^(ssl|tls)://#i', '', trim((string) setting('Solar.smtpHost')));
        $port   = (int) setting('Solar.smtpPort') ?: 465;
        $crypto = strtolower(trim((string) setting('Solar.smtpCrypto')));
        $crypto = match ($port) { 465 => 'ssl', 587 => 'tls', default => in_array($crypto, ['ssl', 'tls'], true) ? $crypto : '' };
        $pass   = (string) setting('Solar.smtpPass');
        if (str_contains(strtolower($host), 'gmail') || str_contains(strtolower($host), 'google')) {
            $pass = str_replace(' ', '', $pass);
        }

        return [
            'protocol' => 'smtp', 'SMTPHost' => $host, 'SMTPUser' => trim((string) setting('Solar.smtpUser')),
            'SMTPPass' => $pass, 'SMTPPort' => $port, 'SMTPCrypto' => $crypto, 'SMTPTimeout' => 20,
        ];
    }

    /** A short, readable reason for a failed send: what to check, then the mail server's own reply. */
    private static function explain(string $debug, array $cfg): string
    {
        $lines   = array_values(array_filter(array_map('trim', preg_split('/\R/', $debug))));
        $replies = array_values(array_filter($lines, static fn ($l) => preg_match('/^[45]\d\d[ -]/', $l)));
        $server  = $replies ? implode(' ', array_slice($replies, -2)) : '';
        $all     = strtolower($debug);
        $host    = strtolower((string) ($cfg['SMTPHost'] ?? ''));

        $hint = match (true) {
            str_contains($all, '535') || str_contains($all, 'username and password not accepted') || str_contains($all, 'authentication failed')
                => str_contains($host, 'gmail')
                    ? 'Gmail rejected the login. Use a 16-letter App Password (Google Account → Security → 2-Step Verification → App passwords), not your normal Gmail password.'
                    : 'The mail server rejected the username / password. Check the SMTP username (full email address) and password.',
            str_contains($all, 'unable to initiate auth') || str_contains($all, 'starttls')
                => 'The mail server did not accept login on this port/encryption. Use port 465 with SSL, or port 587 with TLS'
                    . (str_contains($host, 'gmail') ? ' (Gmail: smtp.gmail.com, 465, SSL, App Password).' : (str_contains($host, 'hostinger') ? ' (Hostinger: smtp.hostinger.com, 465, SSL).' : '.')),
            str_contains($all, 'unable to connect') || str_contains($all, 'timed out') || str_contains($all, 'connection refused')
                => 'Could not connect to ' . ($cfg['SMTPHost'] ?? 'the SMTP host') . ':' . ($cfg['SMTPPort'] ?? '') . '. Check the host and port; some hosting plans block outgoing SMTP to other providers (then use your hosting email, e.g. Hostinger).',
            default => '',
        };
        if ($hint === '' && $server === '') {
            $useful = array_filter($lines, static fn ($l) => preg_match('/fail|error|unable|denied|refused/i', $l));

            return mb_substr(implode(' ', array_slice(array_values($useful), 0, 2)) ?: trim($debug), 0, 300);
        }

        return trim($hint . ($server !== '' ? ' Server said: ' . mb_substr($server, 0, 200) : ''));
    }
}
