<?php

namespace App\Database\Migrations;

class CreateProjectTables extends BaseSchema
{
    public function up()
    {
        $this->create('projects', $this->id() + [
            'project_no'    => $this->str(30, false),
            'customer_id'   => $this->fk(),
            'invoice_id'    => $this->fk(true),
            'customer_type' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'b2c'],
            'subsidy_type'  => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'non_subsidy'],
            'installation_type_id' => $this->fk(true),
            'commissioned_on'      => $this->date(),
            'capacity_kw'   => $this->qty(),
            'kit_id'        => $this->fk(true),
            'site_address'  => $this->text(),
            'current_stage' => $this->str(30),
            'status'        => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'active'], // active | completed | on_hold | cancelled
            'completed_at'  => $this->datetime(),
        ] + $this->stamps(), ['customer_id', 'status', 'current_stage'], ['project_no']);

        $this->create('project_stages', $this->id() + [
            'project_id'   => $this->fk(),
            'stage'        => $this->str(30, false),
            'sequence'     => ['type' => 'SMALLINT', 'default' => 0],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pending'], // pending | active | done | rejected
            'assigned_to'  => $this->fk(true),
            'planned_date' => $this->date(),
            'data'         => $this->text(), // JSON: stage-specific fields
            'notes'        => $this->text(),
            'started_at'   => $this->datetime(),
            'completed_at' => $this->datetime(),
            'completed_by' => $this->fk(true),
        ], ['project_id', ['stage', 'status'], 'assigned_to']);

        $this->create('project_files', $this->id() + [
            'project_id'    => $this->fk(),
            'stage'         => $this->str(30),
            'label'         => $this->str(100),
            'file_path'     => $this->str(255, false),
            'original_name' => $this->str(255),
            'uploaded_by'   => $this->fk(true),
            'created_at'    => $this->datetime(),
        ], ['project_id']);

        $this->create('dispatches', $this->id() + [
            'dispatch_no'   => $this->str(30, false),
            'project_id'    => $this->fk(),
            'location_id'   => $this->fk(),
            'status'        => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'draft'], // draft | dispatched | delivered
            'vehicle_no'    => $this->str(30),
            'driver_name'   => $this->str(100),
            'dispatched_at' => $this->datetime(),
            'dispatched_by' => $this->fk(true),
            'delivered_at'  => $this->datetime(),
            'delivered_by'  => $this->fk(true),
            'delivery_note' => $this->text(),
        ] + $this->stamps(), ['project_id', 'status'], ['dispatch_no']);

        $this->create('dispatch_items', $this->id() + [
            'dispatch_id' => $this->fk(),
            'item_id'     => $this->fk(),
            'qty'         => $this->qty(),
        ], ['dispatch_id', 'item_id']);

        $this->create('notifications', $this->id() + [
            'user_id'    => $this->fk(),
            'title'      => $this->str(191, false),
            'message'    => $this->text(),
            'link'       => $this->str(255),
            'read_at'    => $this->datetime(),
            'created_at' => $this->datetime(),
        ], [['user_id', 'read_at']]);

        $this->create('activity_log', $this->id() + [
            'user_id'     => $this->fk(true),
            'entity'      => $this->str(30),
            'entity_id'   => $this->fk(true),
            'action'      => $this->str(60),
            'description' => $this->text(),
            'created_at'  => $this->datetime(),
        ], [['entity', 'entity_id']]);
    }

    public function down()
    {
        foreach (['activity_log', 'notifications', 'dispatch_items', 'dispatches', 'project_files',
            'project_stages', 'projects'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
