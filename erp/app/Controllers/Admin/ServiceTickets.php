<?php

namespace App\Controllers\Admin;

use App\Libraries\Messenger;
use App\Libraries\Notifier;
use App\Libraries\ServiceDesk;
use App\Libraries\StatusLink;
use App\Libraries\Workflow;
use RuntimeException;

/**
 * Service complaints: register → assign technician (+ visit date) → updates
 * with photos → resolve (chargeable if outside AMC) → close.
 */
class ServiceTickets extends AdminController
{
    public function index()
    {
        $this->requirePermission('amc.view', 'amc.manage', 'customers.manage');
        $db  = db_connect();
        $b   = $db->table('service_tickets t')->select('t.*, c.name AS customer, c.city, c.phone, p.project_no')
            ->join('customers c', 'c.id = t.customer_id')->join('projects p', 'p.id = t.project_id', 'left');
        $st  = (string) ($this->request->getGet('status') ?? 'active');
        if ($st === 'active') {
            $b->whereIn('t.status', ['open', 'assigned', 'in_progress']);
        } elseif (isset(ServiceDesk::STATUSES[$st])) {
            $b->where('t.status', $st);
        }
        if ($u = (int) $this->request->getGet('assigned')) {
            $b->where('t.assigned_to', $u);
        }
        if ($term = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('t.ticket_no', $term)->orLike('c.name', $term)->orLike('c.phone', $term)->orLike('p.project_no', $term)->groupEnd();
        }
        $counts = array_column($db->table('service_tickets')->select('status, COUNT(*) AS n')->groupBy('status')->get()->getResultArray(), 'n', 'status');

        return $this->render('service/index', [
            'rows' => $b->orderBy("FIELD(t.status, 'open', 'assigned', 'in_progress', 'resolved', 'closed')", '', false)
                ->orderBy("FIELD(t.priority, 'urgent', 'high', 'normal', 'low')", '', false)->orderBy('t.id', 'DESC')->get()->getResultArray(),
            'counts' => $counts, 'status' => $st, 'staff' => staff_options(),
        ]);
    }

    public function create()
    {
        $this->requirePermission('amc.manage', 'customers.manage');
        $db = db_connect();

        return $this->render('service/form', [
            'customers' => $db->table('customers')->select('id, name, phone, city')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
            'projects'  => $db->table('projects')->select('id, customer_id, project_no, capacity_kw')->whereIn('status', ['active', 'completed', 'on_hold'])->orderBy('id', 'DESC')->get()->getResultArray(),
            'customerId' => (int) $this->request->getGet('customer'),
            'projectId'  => (int) $this->request->getGet('project'),
        ]);
    }

