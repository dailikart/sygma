<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public demo protection (only when `demo.mode = true` in .env).
 *
 * Visitors share the published demo logins, so changes that would lock other
 * visitors out or touch the server are refused: passwords, users, roles,
 * settings (SMTP / WhatsApp keys), database updates and bulk imports.
 * Everything else (leads, invoices, projects, stock...) works normally and is
 * wiped by the nightly `php spark demo:reset`.
 */
class DemoGuard implements FilterInterface
{
    /** URI prefixes (after admin/) that are read-only in the demo. */
    private const LOCKED = ['profile', 'users', 'roles', 'settings', 'maintenance', 'import', 'whatsapp-templates'];

    public static function enabled(): bool
    {
        return filter_var(env('demo.mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! self::enabled() || strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $path = trim(service('uri')->getRoutePath(), '/');
        foreach (self::LOCKED as $prefix) {
            if ($path === 'admin/' . $prefix || str_starts_with($path, 'admin/' . $prefix . '/')) {
                return redirect()->back()->with('warning', 'This action is disabled in the live demo, so the demo stays usable for everyone. Book a demo to try it on your own copy.');
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
