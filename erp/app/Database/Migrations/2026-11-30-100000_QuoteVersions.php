<?php

namespace App\Database\Migrations;

/**
 * Quotation versions (QT/2026-27/0001-R1, -R2 …) and the consumer–vendor agreement.
 *   root_id       first version of the quotation (null on the first version itself)
 *   revision      0 = original, 1 = R1 …
 *   superseded_by the newer version that replaced this one (null = latest)
 *   agreement     agreement details as JSON
 */
class QuoteVersions extends BaseSchema
{
    public function up()
    {
        $cols = [
            'root_id'       => $this->fk(true) + ['after' => 'quote_no'],
            'revision'      => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0, 'after' => 'root_id'],
            'superseded_by' => $this->fk(true) + ['after' => 'revision'],
            'agreement'     => ['type' => 'MEDIUMTEXT', 'null' => true, 'after' => 'quick_spec'],
        ];
        foreach ($cols as $name => $def) {
            if (! $this->db->fieldExists($name, 'quotations')) {
                $this->forge->addColumn('quotations', [$name => $def]);
            }
        }
        $this->db->query('CREATE INDEX quotations_root_id ON ' . $this->db->prefixTable('quotations') . ' (root_id)');
    }

    public function down()
    {
        $this->forge->dropColumn('quotations', ['root_id', 'revision', 'superseded_by', 'agreement']);
    }
}
