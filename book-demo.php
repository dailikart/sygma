<?php
/**
 * Receives the website forms (Book a demo, Request a callback, Newsletter):
 *  1. saves every request to a CSV file OUTSIDE the public website folder
 *     (…/domains/<site>/sygma-requests/requests.csv — download it from File Manager)
 *  2. emails it to NOTIFY_TO.
 * Answers JSON: {"ok":true} once the request is saved.
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

const NOTIFY_TO   = 'dailikart@gmail.com';
const MAX_PER_IP  = 8;    // requests per IP …
const WINDOW_SECS = 600;  // … per 10 minutes

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function answer(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    answer(405, ['ok' => false, 'error' => 'POST only']);
}

// Spam trap: real visitors never fill the hidden "website" field.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    answer(200, ['ok' => true]);
}

$clean = static fn ($v, int $max = 150): string => mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string) $v)), 0, $max);

$kind  = in_array($_POST['kind'] ?? '', ['demo', 'callback', 'newsletter'], true) ? $_POST['kind'] : 'demo';
$row   = [
    'received' => date('Y-m-d H:i:s'),
    'kind'     => $kind,
    'name'     => $clean($_POST['name'] ?? ''),
    'company'  => $clean($_POST['company'] ?? ''),
    'phone'    => $clean($_POST['phone'] ?? '', 20),
    'email'    => $clean($_POST['email'] ?? '', 120),
    'type'     => $clean($_POST['type'] ?? '', 60),
    'volume'   => $clean($_POST['volume'] ?? '', 20),
    'ip'       => $clean($_SERVER['REMOTE_ADDR'] ?? '', 45),
    'page'     => $clean($_SERVER['HTTP_REFERER'] ?? '', 200),
];

// Validation (same rules as the page).
$emailOk = filter_var($row['email'], FILTER_VALIDATE_EMAIL) !== false;
$phoneOk = preg_match('/^\+?[0-9 ()-]{7,20}$/', $row['phone']) === 1;
if ($kind === 'newsletter' && ! $emailOk) {
    answer(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
}
if ($kind === 'callback' && ! $phoneOk) {
    answer(422, ['ok' => false, 'error' => 'Please enter a valid mobile number.']);
}
if ($kind === 'demo' && ($row['name'] === '' || ! $phoneOk || ! $emailOk)) {
    answer(422, ['ok' => false, 'error' => 'Please fill in your name, mobile number and a valid email.']);
}

// Storage folder outside public_html (fallback: a locked folder inside it).
$dir = dirname(__DIR__) . '/sygma-requests';
if (! is_dir($dir) && ! @mkdir($dir, 0750, true)) {
    $dir = __DIR__ . '/_requests';
    if (! is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (! is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
}

// Rate limit per IP.
$rlFile = $dir . '/ratelimit.json';
$rl     = is_file($rlFile) ? (json_decode((string) file_get_contents($rlFile), true) ?: []) : [];
$now    = time();
$rl     = array_filter($rl, static fn ($times) => is_array($times) && max($times) > $now - WINDOW_SECS);
$hits   = array_values(array_filter($rl[$row['ip']] ?? [], static fn ($t) => $t > $now - WINDOW_SECS));
if (count($hits) >= MAX_PER_IP) {
    answer(429, ['ok' => false, 'error' => 'Too many requests. Please call us instead.']);
}
$hits[]          = $now;
$rl[$row['ip']]  = $hits;
@file_put_contents($rlFile, json_encode($rl), LOCK_EX);

// 1. Save (Excel opens this CSV directly).
$csv   = $dir . '/requests.csv';
$isNew = ! is_file($csv);
$fh    = @fopen($csv, 'ab');
if (! $fh) {
    answer(500, ['ok' => false, 'error' => 'Could not save your request. Please call us.']);
}
flock($fh, LOCK_EX);
if ($isNew) {
    fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ and names correctly
    fputcsv($fh, array_keys($row));
}
// Stop spreadsheet formulas being injected through form fields.
fputcsv($fh, array_map(static fn ($v) => preg_match('/^[=+\-@]/', $v) && ! preg_match('/^\+?\d/', $v) ? "'" . $v : $v, $row));
flock($fh, LOCK_UN);
fclose($fh);

// 2. Email.
$titles  = ['demo' => 'Demo request', 'callback' => 'Callback request', 'newsletter' => 'Newsletter sign-up'];
$subject = $titles[$kind] . ' – ' . ($row['company'] ?: ($row['name'] ?: ($row['phone'] ?: $row['email'])));
$lines   = [];
foreach (['name' => 'Name', 'company' => 'Company', 'phone' => 'Mobile', 'email' => 'Email', 'type' => 'Business type', 'volume' => 'Projects / month', 'received' => 'Received'] as $k => $label) {
    if ($row[$k] !== '') {
        $lines[] = str_pad($label . ':', 18) . $row[$k];
    }
}
$host    = preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$headers = [
    'From: Sygma Solar Suite website <noreply@' . $host . '>',
    'Content-Type: text/plain; charset=UTF-8',
];
if ($emailOk) {
    $headers[] = 'Reply-To: ' . $row['email'];
}
$mailed = @mail(NOTIFY_TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', implode("\n", $lines) . "\n\nAll requests: File Manager → sygma-requests/requests.csv\n", implode("\r\n", $headers));

answer(200, ['ok' => true, 'emailed' => (bool) $mailed]);
