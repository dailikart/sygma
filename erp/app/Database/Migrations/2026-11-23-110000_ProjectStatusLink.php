<?php

namespace App\Database\Migrations;

/** Private link for the customer to follow their project (no login). */
class ProjectStatusLink extends BaseSchema
{
    public function up()
    {
        $this->forge->addColumn('projects', [
            'status_token'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'status'],
            'status_viewed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'status_token'],
        ]);
        $this->db->query('CREATE UNIQUE INDEX projects_status_token ON ' . $this->db->prefixTable('projects') . ' (status_token)');
    }

    public function down()
    {
        $this->forge->dropColumn('projects', ['status_token', 'status_viewed_at']);
    }
}