    public function store()
    {
        $this->requirePermission('amc.manage', 'customers.manage');
        if (! $this->validate(['customer_id' => 'required|is_natural_no_zero', 'category' => 'required', 'description' => 'required|max_length[2000]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p        = $this->request->getPost();
        $customer = $this->findOr404('customers', $p['customer_id']);
        $project  = (int) ($p['project_id'] ?? 0) ?: null;
        if ($project && ! db_connect()->table('projects')->where(['id' => $project, 'customer_id' => $customer['id']])->countAllResults()) {
            return $this->back('That project does not belong to this customer.');
        }
        $id = ServiceDesk::create((int) $customer['id'], $project, (string) $p['category'], (string) ($p['priority'] ?? 'normal'), (string) $p['description'],
            (string) ($p['reported_via'] ?? 'phone'), trim((string) ($p['contact_phone'] ?? '')) ?: $customer['phone'], (int) auth()->id());
        if ($assign = (int) ($p['assigned_to'] ?? 0)) {
            $this->doAssign($id, $assign, ($p['scheduled_date'] ?? '') ?: null);
        }

        return redirect()->to('admin/service/' . $id)->with('message', 'Complaint registered.');
    }

    public function show($id)
    {
        $t = $this->ticket((int) $id);
        if (! can('amc.view') && ! can('amc.manage') && ! can('customers.manage') && ! ServiceDesk::canWork($t)) {
            throw new \App\Exceptions\ForbiddenException();
        }
        $db = db_connect();

        return $this->render('service/show', [
            't' => $t,
            'updates'  => $db->table('service_ticket_updates')->where('ticket_id', $t['id'])->orderBy('id')->get()->getResultArray(),
            'contract' => $t['project_id'] ? $db->table('amc_contracts')->where('project_id', $t['project_id'])->orderBy('id', 'DESC')->get()->getRowArray() : null,
            'history'  => $db->table('service_tickets')->select('id, ticket_no, category, status, created_at')->where('customer_id', $t['customer_id'])->where('id !=', $t['id'])->orderBy('id', 'DESC')->limit(10)->get()->getResultArray(),
            'staff'    => staff_options('service') ?: staff_options(),
        ]);
    }

    public function assign($id)
    {
        $this->requirePermission('amc.manage');
        $t = $this->ticket((int) $id);
        $u = (int) $this->request->getPost('assigned_to');
        if (! $u) {
            return $this->back('Choose the technician.');
        }
        $this->doAssign((int) $t['id'], $u, ($this->request->getPost('scheduled_date') ?? '') ?: null);

        return redirect()->back()->with('message', 'Assigned to ' . user_name($u) . '.');
    }

    /** Note / progress / photo by the technician or office. */
    public function update($id)
    {
        $t = $this->ticket((int) $id);
        if (! ServiceDesk::canWork($t)) {
            throw new \App\Exceptions\ForbiddenException();
        }
        $note   = trim((string) $this->request->getPost('note'));
        $status = (string) $this->request->getPost('status');
        try {
            $photo = $this->storeUpload('photo', 'service');
        } catch (RuntimeException $e) {
            return $this->back($e->getMessage());
        }
        if ($note === '' && ! $photo && $status === '') {
            return $this->back('Write a note or attach a photo.');
        }
        $newStatus = in_array($status, ['open', 'assigned', 'in_progress'], true) && ! in_array($t['status'], ['resolved', 'closed'], true) ? $status : null;
        if ($newStatus) {
            db_connect()->table('service_tickets')->where('id', $t['id'])->update(['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        ServiceDesk::addUpdate((int) $t['id'], $newStatus, $note ?: null, $photo, (int) auth()->id());

        return redirect()->back()->with('message', 'Update saved.');
    }

    public function resolve($id)
    {
        $t = $this->ticket((int) $id);
        if (! ServiceDesk::canWork($t)) {
            throw new \App\Exceptions\ForbiddenException();
        }
        if (in_array($t['status'], ['resolved', 'closed'], true)) {
            return $this->back('Already resolved.');
        }
        $resolution = trim((string) $this->request->getPost('resolution'));
        if ($resolution === '') {
            return $this->back('Write what was done to fix it.');
        }
        try {
            $photo = $this->storeUpload('photo', 'service');
        } catch (RuntimeException $e) {
            return $this->back($e->getMessage());
        }
        $charge = round(max(0, (float) $this->request->getPost('charge_amount')), 2);
        $now    = date('Y-m-d H:i:s');
        db_connect()->table('service_tickets')->where('id', $t['id'])->update([
            'status' => 'resolved', 'resolution' => $resolution, 'resolved_at' => $now, 'resolved_by' => auth()->id(),
            'chargeable' => $charge > 0 ? 1 : 0, 'charge_amount' => $charge, 'updated_at' => $now,
        ]);
        ServiceDesk::addUpdate((int) $t['id'], 'resolved', $resolution . ($charge > 0 ? ' (Service charge ' . money($charge) . ')' : ''), $photo, (int) auth()->id());
        Messenger::customer('service_ticket_resolved', ['name' => $t['customer'], 'email' => $t['email'], 'phone' => $t['phone']], [
            'ticket_no' => $t['ticket_no'], 'resolution' => $resolution,
        ] + ($t['project_id'] ? ['status_link' => StatusLink::url((int) $t['project_id'])] : []), 'Complaint resolved – ' . $t['ticket_no'],
            "Dear {$t['customer']},\n\nYour complaint {$t['ticket_no']} has been resolved.\nWhat we did: {$resolution}" . ($charge > 0 ? "\nService charge: " . money($charge) : '') . "\n\nIf the problem comes back, please let us know.");
        Workflow::log('service_ticket', (int) $t['id'], 'resolved', 'Complaint ' . $t['ticket_no'] . ' resolved');

        return redirect()->back()->with('message', 'Marked as resolved. The customer has been informed.');
    }

    public function close($id)
    {
        $this->requirePermission('amc.manage');
        $t = $this->ticket((int) $id);
        $action = (string) $this->request->getPost('action');
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        if ($action === 'reopen') {
            $db->table('service_tickets')->where('id', $t['id'])->update(['status' => $t['assigned_to'] ? 'assigned' : 'open', 'closed_at' => null, 'resolved_at' => null, 'updated_at' => $now]);
            ServiceDesk::addUpdate((int) $t['id'], $t['assigned_to'] ? 'assigned' : 'open', 'Reopened: ' . (trim((string) $this->request->getPost('note')) ?: 'problem not solved'), null, (int) auth()->id());

            return redirect()->back()->with('message', 'Complaint reopened.');
        }
        if ($t['status'] !== 'resolved') {
            return $this->back('Resolve the complaint before closing it.');
        }
        $db->table('service_tickets')->where('id', $t['id'])->update(['status' => 'closed', 'closed_at' => $now, 'updated_at' => $now]);
        ServiceDesk::addUpdate((int) $t['id'], 'closed', 'Closed' . (($n = trim((string) $this->request->getPost('note'))) ? ': ' . $n : '.'), null, (int) auth()->id());

        return redirect()->back()->with('message', 'Complaint closed.');
    }

    public function delete($id)
    {
        $this->requirePermission('entries.delete');
        $t  = $this->ticket((int) $id);
        $db = db_connect();
        foreach ($db->table('service_ticket_updates')->where('ticket_id', $t['id'])->where('photo_path IS NOT NULL', null, false)->get()->getResultArray() as $u) {
            @unlink(upload_path() . $u['photo_path']);
        }
        $db->table('service_ticket_updates')->where('ticket_id', $t['id'])->delete();
        $db->table('service_tickets')->where('id', $t['id'])->delete();
        Workflow::log('service_ticket', (int) $t['id'], 'deleted', 'Complaint ' . $t['ticket_no'] . ' deleted');

        return redirect()->to('admin/service')->with('message', 'Complaint ' . $t['ticket_no'] . ' deleted.');
    }

    public function photo($updateId)
    {
        $u = $this->findOr404('service_ticket_updates', $updateId);
        $t = $this->ticket((int) $u['ticket_id']);
        if (! can('amc.view') && ! can('amc.manage') && ! can('customers.manage') && ! ServiceDesk::canWork($t)) {
            throw new \App\Exceptions\ForbiddenException();
        }
        $path = $u['photo_path'] ? realpath(upload_path() . $u['photo_path']) : false;
        if (! $path || ! str_starts_with($path, realpath(upload_path()))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setContentType(mime_content_type($path) ?: 'application/octet-stream')->setBody(file_get_contents($path));
    }

    private function doAssign(int $ticketId, int $userId, ?string $date): void
    {
        $t = $this->ticket($ticketId);
        db_connect()->table('service_tickets')->where('id', $ticketId)->update([
            'assigned_to' => $userId, 'scheduled_date' => $date, 'status' => in_array($t['status'], ['open', 'assigned'], true) ? 'assigned' : $t['status'], 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ServiceDesk::addUpdate($ticketId, 'assigned', 'Assigned to ' . user_name($userId) . ($date ? ', visit on ' . fdate($date) : '') . '.', null, (int) auth()->id());
        Notifier::users([$userId], 'Complaint ' . $t['ticket_no'] . ' assigned to you', $t['customer'] . ' – ' . (ServiceDesk::CATEGORIES[$t['category']] ?? '') . ($date ? ', visit ' . fdate($date) : ''), 'admin/service/' . $ticketId);
    }

    private function ticket(int $id): array
    {
        $t = db_connect()->table('service_tickets t')
            ->select('t.*, c.name AS customer, c.phone, c.email, c.address, c.city, p.project_no, p.capacity_kw, p.commissioned_on')
            ->join('customers c', 'c.id = t.customer_id')->join('projects p', 'p.id = t.project_id', 'left')
            ->where('t.id', $id)->get()->getRowArray();
        if (! $t) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $t;
    }
}
