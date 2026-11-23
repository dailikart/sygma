<?php

namespace App\Libraries;

use Throwable;

/**
 * In-app notifications + email (sent in the background by the queue worker).
 */
class Notifier
{
    /** @param list<int> $userIds */
    public static function users(array $userIds, string $title, string $message = '', string $link = ''): void
    {
        $userIds = array_unique(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return;
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        foreach ($userIds as $uid) {
            $db->table('notifications')->insert([
                'user_id' => $uid, 'title' => $title, 'message' => $message,
                'link' => $link, 'created_at' => $now,
            ]);
        }

        // Emails go through the queue so the page does not wait for SMTP.
        $emails = $db->table('auth_identities')
            ->select('secret')
            ->where('type', 'email_password')
            ->whereIn('user_id', $userIds)
            ->get()->getResultArray();
        foreach ($emails as $e) {
            self::queueEmail($e['secret'], $title, $message . ($link ? "\n\nOpen: " . site_url($link) : ''));
        }
    }

    /** Notifies every active user in a role (Shield group). */
    public static function role(string $role, string $title, string $message = '', string $link = ''): void
    {
        $ids = array_column(
            db_connect()->table('auth_groups_users gu')
                ->select('gu.user_id')
                ->join('users u', 'u.id = gu.user_id')
                ->where(['gu.group' => $role, 'u.active' => 1])
                ->where('u.deleted_at', null)
                ->get()->getResultArray(),
            'user_id'
        );
        self::users($ids, $title, $message, $link);
    }

    /**
     * Staff / background email. Sent immediately unless "use queue" is on in
     * Settings (then the queue worker sends it).
     */
    public static function queueEmail(string $to, string $subject, string $body): void
    {
        if ($to === '') {
            return;
        }
        if (! setting('Solar.emailUseQueue')) {
            $r = Mailer::send($to, $subject, $body);
            if (! $r['ok']) {
                log_message('error', 'Email to ' . $to . ' failed: ' . ($r['error'] ?? ''));
            }

            return;
        }

        try {
            service('queue')->push('emails', 'email', ['to' => $to, 'subject' => $subject, 'body' => $body]);
        } catch (Throwable $e) {
            log_message('error', 'Could not queue email: ' . $e->getMessage());
        }
    }
}
