<?php

namespace App\Controllers\Admin;

use App\Libraries\Stock as StockLib;

class Stock extends AdminController
{
    /** Stock balance by location, plus material in transit. */
    public function index()
    {
        $this->requirePermission('inventory.view');
        $db  = db_connect();
        $b   = $db->table('items i')->select('i.*, c.name AS category, t.name AS type')
            ->join('item_categories c', 'c.id = i.category_id', 'left')
            ->join('installation_types t', 't.id = i.installation_type_id', 'left')
            ->where('i.deleted_at', null);
        if ($cat = $this->request->getGet('category')) {
            $b->where('i.category_id', (int) $cat);
        }

        $allLocations = options('locations', 'name', false);
        $locId        = (int) $this->request->getGet('location');
        $locations    = $locId && isset($allLocations[$locId]) ? [$locId => $allLocations[$locId]] : $allLocations;

        return $this->render('stock/index', [
            'items'      => $b->orderBy('c.name')->orderBy('i.name')->get()->getResultArray(),
            'locations'  => $locations,
            'allLocations' => $allLocations,
            'locId'      => $locId,
            'inStockOnly' => (bool) $this->request->getGet('instock'),
            'balances'   => StockLib::balances(),
            'transit'    => StockLib::inTransit(),
            'categories' => options('item_categories'),
        ]);
    }

    public function ledger($itemId)
    {
        $this->requirePermission('inventory.view');
        $item = $this->findOr404('items', $itemId);
        $rows = db_connect()->table('stock_movements m')->select('m.*, l.name AS location')
            ->join('locations l', 'l.id = m.location_id')->where('m.item_id', $item['id'])
            ->orderBy('m.id')->get()->getResultArray();

        return $this->render('stock/ledger', ['item' => $item, 'rows' => $rows]);
    }

    /** Admin ("Delete entries"): remove a wrong opening-stock / adjustment line. */
    public function deleteMovement($id)
    {
        $this->requirePermission('entries.delete');
        $db = db_connect();
        $m  = $db->table('stock_movements m')->select('m.*, i.name, i.unit')->join('items i', 'i.id = m.item_id')->where('m.id', (int) $id)->get()->getRowArray();
        if (! $m) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (! in_array($m['type'], ['opening', 'adjustment'], true)) {
            return $this->back('Only opening stock and adjustment lines can be deleted here. Purchases, returns and dispatches are removed by deleting that document.');
        }
        $have = StockLib::balance((int) $m['item_id'], (int) $m['location_id']);
        if ((float) $m['qty'] > 0 && $have - (float) $m['qty'] < -0.0001) {
            return $this->back(sprintf('Cannot delete: %s of this stock is already used (in store now %s). Stock would go negative.', $m['name'], qty($have)));
        }
        $db->table('stock_movements')->where('id', $m['id'])->delete();
        \App\Libraries\Workflow::log('item', (int) $m['item_id'], 'stock_entry_deleted', sprintf('%s line of %s %s (%s) deleted', ucfirst($m['type']), qty($m['qty']), $m['unit'], $m['name']));

        return redirect()->to('admin/stock/item/' . $m['item_id'])->with('message', ucfirst($m['type']) . ' line of ' . qty($m['qty']) . ' ' . $m['unit'] . ' deleted.');
    }

    public function adjust()
    {
        $this->requirePermission('inventory.manage');

        return $this->render('stock/adjust', [
            'items'     => db_connect()->table('items')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray(),
            'locations' => options('locations'),
            'balances'  => StockLib::balances(),
        ]);
    }

    /** Opening stock / physical-count corrections (+ add, − remove). */
    public function saveAdjust()
    {
        $this->requirePermission('inventory.manage');
        $loc  = (int) $this->request->getPost('location_id');
        $type = $this->request->getPost('type') === 'opening' ? 'opening' : 'adjustment';
        $note = trim((string) $this->request->getPost('note'));
        if (! $loc || $note === '') {
            return $this->back('Choose the location and write a reason.');
        }
        $db = db_connect();
        $db->transException(true)->transStart();
        try {
            $n = 0;
            foreach ((array) $this->request->getPost('items') as $r) {
                $q = round((float) ($r['qty'] ?? 0), 2);
                if (empty($r['item_id']) || $q == 0.0) {
                    continue;
                }
                StockLib::move((int) $r['item_id'], $loc, $q, $type, 'adjustment', null, $note);
                $n++;
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back($e->getMessage());
        }

        return redirect()->to('admin/stock')->with('message', $n . ' stock line(s) adjusted.');
    }
}
