<?php

namespace App\Controllers\Admin;

/**
 * Marketing: leads, follow-ups, and conversion to a customer.
 */
class Leads extends AdminController
{
    public function index()
    {
        $this->requirePermission('leads.view');
        $db = db_connect();
        $b  = $db->table('leads')->where('deleted_at', null);

        $status = $this->request->getGet('status');
        if ($status) {
            $b->where('status', $status);
        } elseif (! $this->request->getGet('all')) {
            $b->whereNotIn('status', ['confirmed', 'lost']);
        }
        if ($this->request->getGet('due')) {
            $b->where('next_followup_date <=', date('Y-m-d'));
        }
        if ($src = $this->request->getGet('source')) {
            $b->where('source', (string) $src);
        }
        if ($a = $this->request->getGet('assigned')) {
            $b->where('assigned_to', (int) $a);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('name', $q)->orLike('phone', $q)->orLike('city', $q)->groupEnd();
        }

        return $this->render('leads/index', [
            'rows'     => $b->orderBy('next_followup_date IS NULL', '', false)->orderBy('next_followup_date')->orderBy('id', 'DESC')->get()->getResultArray(),
            'statuses' => config('Workflow')->leadStatuses,
        ]);
    }

    public function form($id = null)
    {
        $this->requirePermission('leads.manage');

        return $this->render('leads/form', [
            'row'   => $id ? $this->findOr404('leads', $id) : [],
            'staff' => staff_options('marketing') ?: staff_options(),
        ]);
    }

    public function save($id = null)
    {
        $this->requirePermission('leads.manage');
        if (! $this->validate([
            'name'  => 'required|max_length[191]',
            'phone' => 'required|max_length[20]',
            'email' => 'permit_empty|valid_email',
            'customer_type' => 'required|in_list[b2b,b2c]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p    = $this->request->getPost() + [
            'email' => '', 'address' => '', 'city' => '', 'source' => '', 'required_kw' => 0, 'monthly_bill' => 0,
            'assigned_to' => '', 'next_followup_date' => '', 'notes' => '',
        ];
        $data = [
            'name' => trim($p['name']), 'phone' => trim($p['phone']), 'email' => ($p['email'] ?? '') ?: null,
            'address' => ($p['address'] ?? '') ?: null, 'city' => ($p['city'] ?? '') ?: null, 'source' => ($p['source'] ?? '') ?: null,
            'customer_type' => $p['customer_type'], 'required_kw' => (float) $p['required_kw'],
            'monthly_bill' => (float) $p['monthly_bill'], 'assigned_to' => ($p['assigned_to'] ?? '') ?: null,
            'next_followup_date' => ($p['next_followup_date'] ?? '') ?: null, 'notes' => ($p['notes'] ?? '') ?: null,
        ];
        if (isset($p['status']) && array_key_exists($p['status'], config('Workflow')->leadStatuses) && $p['status'] !== 'confirmed') {
            $data['status'] = $p['status'];
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        if (! $id) {
            $dupe = $db->table('leads')->where(['phone' => $data['phone'], 'deleted_at' => null])->get()->getRowArray();
            if ($dupe && ! $this->request->getPost('allow_duplicate')) {
                return $this->back('A lead with this phone number already exists: ' . $dupe['name'] . '. Tick "save anyway" to add it again.');
            }
            $db->table('leads')->insert($data + ['created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
            $id = $db->insertID();
            if ($data['assigned_to'] && (int) $data['assigned_to'] !== (int) auth()->id()) {
                \App\Libraries\Notifier::users([(int) $data['assigned_to']], 'New lead assigned: ' . $data['name'], $data['phone'] . ' ' . ($data['city'] ?? ''), 'admin/leads/' . $id);
            }
        } else {
            $this->findOr404('leads', $id);
            $db->table('leads')->where('id', (int) $id)->update($data + ['updated_at' => $now]);
        }

        return redirect()->to('admin/leads/' . $id)->with('message', 'Lead saved.');
    }

    public function show($id)
    {
        $this->requirePermission('leads.view');
        $lead = $this->findOr404('leads', $id);

        return $this->render('leads/show', [
            'lead'      => $lead,
            'followups' => db_connect()->table('lead_followups')->where('lead_id', $lead['id'])->orderBy('id', 'DESC')->get()->getResultArray(),
            'statuses'  => config('Workflow')->leadStatuses,
        ]);
    }

    public function followup($id)
    {
        $this->requirePermission('leads.manage');
        $lead   = $this->findOr404('leads', $id);
        $status = $this->request->getPost('status');
        if (! array_key_exists($status, config('Workflow')->leadStatuses) || $status === 'confirmed') {
            $status = $lead['status'];
        }
        $note = trim((string) $this->request->getPost('note'));
        if ($note === '') {
            return $this->back('Write what was discussed.');
        }
        $next = $this->request->getPost('next_followup_date') ?: null;

        $db = db_connect();
        $db->table('lead_followups')->insert([
            'lead_id' => $lead['id'], 'note' => $note, 'status_after' => $status,
            'next_followup_date' => $next, 'created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('leads')->where('id', $lead['id'])->update([
            'status' => $status, 'next_followup_date' => $next,
            'lost_reason' => $status === 'lost' ? $note : null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('admin/leads/' . $lead['id'])->with('message', 'Follow-up saved.');
    }

    /** Lead confirmed → becomes a customer (B2B / B2C). */
    public function convert($id)
    {
        $this->requirePermission('leads.manage');
        $this->requirePermission('customers.manage');
        $lead = $this->findOr404('leads', $id);
        if ($lead['customer_id']) {
            return redirect()->to('admin/customers/' . $lead['customer_id']);
        }
        $type = in_array($this->request->getPost('type'), ['b2b', 'b2c'], true) ? $this->request->getPost('type') : $lead['customer_type'];

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('customers')->insert([
            'type' => $type, 'name' => $lead['name'], 'phone' => $lead['phone'], 'email' => $lead['email'],
            'address' => $lead['address'], 'city' => $lead['city'], 'state' => setting('Solar.companyState'),
            'lead_id' => $lead['id'], 'notes' => $lead['notes'],
            'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $customerId = $db->insertID();
        $db->table('leads')->where('id', $lead['id'])->update(['status' => 'confirmed', 'customer_id' => $customerId, 'updated_at' => $now]);
        $db->transComplete();

        return redirect()->to('admin/customers/' . $customerId . '/edit')->with('message', 'Lead converted to a customer. Check the details, then create the invoice.');
    }
}
