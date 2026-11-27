<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Stock lots (FIFO) on top of the stock ledger.
 *
 * Every stock-in movement creates a lot that remembers where the material came
 * from: purchase (goods received) no., supplier invoice no., PO no., supplier,
 * date and rate. Every stock-out takes from the oldest lots first (FIFO) and
 * records which lots it used (stock_lot_moves), so for any panel or inverter
 * you can see which PO / purchase invoice it came from and which dispatch,
 * project or return it went to.
 *
 * Called only from App\Libraries\Stock (move / reverse) and the stock-line
 * delete; never write the tables directly.
 */
class Lots
{
    private static ?bool $ready = null;

    /** Forget cached state (multi-company mode switches company). */
    public static function reset(): void
    {
        self::$ready = null;
    }

    /** False until "Database Updates" has created the lot tables: everything then falls back to the old behaviour. */
    public static function ready(): bool
    {
        return self::$ready ??= db_connect()->tableExists('stock_lot_moves');
    }

    /** A movement has been added: create a lot (qty > 0) or take from lots (qty < 0). */
    public static function onMove(int $movementId, int $itemId, int $locationId, float $qty, string $type, ?string $refType, ?int $refId, ?string $when = null): void
    {
        if (! self::ready()) {
            return;
        }
        if ($qty > 0) {
            self::create($movementId, $itemId, $locationId, $qty, $type, $refType, $refId, $when);
        } elseif ($qty < 0) {
            $prefer = $refType === 'purchase_return' && $refId
                ? (int) (db_connect()->table('purchase_returns')->select('purchase_id')->where('id', $refId)->get()->getRow()->purchase_id ?? 0)
                : 0;
            self::consume($movementId, $itemId, $locationId, -$qty, $prefer ?: null);
        }
    }

    /**
     * Movements are being deleted (document cancelled / deleted / wrong line):
     * give consumed quantities back to their lots and remove lots they created.
     * Refuses when a lot created by these movements has already been used.
     *
     * @param list<int> $movementIds
     */
    public static function undo(array $movementIds): void
    {
        $movementIds = array_values(array_filter(array_map('intval', $movementIds)));
        if (! $movementIds || ! self::ready()) {
            return;
        }
        $db   = db_connect();
        $lots = $db->table('stock_lots')->whereIn('movement_id', $movementIds)->get()->getResultArray();
        foreach ($lots as $lot) {
            if ((float) $lot['qty_left'] + 0.0001 < (float) $lot['qty_in']) {
                $used = $db->table('stock_lot_moves lm')->select('sm.ref_type, sm.ref_id')
                    ->join('stock_movements sm', 'sm.id = lm.movement_id')->where('lm.lot_id', $lot['id'])->get()->getResultArray();
                throw new RuntimeException(sprintf('Cannot remove: %s of this stock (lot %s) is already used in %s. Remove those first.',
                    qty((float) $lot['qty_in'] - (float) $lot['qty_left']), self::label($lot), implode(', ', array_unique(array_map([self::class, 'refLabel'], $used))) ?: 'another entry'));
            }
        }
        foreach ($db->table('stock_lot_moves')->whereIn('movement_id', $movementIds)->get()->getResultArray() as $m) {
            $db->query('UPDATE ' . $db->prefixTable('stock_lots') . ' SET qty_left = qty_left + ? WHERE id = ?', [$m['qty'], $m['lot_id']]);
        }
        $db->table('stock_lot_moves')->whereIn('movement_id', $movementIds)->delete();
        if ($lots) {
            $db->table('stock_lots')->whereIn('id', array_column($lots, 'id'))->delete();
        }
    }

    /** Rebuilds every lot from the stock ledger, in date order (used once when lots are switched on). */
    public static function rebuild(): int
    {
        self::$ready = null;
        $db = db_connect();
        $db->table('stock_lot_moves')->truncate();
        $db->table('stock_lots')->truncate();
        $rows = $db->table('stock_movements')->orderBy('created_at')->orderBy('id')->get()->getResultArray();
        foreach ($rows as $m) {
            self::onMove((int) $m['id'], (int) $m['item_id'], (int) $m['location_id'], (float) $m['qty'], (string) $m['type'], $m['ref_type'], $m['ref_id'] !== null ? (int) $m['ref_id'] : null, $m['created_at']);
        }

        return count($rows);
    }

