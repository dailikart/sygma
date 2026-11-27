<?php

namespace App\Filters;

use App\Libraries\Saas;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Multi-company mode (does nothing otherwise):
 * - the owner address only serves the owner control panel;
 * - a company address never serves the owner panel;
 * - suspended / expired companies see a notice instead of the ERP;
 * - a login session belongs to one company only.
 */
class SaasGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! Saas::enabled() || is_cli()) {
            return null;
        }
        $path = trim(service('uri')->getRoutePath(), '/');
        $isOwnerPath = $path === 'owner' || str_starts_with($path, 'owner/');
        if (Saas::isOwnerRequest()) {
            return $isOwnerPath ? null : redirect()->to(site_url('owner'));
        }
        if ($isOwnerPath) {
            throw PageNotFoundException::forPageNotFound();
        }
        $c = Saas::current();
        if (! $c) {
            throw PageNotFoundException::forPageNotFound();
        }

        // one session = one company
        $session = session();
        if (($pinned = $session->get('saas_code')) !== null && $pinned !== $c['code']) {
            $session->destroy();

            return redirect()->to(current_url());
        }
        $session->set('saas_code', $c['code']);

        $blocked = match (true) {
            $c['status'] === 'suspended' => ['Account on hold', $c['suspend_note'] ?: 'This account is on hold. Please contact Sygma Solar Suite.'],
            $c['status'] === 'setup'     => ['Being set up', 'This account is still being set up. Please try again in a few minutes.'],
            $c['expires_on'] && $c['expires_on'] < date('Y-m-d') => ['Subscription ended', 'The subscription ended on ' . date('d-m-Y', strtotime($c['expires_on'])) . '. Please contact Sygma Solar Suite to renew. Your data is safe.'],
            default => null,
        };
        if ($blocked) {
            return service('response')->setStatusCode(403)->setBody(view('saas/blocked', ['title' => $blocked[0], 'text' => $blocked[1], 'company' => $c]));
        }

        if (! $c['last_seen_at'] || strtotime($c['last_seen_at']) < time() - 600) {
            try {
                Saas::db()->table(Saas::T_COMPANIES)->where('id', $c['id'])->update(['last_seen_at' => date('Y-m-d H:i:s')]);
            } catch (\Throwable) {
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
