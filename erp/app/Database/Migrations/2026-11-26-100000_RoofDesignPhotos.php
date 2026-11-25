<?php

namespace App\Database\Migrations;

/**
 * Photos for 3D roof designs: the top-view photo traced under the plan
 * ('plan') and site photos for reference ('site').
 */
class RoofDesignPhotos extends BaseSchema
{
    public function up()
    {
        $this->create('roof_design_photos', $this->id() + [
            'design_id' => $this->fk(true),
            'kind'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'site'],
            'path'      => $this->str(255, false),
            'caption'   => $this->str(150),
            'width'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'height'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'latitude'  => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
        ] + $this->stamps(), ['design_id', 'created_by']);
    }

    public function down()
    {
        $this->forge->dropTable('roof_design_photos', true);
    }
}
