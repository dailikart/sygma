<?php

namespace App\Database\Migrations;

/**
 * Batch 2 changes: finance mode (cash / loan) and loan disbursements,
 * invoice print format, fixed document types, serial numbers at dispatch.
 */
class ClientChangesBatch2 extends BaseSchema
{
    public function up()
    {
        $this->forge->addColumn('invoices', [
            'finance_mode' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'cash', 'after' => 'subsidy_type'], // cash | loan
            'loan_bank'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'finance_mode'],
            'loan_amount'  => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0, 'after' => 'loan_bank'],
            'loan_ref'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'loan_amount'],
            'print_format' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'itemwise', 'after' => 'loan_ref'], // itemwise | cumulative
        ]);

        // Expected loan disbursements (bank pays the company directly, usually in 3 parts).
        $this->create('loan_tranches', $this->id() + [
            'invoice_id'    => $this->fk(),
            'seq'           => ['type' => 'SMALLINT', 'default' => 1],
            'amount'        => $this->money(),
            'expected_date' => $this->date(),
            'status'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'], // pending | received
            'payment_id'    => $this->fk(true),
            'notes'         => $this->str(),
        ] + $this->stamps(), ['invoice_id']);

        $this->forge->addColumn('payments', [
            'loan_tranche_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'account_id'],
        ]);

        // Fixed set of customer documents.
        $this->create('document_types', $this->id() + [
            'name'        => $this->str(100, false),
            'is_required' => $this->bool(1),
            'sort_order'  => ['type' => 'SMALLINT', 'default' => 0],
            'is_active'   => $this->bool(),
        ] + $this->stamps());
        $now = date('Y-m-d H:i:s');
        $i   = 0;
        foreach ([['Aadhaar card', 1], ['EB bill', 1], ['Property tax receipt', 1], ['Bank passbook / cancelled cheque', 1],
            ['Passport size photo', 1], ['PAN card', 0], ['Site photo', 0], ['Signed agreement', 0]] as [$name, $req]) {
            $this->db->table('document_types')->insert([
                'name' => $name, 'is_required' => $req, 'sort_order' => ++$i, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->forge->addColumn('customer_documents', [
            'document_type_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'project_id'],
        ]);

        // Serial numbers captured by the store at dispatch (inverter, panels…).
        $this->forge->addColumn('dispatch_items', [
            'serial_nos' => ['type' => 'TEXT', 'null' => true, 'after' => 'qty'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('dispatch_items', 'serial_nos');
        $this->forge->dropColumn('customer_documents', 'document_type_id');
        $this->forge->dropTable('document_types', true);
        $this->forge->dropColumn('payments', 'loan_tranche_id');
        $this->forge->dropTable('loan_tranches', true);
        $this->forge->dropColumn('invoices', ['finance_mode', 'loan_bank', 'loan_amount', 'loan_ref', 'print_format']);
    }
}
