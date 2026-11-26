<?php

namespace App\Database\Migrations;

/** GST e-invoice: IRN, acknowledgement and signed QR code returned by the portal. */
class EInvoice extends BaseSchema
{
    public function up()
    {
        $this->forge->addColumn('invoices', [
            'irn'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'status'],
            'irn_ack_no'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'irn'],
            'irn_ack_date' => ['type' => 'DATETIME', 'null' => true, 'after' => 'irn_ack_no'],
            'irn_qr'       => ['type' => 'TEXT', 'null' => true, 'after' => 'irn_ack_date'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('invoices', ['irn', 'irn_ack_no', 'irn_ack_date', 'irn_qr']);
    }
}
