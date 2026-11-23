<?php

namespace App\Database\Migrations;

/**
 * Who pays the EB / meter charges: the company (included in our price, shown as "–")
 * or the customer (an extra amount collected beyond the invoice, e.g. 5000).
 * Set on the customer as a default and on each invoice.
 */
class EbChargesPaidBy extends BaseSchema
{
    public function up()
    {
        foreach (['customers' => 'eb_service_no', 'invoices' => 'finance_mode'] as $table => $after) {
            $this->forge->addColumn($table, [
                'eb_charges_by'     => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => $after],
                'eb_charges_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'eb_charges_by'],
            ]);
        }
    }

    public function down()
    {
        foreach (['customers', 'invoices'] as $table) {
            $this->forge->dropColumn($table, ['eb_charges_by', 'eb_charges_amount']);
        }
    }
}
