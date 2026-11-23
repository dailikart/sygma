<?php

namespace App\Database\Migrations;

/** Delivery note details on dispatches. */
class DeliveryNoteFields extends BaseSchema
{
    public function up()
    {
        $this->forge->addColumn('dispatches', [
            'dispatched_through' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'driver_name'],
            'destination'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'dispatched_through'],
            'terms_of_delivery'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'destination'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('dispatches', ['dispatched_through', 'destination', 'terms_of_delivery']);
    }
}
