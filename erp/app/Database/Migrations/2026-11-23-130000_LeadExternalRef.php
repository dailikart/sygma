<?php

namespace App\Database\Migrations;

/** Reference of a lead imported from outside (e.g. IndiaMART query id) so it is never imported twice. */
class LeadExternalRef extends BaseSchema
{
    public function up()
    {
        $this->forge->addColumn('leads', ['external_ref' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'source']]);
        $this->db->query('CREATE INDEX leads_external_ref ON ' . $this->db->prefixTable('leads') . ' (external_ref)');
    }

    public function down()
    {
        $this->forge->dropColumn('leads', ['external_ref']);
    }
}
