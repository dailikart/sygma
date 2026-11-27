<?php

namespace App\Database\Migrations;

/** One-click quotation: the chosen options, bill of materials and savings, as JSON. */
class QuickQuote extends BaseSchema
{
    public function up()
    {
        if (! $this->db->fieldExists('quick_spec', 'quotations')) {
            $this->forge->addColumn('quotations', ['quick_spec' => ['type' => 'MEDIUMTEXT', 'null' => true, 'after' => 'notes']]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('quotations', 'quick_spec');
    }
}
