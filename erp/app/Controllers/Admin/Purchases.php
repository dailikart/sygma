<?php

namespace App\Controllers\Admin;

use App\Libraries\Ledger;
use App\Libraries\Stock;

/**
 * Purchase (supplier bill / goods received): stock comes in at the chosen
 * location and the purchase voucher is posted.
 */
class Purchases extends PurchaseDocs
{
    protected string $table = 'purchases';
    protected string $itemsTable = 'purchase_items';
    protected string $fk = 'purchase_id';
    protected string $noField = 'purchase_no';
    protected string $dateField = 'bill_date';
    protected string $prefixKey = 'Solar.purchasePrefix';
    protected string $uri = 'purchases';
    protected string $title = 'Purchases';

    /** ?po=ID fills the form with what is still to be received on that PO. */
    protected function prefill(): array
    {
        $poId = (int) $this->request->getGet('po');
        if (! $poId) {
            return parent::prefill();
        }
        $po   = $this->findOr404('purchase_orders', $poId);
        $rows = [];
        foreach (db_connect()->table('purchase_order_items')->where('purchase_order_id', $poId)->get()->getResultArray() as $l) {
            $left = (float) $l['qty'] - (float) $l['received_qty'];
            if ($left > 0) {
                $rows[] = ['item_id' => $l['item_id'], 'qty' => $left, 'rate' => $l['rate'], 'tax_percent' => $l['tax_percent']];
            }
        }

        return ['head' => ['supplier_id' => $po['supplier_id'], 'purchase_order_id' => $poId, 'po_no' => $po['po_no']], 'rows' => $rows];
    }

    protected function extraHead(array $p): array
    {
        return [
            'location_id' => (int) $p['location_id'], 'bill_no' => trim((string) ($p['bill_no'] ?? '')) ?: null,
            'purchase_order_id' => ($p['purchase_order_id'] ?? '') ?: null, 'notes' => ($p['notes'] ?? '') ?: null,
        ];
    }

    /** The supplier's invoice no. is required and cannot be entered twice for the same supplier. */
    protected function checkHead(array $p): ?string
    {
        $billNo = trim((string) ($p['bill_no'] ?? ''));
        if ($billNo === '') {
            return 'Enter the supplier invoice no.';
        }
        if (($p['doc_date'] ?? '') > date('Y-m-d')) {
            return 'Supplier invoice date cannot be in the future.';
        }
        $dup = db_connect()->table('purchases')->select('purchase_no')
            ->where(['supplier_id' => (int) $p['supplier_id'], 'bill_no' => $billNo])->get()->getRowArray();

        return $dup ? 'Supplier invoice ' . $billNo . ' is already entered as purchase ' . $dup['purchase_no'] . '.' : null;
    }

    protected function afterStore(int $id, array $lines, array $p): void
    {
        $db = db_connect();
        foreach ($lines as $l) {
            Stock::move((int) $l['item_id'], (int) $p['location_id'], (float) $l['qty'], 'purchase', 'purchase', $id, 'Bill ' . ($p['bill_no'] ?? ''));
        }
        // Update the PO's received quantities and status.
        if ($poId = (int) ($p['purchase_order_id'] ?? 0)) {
            foreach ($lines as $l) {
                $db->query('UPDATE ' . $db->prefixTable('purchase_order_items') . ' SET received_qty = received_qty + ? WHERE purchase_order_id = ? AND item_id = ?', [$l['qty'], $poId, $l['item_id']]);
            }
            $left = $db->table('purchase_order_items')->where('purchase_order_id', $poId)->where('received_qty < qty', null, false)->countAllResults();
            $db->table('purchase_orders')->where('id', $poId)->update(['status' => $left ? 'partial' : 'received']);
        }
        Ledger::postPurchase($id);
    }

    protected function beforeDelete(array $doc): void
    {
        $db = db_connect();
        $returns = $db->table('purchase_returns')->select('return_no')->where('purchase_id', $doc['id'])->get()->getResultArray();
        if ($returns) {
            throw new \RuntimeException('This purchase has return(s) ' . implode(', ', array_column($returns, 'return_no')) . '. Delete those returns first.');
        }
        $lines = $db->table('purchase_items l')->select('l.item_id, SUM(l.qty) AS qty, i.name')->join('items i', 'i.id = l.item_id')
            ->where('l.purchase_id', $doc['id'])->groupBy('l.item_id, i.name')->get()->getResultArray();
        foreach ($lines as $l) {
            $have = Stock::balance((int) $l['item_id'], (int) $doc['location_id']);
            if ($have + 0.0001 < (float) $l['qty']) {
                throw new \RuntimeException(sprintf('Cannot delete: %s from this purchase is already used (in store now %s, purchased %s). Stock would go negative.', $l['name'], qty($have), qty($l['qty'])));
            }
        }
        Stock::reverse('purchase', (int) $doc['id']);
        Ledger::unpost('purchase', (int) $doc['id']);
        if ($poId = (int) ($doc['purchase_order_id'] ?? 0)) {
            foreach ($lines as $l) {
                $db->query('UPDATE ' . $db->prefixTable('purchase_order_items') . ' SET received_qty = GREATEST(received_qty - ?, 0) WHERE purchase_order_id = ? AND item_id = ?', [$l['qty'], $poId, $l['item_id']]);
            }
            $po = $db->table('purchase_orders')->select('status')->where('id', $poId)->get()->getRowArray();
            if ($po && $po['status'] !== 'cancelled') {
                $got  = (float) ($db->table('purchase_order_items')->selectSum('received_qty')->where('purchase_order_id', $poId)->get()->getRow()->received_qty ?? 0);
                $left = $db->table('purchase_order_items')->where('purchase_order_id', $poId)->where('received_qty < qty', null, false)->countAllResults();
                $db->table('purchase_orders')->where('id', $poId)->update(['status' => $got <= 0 ? 'open' : ($left ? 'partial' : 'received')]);
            }
        }
    }
}
