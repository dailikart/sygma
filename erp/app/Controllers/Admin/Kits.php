<?php

namespace App\Controllers\Admin;

/**
 * Item groups (kits): the items needed for one system size, e.g. "3 kW On-grid".
 * The dispatch list for a project is generated from its kit.
 */
class Kits extends AdminController
{
    public function index()
    {
        $this->requirePermission('inventory.manage', 'inventory.view');
        $rows = db_connect()->table('kits k')
            ->select('k.*, t.name AS type, (SELECT COUNT(*) FROM kit_items ki WHERE ki.kit_id = k.id) AS line_count')
            ->join('installation_types t', 't.id = k.installation_type_id', 'left')
            ->orderBy('t.name')->orderBy('k.capacity_kw')->get()->getResultArray();

        return $this->render('kits/index', ['rows' => $rows]);
    }

    public function form($id = null)
    {
        $this->requirePermission('inventory.manage');
        $kit = $id ? $this->findOr404('kits', $id) : [];

        return $this->render('kits/form', [
            'kit'   => $kit,
            'lines' => $id ? db_connect()->table('kit_items')->where('kit_id', $id)->get()->getResultArray() : [],
            'items' => db_connect()->table('items i')->select('i.id, i.name, i.sku, i.unit, c.name AS category')
                ->join('item_categories c', 'c.id = i.category_id', 'left')
                ->where(['i.is_active' => 1, 'i.deleted_at' => null])->orderBy('c.name')->orderBy('i.name')->get()->getResultArray(),
            'types' => options('installation_types'),
        ]);
    }

    public function save($id = null)
    {
        $this->requirePermission('inventory.manage');
        if (! $this->validate(['name' => 'required|max_length[100]', 'capacity_kw' => 'required|decimal|greater_than[0]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p     = $this->request->getPost();
        $lines = [];
        foreach ((array) ($p['items'] ?? []) as $r) {
            if (! empty($r['item_id']) && (float) ($r['qty'] ?? 0) > 0) {
                $lines[(int) $r['item_id']] = ($lines[(int) $r['item_id']] ?? 0) + (float) $r['qty'];
            }
        }
        if (! $lines) {
            return $this->back('Add at least one item with a quantity.');
        }

        $db   = db_connect();
        $now  = date('Y-m-d H:i:s');
        $data = [
            'name' => trim($p['name']), 'installation_type_id' => ($p['installation_type_id'] ?? '') ?: null,
            'capacity_kw' => (float) $p['capacity_kw'], 'description' => ($p['description'] ?? '') ?: null,
            'is_active' => empty($p['is_active']) ? 0 : 1,
        ];
        $db->transStart();
        if ($id) {
            $this->findOr404('kits', $id);
            $db->table('kits')->where('id', (int) $id)->update($data + ['updated_at' => $now]);
            $db->table('kit_items')->where('kit_id', (int) $id)->delete();
        } else {
            $db->table('kits')->insert($data + ['created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
            $id = $db->insertID();
        }
        foreach ($lines as $itemId => $qty) {
            $db->table('kit_items')->insert(['kit_id' => $id, 'item_id' => $itemId, 'qty' => $qty]);
        }
        $db->transComplete();

        return redirect()->to('admin/kits')->with('message', 'Item group saved.');
    }
}
