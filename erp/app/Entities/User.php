<?php

namespace App\Entities;

use App\Libraries\Features;
use CodeIgniter\Shield\Entities\User as ShieldUser;

/**
 * Shield user + per-company modules: a permission of a module or option that
 * is switched off is never granted, so its menu, screens and buttons disappear.
 */
class User extends ShieldUser
{
    public function can(string ...$permissions): bool
    {
        $permissions = array_values(array_filter($permissions, Features::permissionAllowed(...)));
        if ($permissions !== [] && $this->inGroup('superadmin')) {
            return true; // Super Admin always has everything (also permissions added after roles were saved)
        }

        return $permissions !== [] && parent::can(...$permissions);
    }
}
