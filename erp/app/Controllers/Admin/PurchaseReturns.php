<?php

namespace App\Controllers\Admin;

use App\Libraries\Ledger;
use App\Libraries\Stock;

/**
 * Purchase return (debit note), always against a purchase: the supplier,
 * supplier invoice, location, items, rates and GST come from that purchase;
 * only the quantities to return are entered (up to what is still returnable).
 * Stock goes out of the location and the supplier balance is reduced.
 */
class PurchaseReturns extends PurchaseDocs
{
    protected string $table = 'purchase_returns';
    protected string $itemsTable = 'purchase_return_items';
    protected string $fk = 'purchase_return_id';
    protected string $noField = 'return_no';
    protected string $dateField = 'return_date';
    protected string $prefixKey = 'Solar.returnPrefix';
    protected string $uri = 'purchase-returns';
    protected string $title = 'Purchase Returns';

    /** Without ?purchase=ID, first choose the purchase (supplier invoice) to return against. */
    protected function beforeCreate(): ?string
    {
        if ((int) $this->request->getGet('purchase')) {
            return null;
        }
        $rows = db_connect()->table('purchases p')
            ->select('p.id, p.purchase_no, p.bill_no, p.bill_date, p.total, s.name AS supplier')
            ->join('suppliers s', 's.id = p.supplier_id')->orderBy('p.id', 'DESC')->limit(500)->get()->getResultArray();

        return $this->render('purchases/pick_purchase', ['purchases' => $rows]);
    }

    /** Lines of a purchase with what was bought, already returned and still returnable. */
    public static function returnable(int $purchaseId): array
    {
        $db       = db_connect();
        $returned = [];
        foreach ($db->table('purchase_return_items ri')->select('ri.item_id, SUM(ri.qty) AS q')
            ->join('purchase_returns r', 'r.id = ri.purchase_return_id')->where('r.purchase_id', $purchaseId)
            ->groupBy('ri.item_id')->get()->getResultArray() as $r) {
            $returned[$r['item_id']] = (float) $r['q'];
        }
        $out = [];
        foreach ($db->table('purchase_items l')->select('l.item_id, SUM(l.qty) AS qty, MAX(l.rate) AS rate, MAX(l.tax_percent) AS tax_percent, i.name, i.unit')
            ->join('items i', 'i.id = l.item_id')->where('l.purchase_id', $purchaseId)
            ->groupBy('l.item_id, i.name, i.unit')->orderBy('MIN(l.id)', '', false)->get()->getResultArray() as $l) {
            $done = $returned[$l['item_id']] ?? 0.0;
            $out[(int) $l['item_id']] = $l + ['returned' => $done, 'left' => max(0, round((float) $l['qty'] - $done, 2))];
        }

        return $out;
    }

    protected function prefill(): array
    {
        $pid  = (int) $this->request->getGet('purchase');
        $pur  = $this->findOr404('purchases', $pid);
        $rows = array_values(self::returnable($pid));

        return ['head' => [
            'supplier_id' => $pur['supplier_id'], 'location_id' => $pur['location_id'], 'purchase_id' => $pid,
            'purchase_no' => $pur['purchase_no'], 'bill_no' => $pur['bill_no'], 'bill_date' => $pur['bill_date'],
            'supplier_name' => db_connect()->table('suppliers')->select('name')->where('id', $pur['supplier_id'])->get()->getRow('name'),
            'location_name' => db_connect()->table('locations')->select('name')->where('id', $pur['location_id'])->get()->getRow('name'),
        ], 'rows' => $rows];
    }

    protected function checkHead(array $p): ?string
    {
        $pid = (int) ($p['purchase_id'] ?? 0);
        $pur = $pid ? db_connect()->table('purchases')->where('id', $pid)->get()->getRowArray() : null;
        if (! $pur) {
            return 'Choose the purchase (supplier invoice) to return against.';
        }
        if ((int) $pur['supplier_id'] !== (int) $p['supplier_id']) {
            return 'The supplier does not match the purchase.';
        }
        if (($p['doc_date'] ?? '') < $pur['bill_date']) {
            return 'Return date cannot be before the supplier invoice date (' . fdate($pur['bill_date']) . ').';
        }
        $left = self::returnable($pid);
        foreach ((array) ($p['items'] ?? []) as $r) {
            $q = round((float) ($r['qty'] ?? 0), 2);
            if ($q <= 0) {
                continue;
            }
            $l = $left[(int) ($r['item_id'] ?? 0)] ?? null;
            if (! $l) {
                return 'Only items on purchase ' . $pur['purchase_no'] . ' can be returned against it.';
            }
            if ($q > $l['left']) {
                return $l['name'] . ': only ' . qty($l['left']) . ' ' . $l['unit'] . ' can still be returned (bought ' . qty($l['qty']) . ', already returned ' . qty($l['returned']) . ').';
            }
        }

        return null;
    }

    /** Items, rate and GST from the purchase; only the posted quantity is used. */
    protected function rowsFor(array $p): array
    {
        $left = self::returnable((int) $p['purchase_id']);
        $rows = [];
        foreach ((array) ($p['items'] ?? []) as $r) {
            $id = (int) ($r['item_id'] ?? 0);
            if (isset($left[$id])) {
                $rows[] = ['item_id' => $id, 'qty' => $r['qty'] ?? 0, 'rate' => $left[$id]['rate'], 'tax_percent' => $left[$id]['tax_percent']];
            }
        }

        return $rows;
    }

    protected function extraHead(array $p): array
    {
        $pur = db_connect()->table('purchases')->select('location_id')->where('id', (int) $p['purchase_id'])->get()->getRowArray();

        return ['location_id' => (int) $pur['location_id'], 'purchase_id' => (int) $p['purchase_id'], 'reason' => ($p['notes'] ?? '') ?: null];
    }

    protected function afterStore(int $id, array $lines, array $p): void
    {
        $loc = (int) db_connect()->table('purchase_returns')->select('location_id')->where('id', $id)->get()->getRow('location_id');
        foreach ($lines as $l) {
            Stock::move((int) $l['item_id'], $loc, -(float) $l['qty'], 'purchase_return', 'purchase_return', $id, 'Returned to supplier');
        }
        Ledger::postPurchaseReturn($id);
    }

    protected function beforeDelete(array $doc): void
    {
        Stock::reverse('purchase_return', (int) $doc['id']);
        Ledger::unpost('purchase_return', (int) $doc['id']);
    }
}
