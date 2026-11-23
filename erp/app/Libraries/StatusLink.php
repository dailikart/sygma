<?php

namespace App\Libraries;

/** The customer's private project-status link: site_url('track/<token>'). */
class StatusLink
{
    /** Token of a project, created the first time it is needed. */
    public static function token(int $projectId): string
    {
        $db  = db_connect();
        $tok = (string) $db->table('projects')->select('status_token')->where('id', $projectId)->get()->getRow('status_token');
        if ($tok === '') {
            $tok = bin2hex(random_bytes(16));
            $db->table('projects')->where('id', $projectId)->update(['status_token' => $tok]);
        }

        return $tok;
    }

    public static function url(int $projectId): string
    {
        return site_url('track/' . self::token($projectId));
    }

    /** New token: the old link stops working. */
    public static function reset(int $projectId): string
    {
        $tok = bin2hex(random_bytes(16));
        db_connect()->table('projects')->where('id', $projectId)->update(['status_token' => $tok, 'status_viewed_at' => null]);

        return $tok;
    }
}
