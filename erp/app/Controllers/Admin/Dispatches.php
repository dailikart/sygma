<?php

namespace App\Controllers\Admin;

use App\Libraries\Messenger;
use App\Libraries\Notifier;
use App\Libraries\Numbering;
use App\Libraries\Pdf;
use App\Libraries\Stock;
use App\Libraries\Workflow;

/**
 * Material dispatch: the store prepares the list from the project's item
 * group (kit), prints it, loads the vehicle and dispatches (stock goes out →
 * "in transit"); the delivery person marks it delivered with a photo.
 */
class Dispatches extends AdminController
{
    public function index()
    {
        $this->requirePermission('dispatch.manage', 'delivery.update');
        $b = db_connect()->table('dispatches d')
            ->select('d.*, p.project_no, c.name AS customer, c.city, c.phone, l.name AS location')
            ->join('projects p', 'p.id = d.project_id')->join('customers c', 'c.id = p.customer_id')
            ->join('locations l', 'l.id = d.location_id');
        $status = $this->request->getGet('status');
        if ($status) {
            $b->where('d.status', $status);
        } elseif (! can('dispatch.manage')) {
            $b->where('d.status', 'dispatched'); // delivery team sees what is on the road
        }

        return $this->render('dispatches/index', ['rows' => $b->orderBy('d.id', 'DESC')->limit(300)->get()->getResultArray()]);
    }

    /** Dispatch list prepared from the kit (or another item group). */
    public function create($projectId)
    {
        $this->requirePermission('dispatch.manage');
        $project = $this->findOr404('projects', $projectId);
        $stage   = Workflow::stage((int) $project['id'], 'dispatch');
        if (! $stage || ! in_array($stage['status'], ['active', 'done'], true) || $project['status'] !== 'active') {
            return $this->back('Material can be dispatched only after the site survey is approved.');
        }
        if ($msg = self::financeBlock($project)) {
            return $this->back($msg);
        }
        $db    = db_connect();
        $kitId = (int) ($this->request->getGet('kit') ?: $project['kit_id']);
        $rows  = $kitId ? $db->table('kit_items')->select('item_id, qty')->where('kit_id', $kitId)->get()->getResultArray() : [];

        return $this->render('dispatches/form', [
            'project'   => $project,
            'customer'  => $db->table('customers')->where('id', $project['customer_id'])->get()->getRowArray(),
            'kits'      => $db->table('kits')->where('is_active', 1)->orderBy('capacity_kw')->get()->getResultArray(),
            'kitId'     => $kitId,
            'rows'      => $rows,
            'items'     => $db->table('items')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray(),
            'locations' => options('locations'),
            'balances'  => Stock::balances(),
        ]);
    }

