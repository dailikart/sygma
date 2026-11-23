<?php

namespace App\Controllers\Admin;

use App\Libraries\Features as F;

/**
 * Administration → Modules & Options: which modules / options this company has.
 * Only on installs where you (the software owner) set features.manage = true in .env;
 * a customer's own Super Admin never sees this screen.
 */
class Features extends AdminController
{
    public function index()
    {
        $this->guard();

        return $this->render('features/index', ['catalogue' => F::config()->catalogue, 'off' => F::offKeys(), 'saved' => (array) setting('Features.off')]);
    }

    public function save()
    {
        $this->guard();
        $on  = array_keys((array) $this->request->getPost('on'));
        $off = array_values(array_diff(array_keys(F::all()), $on));
        F::save($off);

        $auto = array_filter(array_keys(F::all()), static fn ($k) => ! str_contains($k, '.') && F::offBecause($k) !== null);
        $msg  = 'Modules saved.';
        if ($auto !== []) {
            $msg .= ' Not available until the module they need is on: ' . implode(', ', array_map(F::title(...), $auto)) . '.';
        }

        return redirect()->to('admin/features')->with('message', $msg);
    }

    private function guard(): void
    {
        $this->requirePermission('settings.manage');
        if (! F::manageable()) {
            throw new \App\Exceptions\ForbiddenException();
        }
    }
}
