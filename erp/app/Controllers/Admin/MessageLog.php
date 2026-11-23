<?php

namespace App\Controllers\Admin;

class MessageLog extends AdminController
{
    public function index()
    {
        $this->requirePermission('settings.manage');

        return $this->render('message_log', ['rows' => db_connect()->table('message_log')->orderBy('id', 'DESC')->limit(300)->get()->getResultArray()]);
    }
}