    public function store($projectId)
    {
        $this->requirePermission('dispatch.manage');
        $project = $this->findOr404('projects', $projectId);
        $loc     = (int) $this->request->getPost('location_id');
        $lines   = [];
        foreach ((array) $this->request->getPost('items') as $r) {
            if (! empty($r['item_id']) && (float) ($r['qty'] ?? 0) > 0) {
                $lines[(int) $r['item_id']] = ($lines[(int) $r['item_id']] ?? 0) + (float) $r['qty'];
            }
        }
        if (! $loc || ! $lines) {
            return $this->back('Choose the store location and at least one item.');
        }
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('dispatches')->insert([
            'dispatch_no' => Numbering::next('dispatches', 'dispatch_no', setting('Solar.dispatchPrefix')),
            'project_id' => $project['id'], 'location_id' => $loc, 'status' => 'draft',
            'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        foreach ($lines as $itemId => $q) {
            $db->table('dispatch_items')->insert(['dispatch_id' => $id, 'item_id' => $itemId, 'qty' => $q]);
        }
        $db->transComplete();
        Workflow::log('project', (int) $project['id'], 'dispatch_list', 'Dispatch list prepared');

        return redirect()->to('admin/dispatches/' . $id)->with('message', 'Dispatch list prepared. Print it, load the vehicle, then click "Dispatch".');
    }

    public function show($id)
    {
        $this->requirePermission('dispatch.manage', 'delivery.update');

        return $this->render('dispatches/show', $this->data((int) $id) + ['balances' => Stock::balances()]);
    }

    /** Delivery note (Tally style) with rates, GST and HSN summary. */
    public function deliveryNote($id)
    {
        $this->requirePermission('dispatch.manage', 'delivery.update');
        $d   = $this->data((int) $id);
        $db  = db_connect();
        $inv = $d['project']['invoice_id'] ? $db->table('invoices')->where('id', $d['project']['invoice_id'])->get()->getRowArray() : null;
        $invRates = [];
        if ($inv) {
            foreach ($db->table('invoice_items')->where('invoice_id', $inv['id'])->where('item_id IS NOT NULL', null, false)->get()->getResultArray() as $l) {
                $invRates[$l['item_id']] = ['rate' => (float) $l['rate'], 'tax' => (float) $l['tax_percent']];
            }
        }
        $lines = [];
        foreach ($db->table('dispatch_items di')->select('di.*, i.name, i.unit, i.hsn, i.sale_price, i.tax_percent')
            ->join('items i', 'i.id = di.item_id')->where('di.dispatch_id', $id)->orderBy('di.id')->get()->getResultArray() as $l) {
            $rate = $invRates[$l['item_id']]['rate'] ?? (float) $l['sale_price'];
            $tax  = $invRates[$l['item_id']]['tax'] ?? (float) $l['tax_percent'];
            $amt  = round((float) $l['qty'] * $rate, 2);
            $lines[] = $l + ['rate' => $rate, 'tax_percent' => $tax, 'amount' => $amt, 'tax_amount' => round($amt * $tax / 100, 2)];
        }
        $d['inv']      = $inv;
        $d['dlines']   = $lines;
        $d['gstType']  = \App\Libraries\Tax::gstType($d['customer']['state']);
        Pdf::stream('pdf/delivery_note', $d, 'Delivery-Note-' . str_replace('/', '-', $d['d']['dispatch_no']));
    }

    public function printList($id)
    {
        $this->requirePermission('dispatch.manage', 'delivery.update');
        $d = $this->data((int) $id);
        Pdf::stream('pdf/dispatch', $d, str_replace('/', '-', $d['d']['dispatch_no']));
    }

    /** Vehicle loaded → stock out of the store, now "in transit". */
    /** Payment mode (cash / loan) must be decided before material leaves the store. */
    private static function financeBlock(array $project): ?string
    {
        $inv = $project['invoice_id'] ? db_connect()->table('invoices')->select('finance_mode')->where('id', $project['invoice_id'])->get()->getRowArray() : null;

        return $inv && ($inv['finance_mode'] ?? '') === ''
            ? 'Set the payment mode (cash / loan) on the invoice before dispatching material.' : null;
    }

    public function delete($id)
    {
        $this->requirePermission('dispatch.manage');
        $d = $this->findOr404('dispatches', $id);
        if ($d['status'] !== 'draft') {
            return $this->back('Only a draft dispatch (not yet loaded) can be deleted.');
        }
        $db = db_connect();
        $db->table('dispatch_items')->where('dispatch_id', $d['id'])->delete();
        $db->table('dispatches')->where('id', $d['id'])->delete();
        Workflow::log('project', (int) $d['project_id'], 'dispatch_deleted', 'Draft dispatch ' . $d['dispatch_no'] . ' deleted');

        return redirect()->to('admin/projects/' . $d['project_id'])->with('message', 'Draft dispatch ' . $d['dispatch_no'] . ' deleted.');
    }

    /** Store records serial numbers of the items being loaded. */
    public function serials($id)
    {
        $this->requirePermission('dispatch.manage');
        $d = $this->findOr404('dispatches', $id);
        if ($d['status'] === 'delivered') {
            return $this->back('Already delivered.');
        }
        foreach ((array) $this->request->getPost('serials') as $lineId => $text) {
            $clean = trim(preg_replace('/[\r\n,]+/', "\n", (string) $text));
            db_connect()->table('dispatch_items')->where(['id' => (int) $lineId, 'dispatch_id' => $d['id']])->update(['serial_nos' => $clean ?: null]);
        }

        return redirect()->back()->with('message', 'Serial numbers saved.');
    }

    public function dispatch($id)
    {
        $this->requirePermission('dispatch.manage');
        $d = $this->findOr404('dispatches', $id);
        if ($d['status'] !== 'draft') {
            return $this->back('Already dispatched.');
        }
        $db = db_connect();
        if ($msg = self::financeBlock($db->table('projects')->where('id', $d['project_id'])->get()->getRowArray())) {
            return $this->back($msg);
        }
        if ($this->request->getPost('serials')) {
            foreach ((array) $this->request->getPost('serials') as $lineId => $text) {
                $clean = trim(preg_replace('/[\r\n,]+/', "\n", (string) $text));
                $db->table('dispatch_items')->where(['id' => (int) $lineId, 'dispatch_id' => $d['id']])->update(['serial_nos' => $clean ?: null]);
            }
        }
        $db->transException(true)->transStart();
        try {
            foreach ($db->table('dispatch_items')->where('dispatch_id', $d['id'])->get()->getResultArray() as $l) {
                Stock::move((int) $l['item_id'], (int) $d['location_id'], -(float) $l['qty'], 'dispatch', 'dispatch', (int) $d['id'], 'To site');
            }
            $db->table('dispatches')->where('id', $d['id'])->update([
                'status' => 'dispatched', 'vehicle_no' => trim((string) $this->request->getPost('vehicle_no')) ?: null,
                'driver_name' => trim((string) $this->request->getPost('driver_name')) ?: null,
                'dispatched_through' => trim((string) $this->request->getPost('dispatched_through')) ?: null,
                'destination' => trim((string) $this->request->getPost('destination')) ?: null,
                'terms_of_delivery' => trim((string) $this->request->getPost('terms_of_delivery')) ?: null,
                'dispatched_at' => date('Y-m-d H:i:s'), 'dispatched_by' => auth()->id(),
            ]);
            $stage = Workflow::stage((int) $d['project_id'], 'dispatch');
            if ($stage['status'] === 'active') {
                Workflow::complete((int) $d['project_id'], 'dispatch'); // activates Delivery → notifies the delivery team
            } else {
                Notifier::role('delivery', 'Extra material dispatched – ' . $d['dispatch_no'], 'Please deliver and update with a photo.', 'admin/dispatches/' . $d['id']);
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back($e->getMessage());
        }

        $p = $db->table('projects')->where('id', $d['project_id'])->get()->getRowArray();
        $c = $db->table('customers')->where('id', $p['customer_id'])->get()->getRowArray();
        Messenger::customer('material_dispatched', $c, ['project_no' => $p['project_no'], 'dispatch_no' => $d['dispatch_no'], 'vehicle_no' => (string) $this->request->getPost('vehicle_no'), 'status_link' => \App\Libraries\StatusLink::url((int) $p['id'])],
            'Material dispatched – ' . $p['project_no'], "Dear {$c['name']},\n\nThe material for your solar system has been dispatched today and will reach your site soon.");

        return redirect()->back()->with('message', 'Dispatched. Stock reduced and shown as in transit. The delivery team has been notified.');
    }

    /** Delivery person: delivered at site, with a photo. */
    public function deliver($id)
    {
        $this->requirePermission('delivery.update', 'dispatch.manage');
        $d = $this->findOr404('dispatches', $id);
        if ($d['status'] !== 'dispatched') {
            return $this->back('This dispatch is not on the road.');
        }
        $photo = $this->request->getFile('photo');
        if (! $photo || ! $photo->isValid()) {
            return $this->back('Take or choose a photo of the material at the site.');
        }
        $ext = strtolower($photo->getClientExtension() ?: $photo->guessExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) {
            return $this->back('The delivery photo must be an image.');
        }
        $db   = db_connect();
        $name = 'delivery_photo-' . time() . '.' . $ext;
        $photo->move(upload_path() . 'projects/' . $d['project_id'], $name);

        $db->transException(true)->transStart();
        try {
            $db->table('project_files')->insert([
                'project_id' => $d['project_id'], 'stage' => 'delivery', 'label' => 'delivery_photo',
                'file_path' => 'projects/' . $d['project_id'] . '/' . $name, 'original_name' => $photo->getClientName(),
                'uploaded_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('dispatches')->where('id', $d['id'])->update([
                'status' => 'delivered', 'delivered_at' => date('Y-m-d H:i:s'), 'delivered_by' => auth()->id(),
                'delivery_note' => trim((string) $this->request->getPost('delivery_note')) ?: null,
            ]);
            $stage = Workflow::stage((int) $d['project_id'], 'delivery');
            // Only loads on the road count; an unused draft list must not block delivery.
            $open  = $db->table('dispatches')->where(['project_id' => $d['project_id'], 'status' => 'dispatched'])->countAllResults();
            if ($stage && $stage['status'] === 'active' && ! $open) {
                Workflow::complete((int) $d['project_id'], 'delivery'); // activates Installation → notifies installers
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back($e->getMessage());
        }

        $p = $db->table('projects')->where('id', $d['project_id'])->get()->getRowArray();
        $c = $db->table('customers')->where('id', $p['customer_id'])->get()->getRowArray();
        Messenger::customer('material_delivered', $c, ['project_no' => $p['project_no'], 'dispatch_no' => $d['dispatch_no'], 'status_link' => \App\Libraries\StatusLink::url((int) $p['id'])],
            'Material delivered – ' . $p['project_no'], "Dear {$c['name']},\n\nThe material for your solar system has been delivered at your site. Our installation team will contact you.");

        return redirect()->to('admin/dispatches/' . $d['id'])->with('message', 'Marked as delivered. The installation team has been notified.');
    }

    private function data(int $id): array
    {
        $d  = $this->findOr404('dispatches', $id);
        $db = db_connect();
        $p  = $db->table('projects')->where('id', $d['project_id'])->get()->getRowArray();

        return [
            'd'        => $d,
            'project'  => $p,
            'customer' => $db->table('customers')->where('id', $p['customer_id'])->get()->getRowArray(),
            'location' => $db->table('locations')->where('id', $d['location_id'])->get()->getRowArray(),
            'lines'    => $db->table('dispatch_items di')->select('di.*, i.name, i.unit, i.sku, c.name AS category')
                ->join('items i', 'i.id = di.item_id')->join('item_categories c', 'c.id = i.category_id', 'left')
                ->where('di.dispatch_id', $id)->orderBy('c.name')->orderBy('i.name')->get()->getResultArray(),
            'photos'   => $db->table('project_files')->where(['project_id' => $p['id'], 'label' => 'delivery_photo'])->get()->getResultArray(),
            'taken'    => \App\Libraries\Lots::takenBy('dispatch', (int) $d['id']),
        ];
    }
}
