<?php

namespace App\Controllers\Admin;

use App\Libraries\Amc;
use App\Libraries\Messenger;
use App\Libraries\Workflow;
use RuntimeException;

/**
 * Installation projects and their stages.
 */
class Projects extends AdminController
{
    private const STAGE_PERMS = ['projects.view', 'survey.update', 'dispatch.manage', 'delivery.update', 'installation.update', 'eb.update', 'subsidy.update'];

    public function index()
    {
        $this->requirePermission(...self::STAGE_PERMS);
        $b = db_connect()->table('projects p')
            ->select('p.*, c.name AS customer, c.city, c.phone, i.invoice_no, i.total, i.amount_paid, t.name AS install_type')
            ->join('customers c', 'c.id = p.customer_id')
            ->join('invoices i', 'i.id = p.invoice_id', 'left')
            ->join('installation_types t', 't.id = p.installation_type_id', 'left');
        $status = $this->request->getGet('status') ?: 'active';
        if ($status !== 'all') {
            $b->where('p.status', $status);
        }
        if ($s = $this->request->getGet('stage')) {
            $b->where('p.current_stage', $s);
        }
        if ($t = $this->request->getGet('subsidy')) {
            $b->where('p.subsidy_type', $t);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('p.project_no', $q)->orLike('c.name', $q)->orLike('c.phone', $q)->groupEnd();
        }
        // Users who only handle a stage see only projects with a stage for them.
        if (! can('projects.view')) {
            $ids = array_column(Tasks::myTasks(), 'project_id');
            $b->whereIn('p.id', $ids ?: [0]);
        }

        return $this->render('projects/index', ['rows' => $b->orderBy('p.id', 'DESC')->get()->getResultArray(), 'status' => $status]);
    }

    public function show($id)
    {
        $project = $this->findOr404('projects', $id);
        $stages  = Workflow::stages((int) $project['id']);
        if (! can('projects.view') && ! array_filter($stages, static fn ($s) => Workflow::canAct($s))) {
            throw new \App\Exceptions\ForbiddenException();
        }
        $db = db_connect();
        $files = [];
        foreach ($db->table('project_files')->where('project_id', $project['id'])->orderBy('id')->get()->getResultArray() as $f) {
            $files[$f['stage']][] = $f;
        }

        return $this->render('projects/show', [
            'p'          => $project,
            'customer'   => $db->table('customers')->where('id', $project['customer_id'])->get()->getRowArray(),
            'invoice'    => $project['invoice_id'] ? $db->table('invoices')->where('id', $project['invoice_id'])->get()->getRowArray() : null,
            'stages'     => $stages,
            'files'      => $files,
            'kit'        => $project['kit_id'] ? $db->table('kits')->where('id', $project['kit_id'])->get()->getRowArray() : null,
            'type'       => $project['installation_type_id'] ? $db->table('installation_types')->where('id', $project['installation_type_id'])->get()->getRowArray() : null,
            'dispatches' => $db->table('dispatches')->where('project_id', $project['id'])->orderBy('id')->get()->getResultArray(),
            'log'        => $db->table('activity_log')->where(['entity' => 'project', 'entity_id' => $project['id']])->orderBy('id', 'DESC')->limit(50)->get()->getResultArray(),
            'amc'        => $db->table('amc_contracts')->where('project_id', $project['id'])->get()->getRowArray(),
            'amcPlans'   => options('amc_plans'),
            'missingDocs' => Documents::missingRequired((int) $project['customer_id']),
            'tranches'    => $project['invoice_id'] ? \App\Libraries\Finance::tranches((int) $project['invoice_id']) : [],
            'financeEditable' => $project['invoice_id'] ? \App\Libraries\Finance::editable($db->table('invoices')->where('id', $project['invoice_id'])->get()->getRowArray()) : false,
            'dispatchSerials' => $db->table('dispatch_items di')->select('di.serial_nos, c.name AS category')
                ->join('dispatches d', 'd.id = di.dispatch_id')->join('items i', 'i.id = di.item_id')->join('item_categories c', 'c.id = i.category_id', 'left')
                ->where('d.project_id', $project['id'])->where('di.serial_nos IS NOT NULL', null, false)->get()->getResultArray(),
            'wf'         => config('Workflow'),
        ]);
    }

