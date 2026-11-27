<?php

namespace App\Controllers\Admin;

use App\Libraries\Workflow;

/**
 * AMC: service visit schedule, completing visits, contracts.
 */
class Amc extends AdminController
{
    public function index()
    {
        $this->requirePermission('amc.view', 'amc.manage');
        $show = $this->request->getGet('show') ?: 'due';
        $b    = db_connect()->table('amc_visits v')
            ->select('v.*, ac.contract_no, ac.project_id, ac.end_date, p.project_no, c.name, c.phone, c.city, c.address, pl.name AS plan')
            ->join('amc_contracts ac', 'ac.id = v.amc_contract_id')->join('projects p', 'p.id = ac.project_id')
            ->join('customers c', 'c.id = ac.customer_id')->join('amc_plans pl', 'pl.id = ac.amc_plan_id');
        if ($show === 'due') {
            $b->where('v.status', 'scheduled')->where('v.due_date <=', date('Y-m-d', strtotime('+30 days')));
        } elseif ($show === 'done') {
            $b->where('v.status', 'done');
        } else {
            $b->where('v.status', 'scheduled');
        }
        if (! can('amc.manage')) {
            $b->groupStart()->where('v.assigned_to', auth()->id())->orWhere('v.assigned_to', null)->groupEnd();
        }

        return $this->render('amc/index', [
            'rows' => $b->orderBy('v.due_date', $show === 'done' ? 'DESC' : 'ASC')->limit(500)->get()->getResultArray(),
            'show' => $show, 'staff' => staff_options('service') ?: staff_options(),
        ]);
    }

    public function contract($id)
    {
        $this->requirePermission('amc.view', 'amc.manage');
        $c  = $this->findOr404('amc_contracts', $id);
        $db = db_connect();

        return $this->render('amc/contract', [
            'c'        => $c,
            'plan'     => $db->table('amc_plans')->where('id', $c['amc_plan_id'])->get()->getRowArray(),
            'project'  => $db->table('projects')->where('id', $c['project_id'])->get()->getRowArray(),
            'customer' => $db->table('customers')->where('id', $c['customer_id'])->get()->getRowArray(),
            'visits'   => $db->table('amc_visits')->where('amc_contract_id', $c['id'])->orderBy('visit_no')->get()->getResultArray(),
            'staff'    => staff_options('service') ?: staff_options(),
        ]);
    }

    public function assign($visitId)
    {
        $this->requirePermission('amc.manage');
        $v = $this->findOr404('amc_visits', $visitId);
        $u = (int) $this->request->getPost('user_id') ?: null;
        db_connect()->table('amc_visits')->where('id', $v['id'])->update([
            'assigned_to' => $u, 'due_date' => $this->request->getPost('due_date') ?: $v['due_date'],
        ]);
        if ($u) {
            \App\Libraries\Notifier::users([$u], 'AMC visit assigned', 'Visit ' . $v['visit_no'] . ' due ' . fdate($this->request->getPost('due_date') ?: $v['due_date']), 'admin/amc');
        }

        return redirect()->back()->with('message', 'Visit updated.');
    }

    public function complete($visitId)
    {
        $this->requirePermission('amc.manage', 'amc.view');
        $v = $this->findOr404('amc_visits', $visitId);
        if (! can('amc.manage') && $v['assigned_to'] && (int) $v['assigned_to'] !== (int) auth()->id()) {
            throw new \App\Exceptions\ForbiddenException();
        }
        $findings = trim((string) $this->request->getPost('findings'));
        if ($findings === '') {
            return $this->back('Write what was done / found during the visit.');
        }
        try {
            $photo = $this->storeUpload('photo', 'amc');
        } catch (\RuntimeException $e) {
            return $this->back($e->getMessage());
        }
        db_connect()->table('amc_visits')->where('id', $v['id'])->update([
            'status' => 'done', 'done_date' => $this->request->getPost('done_date') ?: date('Y-m-d'),
            'done_by' => auth()->id(), 'findings' => $findings, 'photo_path' => $photo,
        ]);
        $c = db_connect()->table('amc_contracts')->where('id', $v['amc_contract_id'])->get()->getRowArray();
        Workflow::log('project', (int) $c['project_id'], 'amc_visit', 'AMC visit ' . $v['visit_no'] . ' completed: ' . $findings);

        return redirect()->back()->with('message', 'Visit marked as done.');
    }

    public function photo($visitId)
    {
        $this->requirePermission('amc.view', 'amc.manage');
        $v    = $this->findOr404('amc_visits', $visitId);
        $path = $v['photo_path'] ? realpath(upload_path() . $v['photo_path']) : false;
        if (! $path || ! str_starts_with($path, realpath(upload_path()))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path))->setBody(file_get_contents($path));
    }
}
