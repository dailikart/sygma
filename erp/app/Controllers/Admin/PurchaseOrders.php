<?php

namespace App\Controllers\Admin;

class PurchaseOrders extends PurchaseDocs
{
    protected string $table = 'purchase_orders';
    protected string $itemsTable = 'purchase_order_items';
    protected string $fk = 'purchase_order_id';
    protected string $noField = 'po_no';
    protected string $dateField = 'po_date';
    protected string $prefixKey = 'Solar.poPrefix';
    protected string $uri = 'purchase-orders';
    protected string $title = 'Purchase Orders';
    protected bool $hasLocation = false;

    protected function extraHead(array $p): array
    {
        return ['expected_date' => ($p['expected_date'] ?? '') ?: null, 'status' => 'open', 'notes' => ($p['notes'] ?? '') ?: null];
    }

    public function cancel($id)
    {
        $this->requirePermission('purchases.manage');
        $po = $this->findOr404('purchase_orders', $id);
        if ($po['status'] !== 'open') {
            return $this->back('Only an open purchase order with nothing received can be cancelled.');
        }
        db_connect()->table('purchase_orders')->where('id', $po['id'])->update(['status' => 'cancelled']);

        return redirect()->back()->with('message', 'Purchase order cancelled.');
    }

    /** Purchases already received against the PO are kept; they just lose the link. */
    protected function beforeDelete(array $doc): void
    {
        db_connect()->table('purchases')->where('purchase_order_id', $doc['id'])->update(['purchase_order_id' => null]);
    }
}
