<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Demo-site protection (only when `demo.mode = true` in .env).
 *
 * Several team members share the demo logins, so changes that would lock others out or touch the server are refused: passwords, users, roles,
 * settings (SMTP / WhatsApp / lead-integration keys), IndiaMART fetch,
 * database updates and bulk imports.
 * Everything else (leads, invoices, projects, stock...) works normally and is
 * kept (run `php spark demo:reset` to go back to the original demo data).
 */
class DemoGuard implements FilterInterface
{
    /** URI prefixes (after admin/) that are read-only in the demo. */
    private const LOCKED = ['profile', 'users', 'roles', 'settings', 'maintenance', 'import', 'whatsapp-templates', 'indiamart'];

    public static function enabled(): bool
    {
        return filter_var(env('demo.mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! self::enabled()) {
            return null;
        }
        self::syncPassword();
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $path = trim(service('uri')->getRoutePath(), '/');
        foreach (self::LOCKED as $prefix) {
            if ($path === 'admin/' . $prefix || str_starts_with($path, 'admin/' . $prefix . '/')) {
                return redirect()->back()->with('warning', 'This is view-only on the demo site, so the demo keeps working for the whole team.');
            }
        }

        return null;
    }

    /**
     * Applies demo.password from .env by itself (no command / SSH needed): the
     * first page load after it is added or changed sets it on every demo login.
     */
    public static function syncPassword(): void
    {
        $password = (string) env('demo.password', '');
        $file     = WRITEPATH . 'demo_password.sync';
        if ($password === '' || (is_file($file) && trim((string) file_get_contents($file)) === hash('sha256', $password))) {
            return;
        }
        try {
            \App\Commands\DemoReset::applyPassword();
            file_put_contents($file, hash('sha256', $password));
        } catch (\Throwable $e) {
            log_message('error', 'Demo password sync failed: ' . $e->getMessage());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
