<?php

namespace App\Controllers\Admin;

use Config\Services;

/**
 * Database updates from the browser (for hosting without SSH).
 * Super Admin only.
 */
class Maintenance extends AdminController
{
    private function guard(): void
    {
        if (! auth()->user()->inGroup('superadmin')) {
            throw new \App\Exceptions\ForbiddenException('Only a Super Admin can run database updates.');
        }
    }

    /** Migrations that have not run yet. */
    public static function pending(): array
    {
        $runner  = Services::migrations();
        $applied = [];
        try {
            foreach (db_connect()->table('migrations')->select('version, class')->get()->getResultArray() as $r) {
                $applied[$r['class']] = true;
            }
        } catch (\Throwable) {
        }
        $out = [];
        foreach ($runner->findMigrations() as $m) {
            if (! isset($applied[$m->class])) {
                $out[] = $m->uid . ' ' . $m->name;
            }
        }

        return $out;
    }

    public function index()
    {
        $this->guard();

        return $this->render('maintenance', ['pending' => self::pending()]);
    }

    public function migrate()
    {
        $this->guard();
        try {
            $runner = Services::migrations();
            $runner->setNamespace(null);
            $ok = $runner->latest();
        } catch (\Throwable $e) {
            return $this->back('Database update failed: ' . $e->getMessage());
        }
        \App\Libraries\Workflow::log('system', 0, 'migrate', 'Database updated by ' . auth()->user()->username);

        return redirect()->to('admin/maintenance')->with($ok ? 'message' : 'error', $ok ? 'Database is up to date.' : 'Some updates did not run. Check writable/logs.');
    }

    /** Demo site only: back to the original demo data (same as `php spark demo:reset`). */
    public function resetDemo()
    {
        $this->requirePermission('settings.manage');
        if (! \App\Filters\DemoGuard::enabled()) {
            throw new \App\Exceptions\ForbiddenException();
        }
        try {
            \App\Commands\DemoReset::restore();
        } catch (\Throwable $e) {
            return redirect()->to('admin/maintenance')->with('error', 'Reset failed: ' . $e->getMessage());
        }

        return redirect()->to('admin')->with('message', 'Demo data reset to the original sample data.');
    }
}