    public function assign($id)
    {
        $this->requirePermission('projects.manage');
        $stage = db_connect()->table('project_stages')->where(['id' => (int) $this->request->getPost('stage_id'), 'project_id' => (int) $id])->get()->getRowArray();
        if (! $stage) {
            return $this->back('Stage not found.');
        }
        Workflow::assign((int) $stage['id'], (int) $this->request->getPost('user_id') ?: null, $this->request->getPost('planned_date') ?: null);

        return redirect()->back()->with('message', 'Stage assignment saved.');
    }

    /** Saves or completes a stage. POST action = save | complete | reject. */
    public function stage($id, $stage)
    {
        $project = $this->findOr404('projects', $id);
        $row     = Workflow::stage((int) $project['id'], $stage);
        if (! $row || ! Workflow::canAct($row)) {
            throw new \App\Exceptions\ForbiddenException();
        }
        if ($project['status'] !== 'active' && ! ($stage === 'survey' && $project['status'] === 'on_hold')) {
            return $this->back('This project is ' . $project['status'] . '.');
        }
        $action = $this->request->getPost('action');
        $notes  = $this->request->getPost('notes');

        $db = db_connect();
        try {
            $data = $this->stageData($stage, (int) $project['id'], $row['data']);
        } catch (RuntimeException $e) {
            return $this->back($e->getMessage());
        }
        $db->transException(true)->transStart();
        try {
            if ($stage === 'survey' && $action === 'reject') {
                Workflow::rejectSurvey((int) $project['id'], trim((string) $notes) ?: 'Site not suitable');
            } elseif ($action === 'complete') {
                Workflow::complete((int) $project['id'], $stage, $data, $notes);
                $this->afterComplete($project, $stage, $data);
            } else {
                Workflow::save((int) $project['id'], $stage, $data, $notes);
            }
            $db->transComplete();
        } catch (RuntimeException $e) {
            $db->transRollback();

            return $this->back($e->getMessage());
        }

        $msg = match (true) {
            $action === 'reject'   => 'Survey rejected. The project is on hold.',
            $action === 'complete' => Workflow::stageTitle($stage) . ' completed.',
            default                => 'Saved.',
        };

        return redirect()->to('admin/projects/' . $project['id'])->with('message', $msg);
    }

    /** Reads the posted fields for a stage. */
    private function stageData(string $stage, int $projectId, array $current): array
    {
        $p = $this->request->getPost();
        $pick = static fn (array $keys) => array_map(static fn ($k) => is_string($p[$k] ?? null) ? trim($p[$k]) : ($p[$k] ?? null), array_combine($keys, $keys));

        switch ($stage) {
            case 'survey':
                $d = $pick(['survey_date', 'roof_type', 'roof_area', 'shading', 'structure_height', 'recommended_kw', 'earthing_point', 'cable_length']);
                $d['approved'] = ($p['action'] ?? '') === 'complete' ? 'yes' : ($current['approved'] ?? '');

                return $d;

            case 'installation':
                return $pick(['installation_date', 'inverter_make', 'inverter_serial', 'panel_make', 'panel_count', 'panel_serials']);

            case 'dcr':
                return $pick(['dcr_number', 'dcr_date', 'submission_ref', 'submission_date']);

            case 'eb_process':
                $steps   = $current['steps'] ?? [];
                $paid    = Workflow::invoicePaid($projectId);
                $missing = Documents::missingRequired((int) db_connect()->table('projects')->select('customer_id')->where('id', $projectId)->get()->getRow()->customer_id);
                foreach (config('Workflow')->ebSteps as $key => $title) {
                    $date = trim((string) ($p['step_date'][$key] ?? ''));
                    if (! empty($p['steps'][$key])) {
                        if ($key === 'eb_charges_paid' && ! $paid) {
                            throw new RuntimeException('EB charges can be marked paid only after the full invoice amount is received.');
                        }
                        if ($key === 'pcr_uploaded' && $missing) {
                            throw new RuntimeException('Upload the customer documents first (needed for the PCR). Missing: ' . implode(', ', $missing) . '.');
                        }
                        $steps[$key] = $date ?: ($steps[$key] ?? date('Y-m-d'));
                    } else {
                        unset($steps[$key]);
                    }
                }

                $sub = $this->request->getPost('eb_submission_status');

                return ['steps' => $steps, 'eb_submission_status' => in_array($sub, ['yes', 'no'], true) ? $sub : 'no']
                    + $pick(['application_no', 'sanction_no', 'eb_charges_amount', 'amc_plan_id']);

            case 'subsidy':
                $d = $pick(['status', 'application_no', 'applied_date', 'subsidy_amount', 'received_date']);
                if (! array_key_exists($d['status'], config('Workflow')->subsidyStatuses)) {
                    $d['status'] = $current['status'] ?? 'not_applied';
                }

                return $d;
        }

        return [];
    }

