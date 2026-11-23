<?php

namespace App\Database\Migrations;

/**
 * Quotations / proposals: given to a lead or customer before the invoice,
 * with system lines, subsidy and savings (payback), shared as PDF / link,
 * and converted into the invoice when accepted.
 */
class Quotations extends BaseSchema
{
    public function up()
    {
        $this->create('quotations', $this->id() + [
            'quote_no'             => $this->str(30, false),
            'lead_id'              => $this->fk(true),
            'customer_id'          => $this->fk(true),
            'party_name'           => $this->str(150, false),
            'party_phone'          => $this->str(20),
            'party_email'          => $this->str(150),
            'party_address'        => $this->text(),
            'party_city'           => $this->str(100),
            'party_state'          => $this->str(100),
            'customer_type'        => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'b2c'],
            'quote_date'           => $this->date(false),
            'valid_until'          => $this->date(),
            'subsidy_type'         => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'subsidy'],
            'installation_type_id' => $this->fk(true),
            'capacity_kw'          => $this->qty(),
            'kit_id'               => $this->fk(true),
            'gst_type'             => ['type' => 'VARCHAR', 'constraint' => 6, 'default' => 'intra'],
            'print_format'         => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'cumulative'],
            'subtotal'             => $this->money(),
            'tax_total'            => $this->money(),
            'total'                => $this->money(),
            'subsidy_amount'       => $this->money(),
            'eb_charges_by'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'eb_charges_amount'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'units_per_kw_day'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 4],
            'tariff'               => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
            'design_path'          => $this->str(255),
            'notes'                => $this->text(),
            'status'               => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'draft'],
            'share_token'          => $this->str(40),
            'invoice_id'           => $this->fk(true),
            'sent_at'              => $this->datetime(),
            'viewed_at'            => $this->datetime(),
        ] + $this->stamps(), ['lead_id', 'customer_id', 'status', 'quote_date'], ['quote_no', 'share_token']);

        $this->create('quotation_items', $this->id() + [
            'quotation_id' => $this->fk(),
            'item_id'      => $this->fk(true),
            'description'  => $this->str(255, false),
            'hsn'          => $this->str(20),
            'qty'          => $this->qty(),
            'rate'         => $this->money(),
            'tax_percent'  => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'tax_amount'   => $this->money(),
            'amount'       => $this->money(),
        ], ['quotation_id']);

        // Sites whose Roles & Access was already saved: give the new permissions to the usual roles.
        $saved = setting()->get('AuthGroups.matrix');
        if (is_array($saved) && $saved !== (new \Config\AuthGroups())->matrix) {
            foreach (['superadmin' => ['quotations.*'], 'admin' => ['quotations.*'], 'marketing' => ['quotations.*'], 'accounts' => ['quotations.view']] as $group => $perms) {
                if (isset($saved[$group])) {
                    $saved[$group] = array_values(array_unique(array_merge($saved[$group], $perms)));
                }
            }
            setting()->set('AuthGroups.matrix', $saved);
        }
    }

    public function down()
    {
        $this->forge->dropTable('quotation_items', true);
        $this->forge->dropTable('quotations', true);
    }
}
