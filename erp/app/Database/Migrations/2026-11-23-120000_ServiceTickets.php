<?php

namespace App\Database\Migrations;

/** Service complaints / tickets after installation, with an update history. */
class ServiceTickets extends BaseSchema
{
    public function up()
    {
        $this->create('service_tickets', $this->id() + [
            'ticket_no'      => $this->str(30, false),
            'customer_id'    => $this->fk(),
            'project_id'     => $this->fk(true),
            'category'       => $this->str(30, false),
            'priority'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'normal'],
            'description'    => $this->text(),
            'reported_via'   => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'phone'],
            'contact_phone'  => $this->str(20),
            'status'         => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'open'],
            'assigned_to'    => $this->fk(true),
            'scheduled_date' => $this->date(),
            'under_amc'      => $this->bool(0),
            'chargeable'     => $this->bool(0),
            'charge_amount'  => $this->money(),
            'resolution'     => $this->text(),
            'resolved_at'    => $this->datetime(),
            'resolved_by'    => $this->fk(true),
            'closed_at'      => $this->datetime(),
        ] + $this->stamps(), ['customer_id', 'project_id', 'status', 'assigned_to'], ['ticket_no']);

        $this->create('service_ticket_updates', $this->id() + [
            'ticket_id'  => $this->fk(),
            'status'     => $this->str(12),
            'note'       => $this->text(),
            'photo_path' => $this->str(255),
            'created_by' => $this->fk(true),
            'created_at' => $this->datetime(),
        ], ['ticket_id']);
    }

    public function down()
    {
        $this->forge->dropTable('service_ticket_updates', true);
        $this->forge->dropTable('service_tickets', true);
    }
}
