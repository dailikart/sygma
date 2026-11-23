<?php

namespace App\Database\Migrations;

class CreateServiceLedgerTables extends BaseSchema
{
    public function up()
    {
        // Customer documents (Aadhaar, EB bill, property tax…), collected at
        // confirmation and used for the PCR. Each file is stored under its title.
        $this->create('customer_documents', $this->id() + [
            'customer_id'   => $this->fk(),
            'project_id'    => $this->fk(true),
            'title'         => $this->str(150, false),
            'file_path'     => $this->str(255, false),
            'original_name' => $this->str(255),
            'uploaded_by'   => $this->fk(true),
            'created_at'    => $this->datetime(),
        ], ['customer_id', 'project_id']);

        // AMC plans are a master: each company sets its own duration and service interval.
        $this->create('amc_plans', $this->id() + [
            'name'            => $this->str(100, false),
            'duration_months' => ['type' => 'SMALLINT', 'default' => 60],
            'interval_months' => ['type' => 'SMALLINT', 'default' => 6],
            'is_free'         => $this->bool(),
            'price'           => $this->money(),
            'description'     => $this->text(),
            'is_default'      => $this->bool(0),
            'is_active'       => $this->bool(),
        ] + $this->stamps());

        $this->create('amc_contracts', $this->id() + [
            'contract_no' => $this->str(30, false),
            'project_id'  => $this->fk(),
            'customer_id' => $this->fk(),
            'amc_plan_id' => $this->fk(),
            'start_date'  => $this->date(false),
            'end_date'    => $this->date(false),
            'amount'      => $this->money(),
            'status'      => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'active'], // active | expired | cancelled
            'notes'       => $this->text(),
        ] + $this->stamps(), ['project_id', 'customer_id', 'end_date'], ['contract_no']);

        $this->create('amc_visits', $this->id() + [
            'amc_contract_id' => $this->fk(),
            'visit_no'        => ['type' => 'SMALLINT', 'default' => 1],
            'due_date'        => $this->date(false),
            'status'          => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'scheduled'], // scheduled | done | missed
            'assigned_to'     => $this->fk(true),
            'done_date'       => $this->date(),
            'done_by'         => $this->fk(true),
            'findings'        => $this->text(),
            'photo_path'      => $this->str(255),
            'reminded_at'     => $this->datetime(),
        ] + $this->stamps(), ['amc_contract_id', ['status', 'due_date']]);

        // ── Double-entry accounts ─────────────────────────────────────
        // Ledger names follow Tally. group_name is the Tally group.
        $this->create('ledgers', $this->id() + [
            'name'            => $this->str(150, false),
            'group_name'      => $this->str(60, false), // Sundry Debtors, Sundry Creditors, Bank Accounts, Cash-in-Hand, Sales Accounts, ...
            'ref_type'        => $this->str(30), // customer | supplier | account | income_head | expense_head | system
            'ref_id'          => $this->fk(true),
            'opening_balance' => $this->money(), // + Dr / − Cr
            'is_active'       => $this->bool(),
        ] + $this->stamps(), [['ref_type', 'ref_id'], 'group_name'], ['name']);

        $this->create('vouchers', $this->id() + [
            'voucher_type' => $this->str(20, false), // Sales | Receipt | Purchase | Debit Note | Payment | Journal | Contra
            'voucher_no'   => $this->str(40, false),
            'voucher_date' => $this->date(false),
            'narration'    => $this->text(),
            'ref_type'     => $this->str(30), // invoice | payment | purchase | purchase_return | supplier_payment | income | expense | manual
            'ref_id'       => $this->fk(true),
            'amount'       => $this->money(),
            'created_by'   => $this->fk(true),
            'created_at'   => $this->datetime(),
        ], [['voucher_type', 'voucher_date'], ['ref_type', 'ref_id']]);

        $this->create('voucher_entries', $this->id() + [
            'voucher_id' => $this->fk(),
            'ledger_id'  => $this->fk(),
            'debit'      => $this->money(),
            'credit'     => $this->money(),
        ], ['voucher_id', 'ledger_id']);

        // ── WhatsApp (WeBase Cloud API, same as the school ERP) ───────
        $this->create('whatsapp_templates', $this->id() + [
            'event'         => $this->str(40, false),
            'template_name' => $this->str(100, false),
            'language'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'en'],
            'params'        => $this->str(255), // comma list of placeholders → field_1, field_2…
            'is_active'     => $this->bool(),
        ] + $this->stamps(), [], ['event']);

        $this->create('message_log', $this->id() + [
            'channel'    => $this->str(12, false), // whatsapp | email
            'recipient'  => $this->str(191, false),
            'event'      => $this->str(40),
            'payload'    => $this->text(),
            'status'     => $this->str(12), // sent | failed
            'response'   => $this->text(),
            'created_at' => $this->datetime(),
        ], ['created_at']);
    }

    public function down()
    {
        foreach (['message_log', 'whatsapp_templates', 'voucher_entries', 'vouchers', 'ledgers',
            'amc_visits', 'amc_contracts', 'amc_plans', 'customer_documents'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
