<?php

namespace App\Controllers\Admin;

/**
 * User log: who made which entry, when, from where.
 */
class UserLog extends Reports
{
    public function index()
    {
        $this->requirePermission('users.manage');
        [$from, $to] = fy_range();
        if (! $this->request->getGet('from')) {
            $from = date('Y-m-d', strtotime('-7 days'));
            $to   = date('Y-m-d');
        }
        $b = db_connect()->table('activity_log')
            ->where('created_at >=', $from . ' 00:00:00')->where('created_at <=', $to . ' 23:59:59');
        if ($u = $this->request->getGet('user')) {
            $b->where('user_id', (int) $u);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->like('description', $q);
        }
        $type = $this->request->getGet('type');
        if ($type === 'logins') {
            $b->whereIn('action', ['login', 'logout', 'failed_login']);
        } elseif ($type === 'changes') {
            $b->whereNotIn('action', ['login', 'logout', 'failed_login']);
        }
        $rows = $b->orderBy('id', 'DESC')->limit(2000)->get()->getResultArray();

        if ($this->request->getGet('export') === 'excel') {
            return $this->excel('User_Log', ['Date & time', 'User', 'Action', 'Details'], array_map(static fn ($r) => [
                fdate($r['created_at'], true), user_name($r['user_id']), $r['action'], $r['description'],
            ], $rows));
        }

        return $this->render('user_log', ['rows' => $rows, 'from' => $from, 'to' => $to, 'users' => staff_options()]);
    }
}
