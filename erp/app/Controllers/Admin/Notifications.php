<?php

namespace App\Controllers\Admin;

class Notifications extends AdminController
{
    public function index()
    {
        $rows = db_connect()->table('notifications')->where('user_id', auth()->id())
            ->orderBy('id', 'DESC')->limit(100)->get()->getResultArray();

        return $this->render('notifications', ['rows' => $rows]);
    }

    public function open($id)
    {
        $db  = db_connect();
        $row = $db->table('notifications')->where(['id' => (int) $id, 'user_id' => auth()->id()])->get()->getRowArray();
        if (! $row) {
            return redirect()->to('admin/notifications');
        }
        $db->table('notifications')->where('id', $row['id'])->update(['read_at' => date('Y-m-d H:i:s')]);

        return redirect()->to($row['link'] ? site_url($row['link']) : 'admin/notifications');
    }

    public function readAll()
    {
        db_connect()->table('notifications')->where(['user_id' => auth()->id(), 'read_at' => null])
            ->update(['read_at' => date('Y-m-d H:i:s')]);

        return redirect()->back();
    }
}