    /** FIFO value of stock on hand at the start of $date (Y-m-d), or now. */
    public static function value(?string $before = null): float
    {
        $db = db_connect();
        if (! self::ready()) { // old method: quantity × item purchase price
            $b = $db->table('stock_movements m')->select('SUM(m.qty * i.purchase_price) AS v', false)->join('items i', 'i.id = m.item_id');
            if ($before !== null) {
                $b->where('m.created_at <', $before . ' 00:00:00');
            }

            return round((float) ($b->get()->getRowArray()['v'] ?? 0), 2);
        }
        if ($before === null) {
            $r = $db->table('stock_lots')->select('SUM(qty_left * rate) AS v', false)->get()->getRowArray();

            return round((float) ($r['v'] ?? 0), 2);
        }
        $at   = $before . ' 00:00:00';
        $in   = $db->table('stock_lots')->select('SUM(qty_in * rate) AS v', false)->where('created_at <', $at)->get()->getRowArray();
        $out  = $db->table('stock_lot_moves lm')->select('SUM(lm.qty * l.rate) AS v', false)->join('stock_lots l', 'l.id = lm.lot_id')
            ->where('lm.created_at <', $at)->get()->getRowArray();

        return round((float) ($in['v'] ?? 0) - (float) ($out['v'] ?? 0), 2);
    }

    /** "PUR/2026-27/0004 · bill AB-123 · PO/2026-27/0002" or "Opening stock 05-11-2026". */
    public static function label(array $lot): string
    {
        if ($lot['source'] === 'purchase') {
            return trim(($lot['purchase_no'] ?? '') . ($lot['bill_no'] ? ' · bill ' . $lot['bill_no'] : '') . ($lot['po_no'] ? ' · ' . $lot['po_no'] : ''));
        }

        return ucfirst($lot['source']) . ' ' . date('d-m-Y', strtotime((string) $lot['received_on']));
    }

    public static function refLabel(array $r): string
    {
        $db = db_connect();

        return match ($r['ref_type']) {
            'dispatch'        => (string) ($db->table('dispatches')->select('dispatch_no')->where('id', $r['ref_id'])->get()->getRow()->dispatch_no ?? 'a dispatch'),
            'purchase_return' => (string) ($db->table('purchase_returns')->select('return_no')->where('id', $r['ref_id'])->get()->getRow()->return_no ?? 'a purchase return'),
            default           => 'a stock adjustment',
        };
    }

    /**
     * Lots of one item (newest first) with where each was used.
     *
     * @return list<array<string, mixed>> each lot + 'location', 'supplier', 'used' => list of [qty, ref_type, ref_id, doc_no, project_id, project_no, date]
     */
    public static function forItem(int $itemId, bool $openOnly = false): array
    {
        if (! self::ready()) {
            return [];
        }
        $db = db_connect();
        $b  = $db->table('stock_lots l')->select('l.*, loc.name AS location, s.name AS supplier')
            ->join('locations loc', 'loc.id = l.location_id', 'left')->join('suppliers s', 's.id = l.supplier_id', 'left')
            ->where('l.item_id', $itemId);
        if ($openOnly) {
            $b->where('l.qty_left >', 0);
        }
        $lots = $b->orderBy('l.received_on', 'DESC')->orderBy('l.id', 'DESC')->get()->getResultArray();
        if (! $lots) {
            return [];
        }
        $used = [];
        foreach (self::uses('lm.lot_id', array_column($lots, 'id')) as $u) {
            $used[(int) $u['lot_id']][] = $u;
        }
        foreach ($lots as &$l) {
            $l['used'] = $used[(int) $l['id']] ?? [];
        }

        return $lots;
    }

