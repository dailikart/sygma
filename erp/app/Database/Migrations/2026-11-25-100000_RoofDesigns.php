<?php

namespace App\Database\Migrations;

/**
 * 3D roof designs: roof outline, obstacles and panel layout (JSON) for a lead,
 * with the simulated capacity, shading and generation. A quotation can be
 * created from a design; its 3D picture becomes the quotation's design image.
 */
class RoofDesigns extends BaseSchema
{
    public function up()
    {
        $this->create('roof_designs', $this->id() + [
            'design_no'     => $this->str(30, false),
            'lead_id'       => $this->fk(true),
            'customer_id'   => $this->fk(true),
            'quotation_id'  => $this->fk(true),
            'name'          => $this->str(150, false),
            'latitude'      => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
            'longitude'     => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
            'data'          => ['type' => 'LONGTEXT', 'null' => true],
            'panel_item_id' => $this->fk(true),
            'panel_watt'    => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0],
            'panel_count'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'capacity_kw'   => $this->qty(),
            'kwh_per_kwp'   => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0],
            'shading_loss'  => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'kwh_year'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'monthly_kwh'   => $this->text(),
            'yield_source'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'snapshot_path' => $this->str(255),
        ] + $this->stamps(true), ['lead_id', 'customer_id', 'quotation_id'], ['design_no']);

        $this->forge->addColumn('quotations', ['roof_design_id' => $this->fk(true) + ['after' => 'design_path']]);

        // Sites whose Roles & Access was already saved: give the new permissions to the usual roles.
        $saved = setting()->get('AuthGroups.matrix');
        if (is_array($saved) && $saved !== (new \Config\AuthGroups())->matrix) {
            foreach (['superadmin' => ['designs.*'], 'admin' => ['designs.*'], 'marketing' => ['designs.view', 'designs.manage'], 'surveyor' => ['designs.view', 'designs.manage']] as $group => $perms) {
                if (isset($saved[$group])) {
                    $saved[$group] = array_values(array_unique(array_merge($saved[$group], $perms)));
                }
            }
            setting()->set('AuthGroups.matrix', $saved);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('quotations', ['roof_design_id']);
        $this->forge->dropTable('roof_designs', true);
    }
}
