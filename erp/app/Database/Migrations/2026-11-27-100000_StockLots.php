<?php

namespace App\Database\Migrations;

/**
 * FIFO stock lots: where each piece of stock came from (purchase / supplier
 * invoice / PO / supplier / date / rate) and where it went (dispatch, return).
 * Existing stock is traced by replaying the stock ledger in date order.
 */
class StockLots extends BaseSchema
{
    public function up()
    {
        $this->create('stock_lots', $this->id() + [
            'item_id'           => $this->fk(),
            'location_id'       => $this->fk(),
            'movement_id'       => $this->fk(),
            'source'            => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'purchase'],
            'purchase_id'       => $this->fk(true),
            'purchase_order_id' => $this->fk(true),
            'supplier_id'       => $this->fk(true),
            'purchase_no'       => $this->str(30),
            'bill_no'           => $this->str(50),
            'po_no'             => $this->str(30),
            'received_on'       => $this->date(false),
            'rate'              => $this->money(),
            'qty_in'            => $this->qty(),
            'qty_left'          => $this->qty(),
            'created_at'        => $this->datetime(),
        ], [['item_id', 'location_id', 'qty_left'], 'movement_id', 'purchase_id', 'purchase_order_id']);

        $this->create('stock_lot_moves', $this->id() + [
            'lot_id'      => $this->fk(),
            'movement_id' => $this->fk(),
            'qty'         => $this->qty(),
            'created_at'  => $this->datetime(),
        ], ['lot_id', 'movement_id']);

        \App\Libraries\Lots::rebuild();
    }

    public function down()
    {
        $this->forge->dropTable('stock_lot_moves', true);
        $this->forge->dropTable('stock_lots', true);
    }
}
