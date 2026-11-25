<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Stock ledger. Balance per item & location = SUM(stock_movements.qty).
 * Material that has left the store but is not yet delivered is "in transit"
 * (dispatch_items of dispatches with status = dispatched).
 */
class Stock
{
    public static function balance(int $itemId, int $locationId): float
    {
        $row = db_connect()->table('stock_movements')
            ->selectSum('qty', 'q')
            ->where(['item_id' => $itemId, 'location_id' => $locationId])
            ->get()->getRowArray();

        return round((float) ($row['q'] ?? 0), 2);
    }

    /** [item_id => [location_id => qty]] */
    public static function balances(): array
    {
        $out  = [];
        $rows = db_connect()->table('stock_movements')
            ->select('item_id, location_id, SUM(qty) AS q')
            ->groupBy(['item_id', 'location_id'])
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $out[(int) $r['item_id']][(int) $r['location_id']] = round((float) $r['q'], 2);
        }

        return $out;
    }

    /** [item_id => qty] currently in transit. */
    public static function inTransit(): array
    {
        $out  = [];
        $rows = db_connect()->table('dispatch_items di')
            ->select('di.item_id, SUM(di.qty) AS q')
            ->join('dispatches d', 'd.id = di.dispatch_id')
            ->where('d.status', 'dispatched')
            ->groupBy('di.item_id')
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $out[(int) $r['item_id']] = round((float) $r['q'], 2);
        }

        return $out;
    }

    /**
     * Records a movement. Negative $qty = stock out. With $checkStock, refuses
     * to take out more than is available.
     */
    public static function move(int $itemId, int $locationId, float $qty, string $type, ?string $refType = null, ?int $refId = null, string $note = '', bool $checkStock = true): void
    {
        if ($qty < 0 && $checkStock) {
            $have = self::balance($itemId, $locationId);
            if ($have + $qty < -0.0001) {
                $item = db_connect()->table('items')->select('name')->where('id', $itemId)->get()->getRowArray();

                throw new RuntimeException(sprintf(
                    'Not enough stock for "%s": available %s, needed %s.',
                    $item['name'] ?? ('#' . $itemId), $have, abs($qty)
                ));
            }
        }

        $db = db_connect();
        $db->table('stock_movements')->insert([
            'item_id'     => $itemId,
            'location_id' => $locationId,
            'qty'         => $qty,
            'type'        => $type,
            'ref_type'    => $refType,
            'ref_id'      => $refId,
            'note'        => $note,
            'created_by'  => auth()->id(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        // FIFO lots: where this stock came from / which lots it was taken from.
        Lots::onMove((int) $db->insertID(), $itemId, $locationId, $qty, $type, $refType, $refId);
    }

    /** Deletes the movements of a document (used when it is cancelled/deleted). */
    public static function reverse(string $refType, int $refId): void
    {
        $db  = db_connect();
        $ids = array_column($db->table('stock_movements')->select('id')->where(['ref_type' => $refType, 'ref_id' => $refId])->get()->getResultArray(), 'id');
        Lots::undo($ids); // refuses if stock from this document is already used elsewhere
        $db->table('stock_movements')->where(['ref_type' => $refType, 'ref_id' => $refId])->delete();
    }
}
