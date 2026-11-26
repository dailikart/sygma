<?php

namespace App\Libraries;

/**
 * True profit of a project:
 *   revenue  = invoice value before GST (GST is collected for the government, not income)
 *   material = items dispatched to the site × average purchase rate (from purchases;
 *              the item's purchase price when it was never purchased)
 *   expenses = expenses linked to the project (Accounts → Expenses → project)
 *   EB       = EB / meter charges when the company pays them (amount on the EB / Net Meter card)
 *   profit   = revenue − material − expenses − EB
 */
class ProjectProfit
{
    /** @return array<int, float> item_id => average purchase rate */
    public static function itemRates(): array
    {
        $db    = db_connect();
        $rates = array_map('floatval', array_column($db->table('items')->select('id, purchase_price')->get()->getResultArray(), 'purchase_price', 'id'));
        foreach ($db->table('purchase_items')->select('item_id, SUM(qty * rate) AS v, SUM(qty) AS q')->groupBy('item_id')->get()->getResultArray() as $r) {
            if ((float) $r['q'] > 0) {
                $rates[(int) $r['item_id']] = round((float) $r['v'] / (float) $r['q'], 2);
            }
        }

        return $rates;
    }

    /**
     * @param list<int>|null $projectIds null = all projects
     *
     * @return array<int, array{revenue: float, material: float, expenses: float, eb: float, cost: float, profit: float, margin: ?float}>
     */
    public static function forProjects(?array $projectIds = null): array
    {
        $db = db_connect();
        if ($projectIds === []) {
            return [];
        }
        $rates = self::itemRates();
        $b     = $db->table('projects p')->select('p.id, i.subtotal, i.eb_charges_by, i.status AS invoice_status, s.data AS eb_data')
            ->join('invoices i', 'i.id = p.invoice_id', 'left')
            ->join('project_stages s', "s.project_id = p.id AND s.stage = 'eb_process'", 'left');
        if ($projectIds !== null) {
            $b->whereIn('p.id', $projectIds);
        }
        $out = [];
        foreach ($b->get()->getResultArray() as $p) {
            $eb  = 0.0;
            if ($p['eb_charges_by'] === 'company') {
                $d  = json_decode((string) $p['eb_data'], true) ?: [];
                $eb = (float) str_replace(',', '', (string) ($d['eb_charges_amount'] ?? 0));
            }
            $out[(int) $p['id']] = ['revenue' => $p['invoice_status'] === 'cancelled' ? 0.0 : (float) $p['subtotal'], 'material' => 0.0, 'expenses' => 0.0, 'eb' => $eb];
        }
        if (! $out) {
            return [];
        }
        $ids = array_keys($out);
        // Material at the real cost of the stock lots used (FIFO); anything not traced to a lot at the average rate.
        $lotted = [];
        foreach (! \App\Libraries\Lots::ready() ? [] : $db->table('stock_lot_moves lm')->select('d.project_id, l.item_id, SUM(lm.qty) AS q, SUM(lm.qty * l.rate) AS v', false)
            ->join('stock_lots l', 'l.id = lm.lot_id')->join('stock_movements sm', 'sm.id = lm.movement_id')
            ->join('dispatches d', "d.id = sm.ref_id AND sm.ref_type = 'dispatch'")->whereIn('d.project_id', $ids)
            ->groupBy('d.project_id, l.item_id')->get()->getResultArray() as $r) {
            $lotted[(int) $r['project_id']][(int) $r['item_id']] = [(float) $r['q'], (float) $r['v']];
        }
        foreach ($db->table('dispatch_items di')->select('d.project_id, di.item_id, SUM(di.qty) AS q')
            ->join('dispatches d', 'd.id = di.dispatch_id')->whereIn('d.status', ['dispatched', 'delivered'])
            ->whereIn('d.project_id', $ids)->groupBy('d.project_id, di.item_id')->get()->getResultArray() as $r) {
            [$lq, $lv] = $lotted[(int) $r['project_id']][(int) $r['item_id']] ?? [0.0, 0.0];
            $out[(int) $r['project_id']]['material'] += $lv + max(0, (float) $r['q'] - $lq) * ($rates[(int) $r['item_id']] ?? 0);
        }
        foreach ($db->table('expenses')->select('project_id, SUM(amount) AS a')->whereIn('project_id', $ids)->groupBy('project_id')->get()->getResultArray() as $r) {
            $out[(int) $r['project_id']]['expenses'] = (float) $r['a'];
        }
        foreach ($out as &$o) {
            $o['material'] = round($o['material'], 2);
            $o['cost']     = round($o['material'] + $o['expenses'] + $o['eb'], 2);
            $o['profit']   = round($o['revenue'] - $o['cost'], 2);
            $o['margin']   = $o['revenue'] > 0 ? round($o['profit'] / $o['revenue'] * 100, 1) : null;
        }

        return $out;
    }
}