    /**
     * Which lots a document took its stock from (dispatch / purchase return / adjustment).
     *
     * @return array<int, list<array<string, mixed>>> item_id => [lot + qty taken]
     */
    public static function takenBy(string $refType, int $refId): array
    {
        if (! self::ready()) {
            return [];
        }
        $out = [];
        foreach (db_connect()->table('stock_lot_moves lm')->select('lm.qty AS taken, l.*')
            ->join('stock_lots l', 'l.id = lm.lot_id')->join('stock_movements sm', 'sm.id = lm.movement_id')
            ->where(['sm.ref_type' => $refType, 'sm.ref_id' => $refId])->orderBy('l.received_on')->orderBy('l.id')->get()->getResultArray() as $r) {
            $out[(int) $r['item_id']][] = $r;
        }

        return $out;
    }

    /**
     * Where the stock of one purchase went: per item the lot (in / left) and its uses.
     *
     * @return array<int, array<string, mixed>> item_id => lot with 'used'
     */
    public static function forPurchase(int $purchaseId): array
    {
        if (! self::ready()) {
            return [];
        }
        $db   = db_connect();
        $lots = $db->table('stock_lots l')->select('l.*, loc.name AS location')->join('locations loc', 'loc.id = l.location_id', 'left')
            ->where('l.purchase_id', $purchaseId)->orderBy('l.id')->get()->getResultArray();
        if (! $lots) {
            return [];
        }
        $used = [];
        foreach (self::uses('lm.lot_id', array_column($lots, 'id')) as $u) {
            $used[(int) $u['lot_id']][] = $u;
        }
        $out = [];
        foreach ($lots as $l) {
            $l['used'] = $used[(int) $l['id']] ?? [];
            $out[(int) $l['item_id']][] = $l;
        }

        return $out;
    }

    /**
     * Open lots of all items with their age (for the stock value / ageing report).
     *
     * @return list<array<string, mixed>>
     */
    public static function open(?int $locationId = null, ?int $categoryId = null): array
    {
        if (! self::ready()) {
            return [];
        }
        $b = db_connect()->table('stock_lots l')->select('l.*, i.name AS item, i.unit, c.name AS category, loc.name AS location, s.name AS supplier, DATEDIFF(CURDATE(), l.received_on) AS age', false)
            ->join('items i', 'i.id = l.item_id')->join('item_categories c', 'c.id = i.category_id', 'left')
            ->join('locations loc', 'loc.id = l.location_id', 'left')->join('suppliers s', 's.id = l.supplier_id', 'left')
            ->where('l.qty_left >', 0);
        if ($locationId) {
            $b->where('l.location_id', $locationId);
        }
        if ($categoryId) {
            $b->where('i.category_id', $categoryId);
        }

        return $b->orderBy('i.name')->orderBy('l.received_on')->orderBy('l.id')->get()->getResultArray();
    }

    /** @return array<int, float> item_id => FIFO value of stock on hand (optionally one store) */
    public static function valueByItem(?int $locationId = null): array
    {
        if (! self::ready()) {
            return [];
        }
        $b = db_connect()->table('stock_lots')->select('item_id, SUM(qty_left * rate) AS v', false)->where('qty_left >', 0);
        if ($locationId) {
            $b->where('location_id', $locationId);
        }
        $out = [];
        foreach ($b->groupBy('item_id')->get()->getResultArray() as $r) {
            $out[(int) $r['item_id']] = (float) $r['v'];
        }

        return $out;
    }

    /** Uses of lots with the document and project they went to. */
    private static function uses(string $col, array $ids): array
    {
        return db_connect()->table('stock_lot_moves lm')
            ->select('lm.lot_id, lm.qty, lm.created_at, sm.ref_type, sm.ref_id, sm.type, sm.note, d.dispatch_no, d.project_id, pr.project_no, c.name AS customer, r.return_no')
            ->join('stock_movements sm', 'sm.id = lm.movement_id')
            ->join('dispatches d', "d.id = sm.ref_id AND sm.ref_type = 'dispatch'", 'left')
            ->join('projects pr', 'pr.id = d.project_id', 'left')
            ->join('customers c', 'c.id = pr.customer_id', 'left')
            ->join('purchase_returns r', "r.id = sm.ref_id AND sm.ref_type = 'purchase_return'", 'left')
            ->whereIn($col, $ids)->orderBy('lm.created_at')->orderBy('lm.id')->get()->getResultArray();
    }

