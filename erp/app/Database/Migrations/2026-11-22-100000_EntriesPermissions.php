<?php

namespace App\Database\Migrations;

/**
 * New permissions "entries.edit" / "entries.delete" (edit and delete saved
 * invoices, payments, customers, projects). When Roles & Access was already
 * saved, the saved matrix does not know them yet: give them to Super Admin
 * and Admin / Manager there too. Other roles get them only when ticked.
 */
class EntriesPermissions extends BaseSchema
{
    public function up()
    {
        $saved = setting()->get('AuthGroups.matrix');
        $defaults = (new \Config\AuthGroups())->matrix;
        if (! is_array($saved) || $saved === $defaults) {
            return; // using the defaults from app/Config/AuthGroups.php
        }
        $saved['superadmin'] = array_values(array_unique(array_merge($saved['superadmin'] ?? [], ['entries.*'])));
        if (isset($saved['admin'])) {
            $saved['admin'] = array_values(array_unique(array_merge($saved['admin'], ['entries.edit', 'entries.delete'])));
        }
        setting()->set('AuthGroups.matrix', $saved);
    }

    public function down()
    {
    }
}