    private function afterComplete(array $project, string $stage, array $data): void
    {
        $db       = db_connect();
        $customer = $db->table('customers')->where('id', $project['customer_id'])->get()->getRowArray();
        $vars     = ['project_no' => $project['project_no'], 'capacity_kw' => qty($project['capacity_kw'])];

        if ($stage === 'survey') {
            Messenger::customer('survey_approved', $customer, $vars, 'Site survey approved – ' . $project['project_no'],
                "Dear {$customer['name']},\n\nYour site survey is complete and approved. We will now prepare the material for installation.", false);
        }
        if ($stage === 'installation') {
            Messenger::customer('installation_completed', $customer, $vars, 'Installation completed – ' . $project['project_no'],
                "Dear {$customer['name']},\n\nThe installation of your " . qty($project['capacity_kw']) . " kW solar system is complete. We will now complete the EB / net-meter process.");
        }
        if ($stage === 'eb_process') {
            // Commissioning date starts the AMC.
            $commissioned = $data['steps']['meter_installed'] ?? date('Y-m-d');
            $db->table('projects')->where('id', $project['id'])->update(['commissioned_on' => $commissioned]);
            $contract = Amc::createForProject((int) $project['id'], (int) ($data['amc_plan_id'] ?? 0) ?: null, $commissioned);
            Messenger::customer('project_commissioned', $customer, $vars + [
                'commissioned_on' => fdate($commissioned), 'amc_end' => $contract ? fdate($contract['end_date']) : '-',
            ], 'Solar system commissioned – ' . $project['project_no'],
                "Dear {$customer['name']},\n\nYour solar system was commissioned on " . fdate($commissioned) . '.'
                . ($contract ? "\nYour AMC is active until " . fdate($contract['end_date']) . '. We will remind you before each service visit.' : ''));
        }
        if ($stage === 'subsidy' && ($data['status'] ?? '') === 'redeemed') {
            Messenger::customer('subsidy_received', $customer, $vars, 'Subsidy received – ' . $project['project_no'],
                "Dear {$customer['name']},\n\nWe have confirmed on the PM Surya Ghar portal that your subsidy has been credited. Thank you for choosing us.", false);
        }
    }

