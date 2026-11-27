<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Demo-site protection (only when `demo.mode = true` in .env).
 *
 * Several team members share the demo logins, so changes that would lock others out or touch the server are refused: passwords, users, roles,
 * settings (SMTP / WhatsApp / lead-integration keys), IndiaMART fetch
 * and bulk imports. Database updates that come with new code are applied
 * automatically (and the "Run them now" button is allowed), so new features
 * work on the demo without anyone having to unlock it.
 * Everything else (leads, invoices, projects, stock...) works normally and is
 * kept (run `php spark demo:reset` to go back to the original demo data).
 */
class DemoGuard implements FilterInterface
{
    /** URI prefixes (after admin/) that are read-only in the demo. */
    private const LOCKED = ['profile', 'users', 'roles', 'settings', 'maintenance', 'import', 'whatsapp-templates', 'indiamart'];

    /** Allowed even though they sit under a locked prefix. */
    private const ALLOWED = ['admin/maintenance/migrate'];

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
        self::syncDatabase();
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $path = trim(service('uri')->getRoutePath(), '/');
        if (in_array($path, self::ALLOWED, true)) {
            return null;
        }
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

    /**
     * Runs database updates shipped with new code, once per set of migration
     * files (no click, no SSH). They only add tables / columns the new code
     * needs; the demo data is kept.
     */
    public static function syncDatabase(): void
    {
        $files = glob(APPPATH . 'Database/Migrations/*.php') ?: [];
        $stamp = hash('sha256', implode('|', array_map('basename', $files)));
        $file  = WRITEPATH . 'demo_migrations.sync';
        if (is_file($file) && trim((string) file_get_contents($file)) === $stamp) {
            return;
        }
        try {
            if (\App\Controllers\Admin\Maintenance::pending()) {
                $runner = \Config\Services::migrations();
                $runner->setNamespace(null);
                $runner->latest();
                log_message('info', 'Demo: database updates applied automatically.');
            }
            file_put_contents($file, $stamp);
        } catch (\Throwable $e) {
            log_message('error', 'Demo database update failed: ' . $e->getMessage());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
