<?php

namespace App\Controllers;

use App\Libraries\Saas;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Owner "Open as Super Admin": a one-time link (valid 2 minutes) signed by the owner panel.
 * Logs in as the company's first Super Admin and records it in the company's user log.
 */
class SaasSupport extends BaseController
{
    public function login($token)
    {
        $c = Saas::current();
        $d = $c ? Saas::readSupportToken((string) $token) : null;
        if (! $c || ! $d || (int) $d['c'] !== (int) $c['id']) {
            throw PageNotFoundException::forPageNotFound();
        }
        $userId = Saas::firstAdminId();
        if (! $userId) {
            return redirect()->to(site_url('login'))->with('error', 'This company has no active Super Admin.');
        }
        Saas::log('support_login', (int) $c['id'], 'nonce ' . $d['n'], (int) $d['o']);
        if (auth()->loggedIn()) {
            auth()->logout();
        }
        auth()->loginById($userId);
        db_connect()->table('activity_log')->insert([
            'user_id' => $userId, 'entity' => 'audit', 'action' => 'support_login',
            'description' => 'Support login by Sygma Solar Suite (software provider) | IP ' . $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        session()->set('saas_support', true);

        return redirect()->to(site_url('admin'))->with('message', 'Support login: you are signed in as this company\'s Super Admin.');
    }
}
