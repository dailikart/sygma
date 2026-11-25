<?php

namespace App\Controllers\Admin;

use App\Libraries\Numbering;
use App\Libraries\Pdf;
use App\Libraries\Tax;

/**
 * Shared code for Purchase Orders, Purchases (goods received → stock in) and
 * Purchase Returns (debit note → stock out).
 */
abstract class PurchaseDocs extends AdminController
{
    protected string $table;       // purchase_orders | purchases | purchase_returns
    protected string $itemsTable;  // *_items
    protected string $fk;          // FK column in items table
    protected string $noField;     // po_no | purchase_no | return_no
    protected string $dateField;   // po_date | bill_date | return_date
    protected string $prefixKey;   // Solar.poPrefix …
    protected string $uri;
    protected string $title;
    protected bool $hasLocation = true;

    public function index()
    {
        $this->requirePermission('purchases.view');
        [$from, $to] = fy_range();
        $b = db_connect()->table($this->table . ' d')->select('d.*, s.name AS supplier')
            ->join('suppliers s', 's.id = d.supplier_id')
            ->where('d.' . $this->dateField . ' >=', $from)->where('d.' . $this->dateField . ' <=', $to);
        if ($sid = $this->request->getGet('supplier')) {
            $b->where('d.supplier_id', (int) $sid);
        }

        return $this->render('purchases/index', [
            'rows' => $b->orderBy('d.id', 'DESC')->get()->getResultArray(), 'from' => $from, 'to' => $to,
            'c' => $this->meta(), 'suppliers' => options('suppliers'),
        ]);
    }

    public function create()
    {
        $this->requirePermission('purchases.manage');
        if ($step = $this->beforeCreate()) {
            return $step;
        }

        return $this->render('purchases/form', [
            'c'         => $this->meta(),
            'suppliers' => db_connect()->table('suppliers')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray(),
            'locations' => options('locations'),
            'items'     => db_connect()->table('items')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray(),
        ] + $this->prefill());
    }

    /** A page to show instead of the form (e.g. choose the purchase to return). */
    protected function beforeCreate(): ?string
    {
        return null;
    }

    /** Prefilled header + lines (from a PO / purchase). */
    protected function prefill(): array
    {
        return ['head' => [], 'rows' => []];
    }

    public function store()
    {
        $this->requirePermission('purchases.manage');
        $rules = ['supplier_id' => 'required|is_natural_no_zero', 'doc_date' => 'required|valid_date[Y-m-d]'];
        if ($this->hasLocation) {
            $rules['location_id'] = 'required|is_natural_no_zero';
        }
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p        = $this->request->getPost();
        $supplier = $this->findOr404('suppliers', $p['supplier_id']);
        if ($err = $this->checkHead($p)) {
            return $this->back($err);
        }
        [$lines, $sub, $tax, $total] = Tax::lines($this->rowsFor($p));
        if (! $lines) {
            return $this->back('Add at least one item line.');
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transException(true)->transStart();
        try {
            $head = [
                $this->noField   => Numbering::next($this->table, $this->noField, (string) setting($this->prefixKey), $p['doc_date']),
                'supplier_id'    => $supplier['id'],
                $this->dateField => $p['doc_date'],
                'gst_type'       => Tax::gstType($supplier['state']),
                'subtotal'       => $sub, 'tax_total' => $tax, 'total' => $total,
                'created_by'     => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
            ] + $this->extraHead($p);
            $db->table($this->table)->insert($head);
            $id = (int) $db->insertID();
            foreach ($lines as $l) {
                $db->table($this->itemsTable)->insert([
                    $this->fk => $id, 'item_id' => (int) $l['item_id'], 'qty' => $l['qty'], 'rate' => $l['rate'],
                    'tax_percent' => $l['tax_percent'], 'tax_amount' => $l['tax_amount'], 'amount' => $l['amount'],
                ]);
            }
            $this->afterStore($id, $lines, $p);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back($e->getMessage());
        }

        return redirect()->to('admin/' . $this->uri . '/' . $id)->with('message', $this->meta()['single'] . ' saved.');
    }

    protected function extraHead(array $p): array
    {
        return [];
    }

    /** Lines to save (a return takes items, rates and GST from its purchase). */
    protected function rowsFor(array $p): array
    {
        return $this->postedRows();
    }

    /** Extra checks on the posted header; return an error message or null. */
    protected function checkHead(array $p): ?string
    {
        return null;
    }

    protected function afterStore(int $id, array $lines, array $p): void
    {
    }

    public function show($id)
    {
        $this->requirePermission('purchases.view');

        $data = $this->data((int) $id);
        if (($data['c']['table'] ?? '') === 'purchases') {
            $data['lotsOf'] = \App\Libraries\Lots::forPurchase((int) $id);
        } elseif (($data['c']['table'] ?? '') === 'purchase_returns') {
            $data['takenFrom'] = \App\Libraries\Lots::takenBy('purchase_return', (int) $id);
        }

        return $this->render('purchases/show', $data);
    }

    public function pdf($id)
    {
        $this->requirePermission('purchases.view');
        $d = $this->data((int) $id);
        Pdf::stream('pdf/purchase', $d, str_replace('/', '-', $d['doc'][$this->noField]));
    }

    protected function data(int $id): array
    {
        $doc = $this->findOr404($this->table, $id);
        $db  = db_connect();

        return [
            'c'        => $this->meta(),
            'doc'      => $doc,
            'supplier' => $db->table('suppliers')->where('id', $doc['supplier_id'])->get()->getRowArray(),
            'location' => isset($doc['location_id']) ? $db->table('locations')->where('id', $doc['location_id'])->get()->getRowArray() : null,
            'against'  => ! empty($doc['purchase_id']) ? $db->table('purchases')->select('id, purchase_no, bill_no, bill_date')->where('id', $doc['purchase_id'])->get()->getRowArray() : null,
            'lines'    => $db->table($this->itemsTable . ' l')->select('l.*, i.name, i.unit, i.hsn')
                ->join('items i', 'i.id = l.item_id')->where('l.' . $this->fk, $id)->orderBy('l.id')->get()->getResultArray(),
        ];
    }

    protected function meta(): array
    {
        return [
            'title' => $this->title, 'single' => rtrim($this->title, 's'), 'uri' => $this->uri,
            'no' => $this->noField, 'date' => $this->dateField, 'hasLocation' => $this->hasLocation,
            'table' => $this->table,
        ];
    }

    /** Admin ("Delete entries"): delete the document; stock and accounts are reversed by beforeDelete(). */
    public function delete($id)
    {
        $this->requirePermission('entries.delete');
        $doc = $this->findOr404($this->table, $id);
        $db  = db_connect();
        $db->transException(true)->transStart();
        try {
            $this->beforeDelete($doc);
            $db->table($this->itemsTable)->where($this->fk, $doc['id'])->delete();
            $db->table($this->table)->where('id', $doc['id'])->delete();
            $db->transComplete();
        } catch (\RuntimeException $e) {
            $db->transRollback();

            return $this->back($e->getMessage());
        }
        \App\Libraries\Workflow::log($this->table, (int) $doc['id'], 'deleted', $this->meta()['single'] . ' ' . $doc[$this->noField] . ' (' . money($doc['total']) . ') deleted');

        return redirect()->to('admin/' . $this->uri)->with('message', $this->meta()['single'] . ' ' . $doc[$this->noField] . ' deleted.');
    }

    /** Undo what saving the document did. Throw RuntimeException to refuse. */
    protected function beforeDelete(array $doc): void
    {
    }
}