    /** Uploads photos / documents for a stage. */
    public function upload($id)
    {
        $project = $this->findOr404('projects', $id);
        $stage   = (string) $this->request->getPost('stage');
        $row     = Workflow::stage((int) $project['id'], $stage);
        if (! $row || ! Workflow::canAct($row)) {
            throw new \App\Exceptions\ForbiddenException();
        }
        $label = preg_replace('/[^a-z0-9_]/', '', (string) $this->request->getPost('label')) ?: 'photo';
        $n     = 0;
        foreach ($this->request->getFileMultiple('files') ?? [] as $file) {
            if (! $file->isValid() || $file->hasMoved()) {
                continue;
            }
            $ext = strtolower($file->getClientExtension() ?: $file->guessExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf'], true) || $file->getSizeByUnit('mb') > 10) {
                return $this->back('Only images / PDF up to 10 MB.');
            }
            $name = $label . '-' . time() . $n . '.' . $ext;
            $file->move(WRITEPATH . 'uploads/projects/' . $project['id'], $name);
            db_connect()->table('project_files')->insert([
                'project_id' => $project['id'], 'stage' => $stage, 'label' => $label,
                'file_path' => 'projects/' . $project['id'] . '/' . $name, 'original_name' => $file->getClientName(),
                'uploaded_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $n++;
        }
        if (! $n) {
            return $this->back('Choose a file to upload.');
        }

        $msg = $n . ' file(s) uploaded.';
        // EB documents tick their step automatically (dated today) when the step's rule allows it.
        $stepFor = ['pcr_copy' => 'pcr_uploaded', 'sanction_letter' => 'sanction', 'eb_receipt' => 'eb_charges_paid', 'commissioning_report' => 'meter_installed'];
        if ($stage === 'eb_process' && isset($stepFor[$label]) && $row['status'] === 'active') {
            $step  = $stepFor[$label];
            $title = config('Workflow')->ebSteps[$step];
            $steps = $row['data']['steps'] ?? [];
            $block = match ($step) {
                'eb_charges_paid' => Workflow::invoicePaid((int) $project['id']) ? null : 'the invoice is not fully paid',
                'pcr_uploaded'    => ($m = Documents::missingRequired((int) $project['customer_id'])) ? 'documents missing: ' . implode(', ', $m) : null,
                default           => null,
            };
            if (empty($steps[$step]) && $block === null) {
                $steps[$step] = date('Y-m-d');
                Workflow::save((int) $project['id'], 'eb_process', ['steps' => $steps]);
                $msg .= ' Step ticked: "' . $title . '".';
            } elseif ($block !== null) {
                $msg .= ' "' . $title . '" not ticked yet – ' . $block . '.';
            }
        }

        return redirect()->to('admin/projects/' . $project['id'] . '#stage-' . $stage)->with('message', $msg);
    }

    public function reopenSurvey($id)
    {
        $this->requirePermission('projects.manage', 'survey.update');
        $project = $this->findOr404('projects', $id);
        if ($project['status'] !== 'on_hold') {
            return $this->back('Only a project on hold can be reopened.');
        }
        Workflow::reopenSurvey((int) $project['id']);

        return redirect()->back()->with('message', 'Survey reopened.');
    }

    public function status($id)
    {
        $this->requirePermission('projects.manage');
        $project = $this->findOr404('projects', $id);
        $to      = $this->request->getPost('status');
        if (! in_array($to, ['active', 'on_hold', 'cancelled'], true) || $project['status'] === 'completed') {
            return $this->back('Not allowed.');
        }
        db_connect()->table('projects')->where('id', $project['id'])->update(['status' => $to, 'updated_at' => date('Y-m-d H:i:s')]);
        Workflow::log('project', (int) $project['id'], 'status', 'Project status changed to ' . $to);

        return redirect()->back()->with('message', 'Project status updated.');
    }

    /** Admin: delete a project (stages, photos, dispatches – stock returned – and AMC). The invoice stays. */
    public function delete($id)
    {
        $this->requirePermission('entries.delete');
        $p  = $this->findOr404('projects', $id);
        $db = db_connect();
        $db->transException(true)->transStart();
        try {
            \App\Libraries\Removal::deleteProject((int) $p['id']);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back('Could not delete: ' . $e->getMessage());
        }
        Workflow::log('project', (int) $p['id'], 'deleted', 'Project ' . $p['project_no'] . ' deleted');

        return redirect()->to('admin/projects')->with('message', 'Project ' . $p['project_no'] . ' deleted. Its invoice is kept.');
    }
}