    // ------------------------------------------------------------------

    private static function create(int $movementId, int $itemId, int $locationId, float $qty, string $type, ?string $refType, ?int $refId, ?string $when): void
    {
        $db  = db_connect();
        $row = ['source' => in_array($type, ['opening', 'adjustment'], true) ? $type : 'adjustment', 'rate' => null,
            'purchase_id' => null, 'purchase_order_id' => null, 'supplier_id' => null, 'purchase_no' => null, 'bill_no' => null, 'po_no' => null,
            'received_on' => substr($when ?: date('Y-m-d H:i:s'), 0, 10)];
        if ($refType === 'purchase' && $refId) {
            $p = $db->table('purchases p')->select('p.*, po.po_no')->join('purchase_orders po', 'po.id = p.purchase_order_id', 'left')->where('p.id', $refId)->get()->getRowArray();
            if ($p) {
                $line = $db->table('purchase_items')->select('SUM(qty * rate) AS v, SUM(qty) AS q', false)->where(['purchase_id' => $refId, 'item_id' => $itemId])->get()->getRowArray();
                $row  = ['source' => 'purchase', 'purchase_id' => $p['id'], 'purchase_order_id' => $p['purchase_order_id'], 'supplier_id' => $p['supplier_id'],
                    'purchase_no' => $p['purchase_no'], 'bill_no' => $p['bill_no'], 'po_no' => $p['po_no'], 'received_on' => $p['bill_date'],
                    'rate' => (float) ($line['q'] ?? 0) > 0 ? round((float) $line['v'] / (float) $line['q'], 2) : null];
            }
        }
        if ($row['rate'] === null) {
            $row['rate'] = (float) ($db->table('items')->select('purchase_price')->where('id', $itemId)->get()->getRow()->purchase_price ?? 0);
        }
        $db->table('stock_lots')->insert($row + [
            'item_id' => $itemId, 'location_id' => $locationId, 'movement_id' => $movementId,
            'qty_in' => $qty, 'qty_left' => $qty, 'created_at' => $when ?: date('Y-m-d H:i:s'),
        ]);
    }

    /** Takes $qty from the oldest lots (FIFO); lots of $preferPurchaseId first (returns go back from the same bill). */
    private static function consume(int $movementId, int $itemId, int $locationId, float $qty, ?int $preferPurchaseId): void
    {
        $db = db_connect();
        $b  = $db->table('stock_lots')->where(['item_id' => $itemId, 'location_id' => $locationId])->where('qty_left >', 0);
        if ($preferPurchaseId) {
            $b->orderBy('purchase_id = ' . (int) $preferPurchaseId, 'DESC', false);
        }
        $lots = $b->orderBy('received_on')->orderBy('id')->get()->getResultArray();
        $now  = $db->table('stock_movements')->select('created_at')->where('id', $movementId)->get()->getRow()->created_at ?? date('Y-m-d H:i:s');
        foreach ($lots as $lot) {
            if ($qty <= 0.0001) {
                break;
            }
            $take = min($qty, (float) $lot['qty_left']);
            $db->table('stock_lot_moves')->insert(['lot_id' => $lot['id'], 'movement_id' => $movementId, 'qty' => $take, 'created_at' => $now]);
            $db->query('UPDATE ' . $db->prefixTable('stock_lots') . ' SET qty_left = qty_left - ? WHERE id = ?', [$take, $lot['id']]);
            $qty -= $take;
        }
        // Anything left over had no lot (stock taken below zero with the check switched off): not traced.
    }
}
