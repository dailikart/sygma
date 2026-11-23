<?php

namespace App\Controllers\Admin;

/**
 * Role → module permission matrix. Saved in the settings table, which
 * overrides the defaults in app/Config/AuthGroups.php.
 */
class Roles extends AdminController
{
    public function index()
    {
        $this->requirePermission('roles.manage');

        return $this->render('roles/index', [
            'groups'      => setting('AuthGroups.groups'),
            'permissions' => array_filter(setting('AuthGroups.permissions'), \App\Libraries\Features::permissionAllowed(...), ARRAY_FILTER_USE_KEY),
            'matrix'      => $this->expandedMatrix(),
        ]);
    }

    public function save()
    {
        $this->requirePermission('roles.manage');
        $posted = (array) $this->request->getPost('perm');
        $valid  = array_keys(setting('AuthGroups.permissions'));
        $matrix = [];
        foreach (array_keys(setting('AuthGroups.groups')) as $group) {
            if ($group === 'superadmin') {
                continue; // always full access, see below
            }
            $matrix[$group] = array_values(array_intersect(array_keys((array) ($posted[$group] ?? [])), $valid));
        }
        $matrix['superadmin'] = $this->allWildcards();
        setting('AuthGroups.matrix', $matrix);

        return redirect()->to('admin/roles')->with('message', 'Access rights saved. They apply on the next page load.');
    }

    public function reset()
    {
        $this->requirePermission('roles.manage');
        setting()->forget('AuthGroups.matrix');

        return redirect()->to('admin/roles')->with('message', 'Access rights reset to the defaults.');
    }

    /** Matrix with wildcards ("leads.*") expanded to individual permissions. */
    private function expandedMatrix(): array
    {
        $perms = array_keys(setting('AuthGroups.permissions'));
        $out   = [];
        foreach ((array) setting('AuthGroups.matrix') as $group => $list) {
            foreach ($list as $p) {
                if (str_ends_with($p, '.*')) {
                    $prefix = substr($p, 0, -1);
                    foreach ($perms as $x) {
                        if (str_starts_with($x, $prefix)) {
                            $out[$group][$x] = true;
                        }
                    }
                } else {
                    $out[$group][$p] = true;
                }
            }
        }

        return $out;
    }

    private function allWildcards(): array
    {
        $mods = [];
        foreach (array_keys(setting('AuthGroups.permissions')) as $p) {
            $mods[explode('.', $p)[0] . '.*'] = true;
        }

        return array_keys($mods);
    }
}
