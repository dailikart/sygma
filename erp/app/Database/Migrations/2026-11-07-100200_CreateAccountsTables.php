<?php

namespace App\Database\Migrations;

class CreateAccountsTables extends BaseSchema
{
    public function up()
    {
        // Bank and cash ledgers. `name` must match the ledger name in Tally.
        $this->create('accounts', $this->id() + [
            'name'            => $this->str(100, false),
            'type'            => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'bank'], // bank | cash
            'account_no'      => $this->str(50),
            'ifsc'            => $this->str(20),
            'opening_balance' => $this->money(),
            'is_active'       => $this->bool(),
        ] + $this->stamps());

        // Income / expense heads. `name` must match the ledger name in Tally.
        foreach (['income_heads', 'expense_heads'] as $t) {
            $this->create($t, $this->id() + [
                'name'        => $this->str(100, false),
                'description' => $this->str(),
                'is_active'   => $this->bool(),
            ] + $this->stamps());
        }

        $this->create('invoices', $this->id() + [
            'invoice_no'   => $this->str(30, false),
            'customer_id'  => $this->fk(),
            'project_id'   => $this->fk(true),
            'invoice_date' => $this->date(false),
            'due_date'     => $this->date(),
            'subsidy_type' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'non_subsidy'], // subsidy | non_subsidy
            'capacity_kw'  => $this->qty(),
            'installation_type_id' => $this->fk(true),
            'gst_type'     => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'intra'],
            'subtotal'     => $this->money(),
            'tax_total'    => $this->money(),
            'total'        => $this->money(),
            'amount_paid'  => $this->money(),
            'status'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'unpaid'], // unpaid | partial | paid | cancelled
            'notes'        => $this->text(),
        ] + $this->stamps(), ['customer_id', 'invoice_date', 'status'], ['invoice_no']);

        $this->create('invoice_items', $this->id() + [
            'invoice_id'  => $this->fk(),
            'item_id'     => $this->fk(true),
            'description' => $this->str(255, false),
            'hsn'         => $this->str(20),
            'qty'         => $this->qty(),
            'rate'        => $this->money(),
            'tax_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'tax_amount'  => $this->money(),
            'amount'      => $this->money(),
        ], ['invoice_id']);

        // Customer receipts.
        $this->create('payments', $this->id() + [
            'receipt_no'   => $this->str(30, false),
            'invoice_id'   => $this->fk(),
            'customer_id'  => $this->fk(),
            'account_id'   => $this->fk(),
            'payment_date' => $this->date(false),
            'amount'       => $this->money(),
            'mode'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'bank'], // bank | upi | cheque | cash
            'reference'    => $this->str(100),
            'notes'        => $this->text(),
        ] + $this->stamps(), ['invoice_id', 'payment_date'], ['receipt_no']);

        $this->create('supplier_payments', $this->id() + [
            'voucher_no'   => $this->str(30, false),
            'supplier_id'  => $this->fk(),
            'account_id'   => $this->fk(),
            'payment_date' => $this->date(false),
            'amount'       => $this->money(),
            'mode'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'bank'],
            'reference'    => $this->str(100),
            'notes'        => $this->text(),
        ] + $this->stamps(), ['supplier_id', 'payment_date'], ['voucher_no']);

        $this->create('incomes', $this->id() + [
            'voucher_no'     => $this->str(30, false),
            'income_head_id' => $this->fk(),
            'account_id'     => $this->fk(),
            'income_date'    => $this->date(false),
            'amount'         => $this->money(),
            'received_from'  => $this->str(),
            'reference'      => $this->str(100),
            'notes'          => $this->text(),
        ] + $this->stamps(), ['income_date'], ['voucher_no']);

        $this->create('expenses', $this->id() + [
            'voucher_no'      => $this->str(30, false),
            'expense_head_id' => $this->fk(),
            'account_id'      => $this->fk(),
            'project_id'      => $this->fk(true),
            'expense_date'    => $this->date(false),
            'amount'          => $this->money(),
            'paid_to'         => $this->str(),
            'reference'       => $this->str(100),
            'attachment'      => $this->str(255),
            'notes'           => $this->text(),
        ] + $this->stamps(), ['expense_date', 'project_id'], ['voucher_no']);
    }

    public function down()
    {
        foreach (['expenses', 'incomes', 'supplier_payments', 'payments', 'invoice_items', 'invoices',
            'expense_heads', 'income_heads', 'accounts'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
