<?php

namespace App\Controllers\Admin;

use App\Libraries\Stock;

class Items extends MasterController
{
    protected string $table = 'items';
    protected string $title = 'Items';
    protected string $uri = 'items';
    protected string $viewPermission = 'inventory.view';
    protected string $managePermission = 'inventory.manage';
    protected array $listColumns = ['sku', 'name', 'installation_type_id', 'category_id', 'unit', 'hsn', 'tax_percent', 'purchase_price', 'sale_price', 'min_stock', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[191]', 'unit' => 'required', 'category_id' => 'required'];

    protected function fieldDefs(): array
    {
        $cats = [];
        foreach (db_connect()->table('item_categories c')->select('c.id, c.name, t.name AS type')
            ->join('installation_types t', 't.id = c.installation_type_id', 'left')
            ->where('c.is_active', 1)->orderBy('t.name')->orderBy('c.name')->get()->getResultArray() as $c) {
            $cats[$c['id']] = $c['name'] . ($c['type'] ? ' (' . $c['type'] . ')' : '');
        }

        return [
            'installation_type_id' => ['Installation type (empty = common to all)', 'select', options('installation_types')],
            'category_id'          => ['Category *', 'select', $cats],
            'name'                 => ['Item name *', 'text'],
            'sku'                  => ['Item code / SKU', 'text'],
            'unit'                 => ['Unit *', 'select', ['Nos', 'Mtr', 'Set', 'Kg', 'Box', 'Pair', 'Lot', 'kW', 'Pieces']],
            'hsn'                  => ['HSN / SAC', 'text'],
            'tax_percent'          => ['GST %', 'number'],
            'purchase_price'       => ['Purchase price (₹, before GST)', 'number'],
            'sale_price'           => ['Sale price (₹, before GST)', 'number'],
            'min_stock'            => ['Minimum stock (low-stock alert)', 'number'],
            'is_active'            => ['Active', 'bool'],
        ];
    }

    /** Opening stock when a new item is created. */
    protected function extraFields(): array
    {
        return [
            'opening_location_id' => ['Opening stock – location', 'select', options('locations')],
            'opening_qty'         => ['Opening stock – quantity (leave empty if none)', 'number'],
        ];
    }

    public function save($id = null)
    {
        $qty = round((float) $this->request->getPost('opening_qty'), 2);
        $loc = (int) $this->request->getPost('opening_location_id');
        if (! $id && $qty > 0 && ! $loc) {
            return $this->back('Choose the location for the opening stock.');
        }

        $db  = db_connect();
        $res = parent::save($id);
        if (! $id && $qty > 0 && ! ($this->validator && $this->validator->getErrors())) {
            $newId = (int) $db->table('items')->selectMax('id')->get()->getRow()->id;
            Stock::move($newId, $loc, $qty, 'opening', 'adjustment', null, 'Opening stock (item created)');
        }

        return $res;
    }

    public function index()
    {
        $this->requirePermission($this->viewPermission, $this->managePermission);
        $db = db_connect();
        $b  = $db->table('items')->where('deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('name', $q)->orLike('sku', $q)->groupEnd();
        }
        if ($t = $this->request->getGet('type')) {
            $b->where('installation_type_id', (int) $t);
        }
        if ($c = $this->request->getGet('category')) {
            $b->where('category_id', (int) $c);
        }
        $rows  = $b->orderBy('name')->get()->getResultArray();
        $bal   = Stock::balances();
        $locId = (int) $this->request->getGet('location');
        foreach ($rows as &$r) {
            $r['stock'] = $locId ? ($bal[$r['id']][$locId] ?? 0) : array_sum($bal[$r['id']] ?? []);
        }
        unset($r);

        return $this->render('items/index', [
            'rows' => $rows, 'fields' => $this->fieldDefs(), 'types' => options('installation_types'),
            'locations' => options('locations', 'name', false), 'locId' => $locId,
            'canManage' => can('inventory.manage'),
        ]);
    }

    /** Admin ("Delete entries"): hide an item that has no stock and is not in a kit. Its history stays. */
    public function delete($id)
    {
        $this->requirePermission('entries.delete');
        $item = $this->findOr404('items', $id);
        $db   = db_connect();
        $have = array_sum(Stock::balances()[$item['id']] ?? []) + (float) (Stock::inTransit()[$item['id']] ?? 0);
        if (abs($have) > 0.0001) {
            return $this->back($item['name'] . ' still has ' . qty($have) . ' ' . $item['unit'] . ' in stock / in transit. Bring it to zero (Stock Adjustment) before deleting.');
        }
        $kits = $db->table('kit_items ki')->select('k.name')->join('kits k', 'k.id = ki.kit_id')->where('ki.item_id', $item['id'])->get()->getResultArray();
        if ($kits) {
            return $this->back($item['name'] . ' is in kit(s): ' . implode(', ', array_column($kits, 'name')) . '. Remove it from those kits first.');
        }
        $db->table('items')->where('id', $item['id'])->update(['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0]);
        \App\Libraries\Workflow::log('item', (int) $item['id'], 'deleted', 'Item ' . $item['name'] . ' deleted');

        return redirect()->to('admin/items')->with('message', 'Item ' . $item['name'] . ' deleted. Past invoices, purchases and stock history still show it.');
    }
}
