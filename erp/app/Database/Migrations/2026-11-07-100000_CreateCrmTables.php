<?php

namespace App\Database\Migrations;

class CreateCrmTables extends BaseSchema
{
    public function up()
    {
        $this->create('customers', $this->id() + [
            'type'          => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'b2c'], // b2b | b2c
            'name'          => $this->str(191, false),
            'company'       => $this->str(),
            'gstin'         => $this->str(20),
            'phone'         => $this->str(20),
            'email'         => $this->str(),
            'address'       => $this->text(),
            'city'          => $this->str(100),
            'state'         => $this->str(100),
            'pincode'       => $this->str(10),
            'eb_service_no' => $this->str(50),
            'lead_id'       => $this->fk(true),
            'notes'         => $this->text(),
        ] + $this->stamps(true), ['type', 'phone']);

        $this->create('leads', $this->id() + [
            'name'               => $this->str(191, false),
            'phone'              => $this->str(20),
            'email'              => $this->str(),
            'address'            => $this->text(),
            'city'               => $this->str(100),
            'source'             => $this->str(60),
            'customer_type'      => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'b2c'],
            'required_kw'        => $this->qty(),
            'monthly_bill'       => $this->money(),
            'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'new'],
            'assigned_to'        => $this->fk(true),
            'next_followup_date' => $this->date(),
            'customer_id'        => $this->fk(true),
            'lost_reason'        => $this->str(),
            'notes'              => $this->text(),
        ] + $this->stamps(true), ['status', 'assigned_to', 'next_followup_date', 'phone']);

        $this->create('lead_followups', $this->id() + [
            'lead_id'            => $this->fk(),
            'note'               => $this->text(),
            'status_after'       => $this->str(20),
            'next_followup_date' => $this->date(),
            'created_by'         => $this->fk(true),
            'created_at'         => $this->datetime(),
        ], ['lead_id']);
    }

    public function down()
    {
        $this->forge->dropTable('lead_followups', true);
        $this->forge->dropTable('leads', true);
        $this->forge->dropTable('customers', true);
    }
}
