<?php

namespace App\Database\Migrations;

class CreateInventoryTables extends BaseSchema
{
    public function up()
    {
        $this->create('locations', $this->id() + [
            'name'      => $this->str(100, false),
            'address'   => $this->text(),
            'is_active' => $this->bool(),
        ] + $this->stamps());

        $this->create('suppliers', $this->id() + [
            'name'            => $this->str(191, false),
            'contact_person'  => $this->str(),
            'phone'           => $this->str(20),
            'email'           => $this->str(),
            'gstin'           => $this->str(20),
            'state'           => $this->str(100),
            'address'         => $this->text(),
            'opening_balance' => $this->money(), // amount we owe them at start
            'is_active'       => $this->bool(),
        ] + $this->stamps(true));

        // Store hierarchy: Installation type → Category → Item.
        $this->create('installation_types', $this->id() + [
            'name'        => $this->str(100, false), // e.g. On-grid, Off-grid, Hybrid, Solar pump
            'description' => $this->str(),
            'is_active'   => $this->bool(),
        ] + $this->stamps());

        $this->create('item_categories', $this->id() + [
            'installation_type_id' => $this->fk(true), // empty = used by all types
            'name'                 => $this->str(100, false), // e.g. Solar Panel, Inverter, Structure
            'is_active'            => $this->bool(),
        ] + $this->stamps(), ['installation_type_id']);

        $this->create('items', $this->id() + [
            'sku'                  => $this->str(50),
            'name'                 => $this->str(191, false),
            'installation_type_id' => $this->fk(true),
            'category_id'          => $this->fk(true),
            'unit'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Nos'],
            'hsn'            => $this->str(20),
            'tax_percent'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'purchase_price' => $this->money(),
            'sale_price'     => $this->money(),
            'min_stock'      => $this->qty(),
            'is_active'      => $this->bool(),
        ] + $this->stamps(true), ['installation_type_id', 'category_id'], ['sku']);

        // Item groups (kits): the set of items for one system size, e.g. "3 kW On-grid".
        $this->create('kits', $this->id() + [
            'name'                 => $this->str(100, false),
            'installation_type_id' => $this->fk(true),
            'capacity_kw'          => $this->qty(),
            'description' => $this->text(),
            'is_active'   => $this->bool(),
        ] + $this->stamps(), ['capacity_kw']);

        $this->create('kit_items', $this->id() + [
            'kit_id'  => $this->fk(),
            'item_id' => $this->fk(),
            'qty'     => $this->qty(),
        ], ['kit_id']);

        // Every stock change: + in, − out. Balance = SUM(qty) per item & location.
        $this->create('stock_movements', $this->id() + [
            'item_id'     => $this->fk(),
            'location_id' => $this->fk(),
            'qty'         => $this->qty(),
            'type'        => $this->str(20, false), // purchase | purchase_return | dispatch | adjustment | opening
            'ref_type'    => $this->str(30),
            'ref_id'      => $this->fk(true),
            'note'        => $this->str(),
            'created_by'  => $this->fk(true),
            'created_at'  => $this->datetime(),
        ], [['item_id', 'location_id'], ['ref_type', 'ref_id']]);

        $this->create('purchase_orders', $this->id() + [
            'po_no'         => $this->str(30, false),
            'supplier_id'   => $this->fk(),
            'po_date'       => $this->date(false),
            'expected_date' => $this->date(),
            'gst_type'      => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'intra'],
            'subtotal'      => $this->money(),
            'tax_total'     => $this->money(),
            'total'         => $this->money(),
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'], // open | partial | received | cancelled
            'notes'         => $this->text(),
        ] + $this->stamps(), ['supplier_id', 'status'], ['po_no']);

        $this->create('purchase_order_items', $this->id() + [
            'purchase_order_id' => $this->fk(),
            'item_id'           => $this->fk(),
            'qty'               => $this->qty(),
            'received_qty'      => $this->qty(),
            'rate'              => $this->money(),
            'tax_percent'       => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'tax_amount'        => $this->money(),
            'amount'            => $this->money(),
        ], ['purchase_order_id']);

        $this->create('purchases', $this->id() + [
            'purchase_no'       => $this->str(30, false),
            'supplier_id'       => $this->fk(),
            'location_id'       => $this->fk(),
            'purchase_order_id' => $this->fk(true),
            'bill_no'           => $this->str(50),
            'bill_date'         => $this->date(false),
            'gst_type'          => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'intra'],
            'subtotal'          => $this->money(),
            'tax_total'         => $this->money(),
            'total'             => $this->money(),
            'notes'             => $this->text(),
        ] + $this->stamps(), ['supplier_id', 'bill_date'], ['purchase_no']);

        $this->create('purchase_items', $this->id() + [
            'purchase_id' => $this->fk(),
            'item_id'     => $this->fk(),
            'qty'         => $this->qty(),
            'rate'        => $this->money(),
            'tax_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'tax_amount'  => $this->money(),
            'amount'      => $this->money(),
        ], ['purchase_id']);

        $this->create('purchase_returns', $this->id() + [
            'return_no'   => $this->str(30, false),
            'supplier_id' => $this->fk(),
            'location_id' => $this->fk(),
            'purchase_id' => $this->fk(true),
            'return_date' => $this->date(false),
            'gst_type'    => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'intra'],
            'subtotal'    => $this->money(),
            'tax_total'   => $this->money(),
            'total'       => $this->money(),
            'reason'      => $this->text(),
        ] + $this->stamps(), ['supplier_id', 'return_date'], ['return_no']);

        $this->create('purchase_return_items', $this->id() + [
            'purchase_return_id' => $this->fk(),
            'item_id'            => $this->fk(),
            'qty'                => $this->qty(),
            'rate'               => $this->money(),
            'tax_percent'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'tax_amount'         => $this->money(),
            'amount'             => $this->money(),
        ], ['purchase_return_id']);
    }

    public function down()
    {
        foreach (['purchase_return_items', 'purchase_returns', 'purchase_items', 'purchases',
            'purchase_order_items', 'purchase_orders', 'stock_movements', 'kit_items', 'kits',
            'items', 'item_categories', 'installation_types', 'suppliers', 'locations'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
