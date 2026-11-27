<?php

namespace App\Controllers\Admin;

class Customers extends AdminController
{
    public function index()
    {
        $this->requirePermission('customers.view');
        $b = db_connect()->table('customers c')
            ->select('c.*, (SELECT COUNT(*) FROM ' . db_connect()->prefixTable('projects') . ' p WHERE p.customer_id = c.id) AS projects,
                (SELECT COALESCE(SUM(i.total - i.amount_paid), 0) FROM ' . db_connect()->prefixTable('invoices') . ' i WHERE i.customer_id = c.id AND i.status IN (\'unpaid\',\'partial\')) AS due', false)
            ->where('c.deleted_at', null);
        if ($t = $this->request->getGet('type')) {
            $b->where('c.type', $t);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('c.name', $q)->orLike('c.phone', $q)->orLike('c.company', $q)->orLike('c.city', $q)->groupEnd();
        }

        return $this->render('customers/index', ['rows' => $b->orderBy('c.id', 'DESC')->get()->getResultArray()]);
    }

    public function form($id = null)
    {
        $this->requirePermission('customers.manage');

        return $this->render('customers/form', ['row' => $id ? $this->findOr404('customers', $id) : []]);
    }

    public function save($id = null)
    {
        $this->requirePermission('customers.manage');
        if (! $this->validate([
            'type'  => 'required|in_list[b2b,b2c]',
            'name'  => 'required|max_length[191]',
            'phone' => 'required|max_length[20]',
            'email' => 'permit_empty|valid_email',
            'gstin' => 'permit_empty|exact_length[15]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p    = $this->request->getPost();
        $data = [];
        foreach (['type', 'name', 'company', 'gstin', 'phone', 'email', 'address', 'city', 'state', 'pincode', 'eb_service_no', 'notes'] as $f) {
            $v        = trim((string) ($p[$f] ?? ''));
            $data[$f] = $v === '' ? null : $v;
        }
        $data['gstin'] = $data['gstin'] ? strtoupper($data['gstin']) : null;
        $data += eb_charges_input($p);

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $this->findOr404('customers', $id);
            $db->table('customers')->where('id', (int) $id)->update($data + ['updated_at' => $now]);
            \App\Libraries\Ledger::syncName('customer', (int) $id, $data['name']);
        } else {
            $db->table('customers')->insert($data + ['created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
            $id = $db->insertID();
        }

        return redirect()->to('admin/customers/' . $id)->with('message', 'Customer saved.');
    }

    public function show($id)
    {
        $this->requirePermission('customers.view');
        $c  = $this->findOr404('customers', $id);
        $db = db_connect();

        return $this->render('customers/show', [
            'c'        => $c,
            'invoices' => $db->table('invoices')->where('customer_id', $c['id'])->orderBy('id', 'DESC')->get()->getResultArray(),
            'payments' => $db->table('payments')->where('customer_id', $c['id'])->orderBy('id', 'DESC')->get()->getResultArray(),
            'projects' => $db->table('projects')->where('customer_id', $c['id'])->orderBy('id', 'DESC')->get()->getResultArray(),
        ]);
    }

    /** Admin: delete a customer who has no invoices (delete their invoices first). */
    public function delete($id)
    {
        $this->requirePermission('entries.delete');
        $c  = $this->findOr404('customers', $id);
        $db = db_connect();
        $n  = $db->table('invoices')->where('customer_id', $c['id'])->countAllResults();
        if ($n) {
            return $this->back($c['name'] . ' has ' . $n . ' invoice(s). Delete those invoices first, then the customer.');
        }
        $db->table('customers')->where('id', $c['id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $db->table('leads')->where('customer_id', $c['id'])->update(['customer_id' => null]);
        \App\Libraries\Workflow::log('customer', (int) $c['id'], 'deleted', 'Customer ' . $c['name'] . ' (' . $c['phone'] . ') deleted');

        return redirect()->to('admin/customers')->with('message', 'Customer ' . $c['name'] . ' deleted.');
    }
}
