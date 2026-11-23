<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Installation workflow engine.
 *
 * Invoice → project → stages (survey → dispatch → delivery → installation →
 * EB / net meter → subsidy*) → completed.  (*subsidy customers only)
 */
class Workflow
{
    public static function config(): \Config\Workflow
    {
        return config('Workflow');
    }

    public static function stageTitle(string $stage): string
    {
        return self::config()->stages[$stage]['title'] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    /**
     * Creates the project and its stages for an invoice and activates the first stage.
     */
    public static function createProject(int $invoiceId, ?int $kitId = null): int
    {
        $db       = db_connect();
        $invoice  = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        $customer = $db->table('customers')->where('id', $invoice['customer_id'])->get()->getRowArray();

        // Pick the kit for the system size (exact kW match, else the next bigger kit).
        $kitQ = $db->table('kits')->where('is_active', 1)->where('capacity_kw >=', $invoice['capacity_kw']);
        if ($invoice['installation_type_id']) {
            $kitQ->groupStart()->where('installation_type_id', $invoice['installation_type_id'])->orWhere('installation_type_id', null)->groupEnd()
                ->orderBy('installation_type_id IS NULL', '', false);
        }
        $kit = $kitId ? $db->table('kits')->where('id', $kitId)->get()->getRowArray() : null;
        $kit ??= $kitQ->orderBy('capacity_kw', 'ASC')->limit(1)->get()->getRowArray();

        $db->table('projects')->insert([
            'project_no'    => Numbering::next('projects', 'project_no', setting('Solar.projectPrefix'), $invoice['invoice_date']),
            'customer_id'   => $customer['id'],
            'invoice_id'    => $invoiceId,
            'customer_type' => $customer['type'],
            'subsidy_type'  => $invoice['subsidy_type'],
            'capacity_kw'   => $invoice['capacity_kw'],
            'installation_type_id' => $invoice['installation_type_id'],
            'kit_id'        => $kit['id'] ?? null,
            'site_address'  => trim(($customer['address'] ?? '') . ', ' . ($customer['city'] ?? ''), ', '),
            'status'        => 'active',
            'created_by'    => auth()->id(),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $projectId = (int) $db->insertID();
        $db->table('invoices')->where('id', $invoiceId)->update(['project_id' => $projectId]);

        $seq = 0;
        foreach (self::config()->stages as $key => $stage) {
            if (! empty($stage['subsidy_only']) && $invoice['subsidy_type'] !== 'subsidy') {
                continue;
            }
            $db->table('project_stages')->insert([
                'project_id' => $projectId,
                'stage'      => $key,
                'sequence'   => ++$seq,
                'status'     => 'pending',
                'data'       => json_encode($key === 'subsidy' ? ['status' => 'not_applied'] : []),
            ]);
        }

        self::log('project', $projectId, 'created', 'Project created from invoice ' . $invoice['invoice_no']);
        self::activateNext($projectId);

        return $projectId;
    }

    public static function stages(int $projectId): array
    {
        $rows = db_connect()->table('project_stages')->where('project_id', $projectId)
            ->orderBy('sequence')->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['data'] = json_decode($r['data'] ?? '[]', true) ?: [];
        }

        return $rows;
    }

    public static function stage(int $projectId, string $stage): ?array
    {
        foreach (self::stages($projectId) as $s) {
            if ($s['stage'] === $stage) {
                return $s;
            }
        }

        return null;
    }

    /** Can the logged-in user work on this stage? */
    public static function canAct(array $stageRow): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        $perm = self::config()->stages[$stageRow['stage']]['permission'] ?? null;

        return $user->can('projects.manage')
            || ($perm && $user->can($perm))
            || ((int) $stageRow['assigned_to'] === (int) $user->id);
    }

    /** Activates the first pending stage, or completes the project when none are left. */
    public static function activateNext(int $projectId): void
    {
        $db   = db_connect();
        $next = $db->table('project_stages')->where(['project_id' => $projectId, 'status' => 'pending'])
            ->orderBy('sequence')->limit(1)->get()->getRowArray();

        if ($next === null) {
            $db->table('projects')->where('id', $projectId)->update([
                'status' => 'completed', 'current_stage' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            self::log('project', $projectId, 'completed', 'All stages completed. Project closed.');
            self::notifyCreator($projectId, 'Project completed', 'All stages are completed and the project is closed.');

            return;
        }

        $db->table('project_stages')->where('id', $next['id'])->update([
            'status' => 'active', 'started_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('projects')->where('id', $projectId)->update([
            'current_stage' => $next['stage'], 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::notifyStage($projectId, $next['stage'], $next['assigned_to'] ? (int) $next['assigned_to'] : null);
    }

    /**
     * Marks a stage done (after its gate checks) and moves to the next stage.
     */
    public static function complete(int $projectId, string $stage, array $data = [], ?string $notes = null): void
    {
        $row = self::stage($projectId, $stage);
        if ($row === null || $row['status'] !== 'active') {
            throw new RuntimeException('This stage is not active.');
        }

        $merged = array_merge($row['data'], $data);
        self::checkGate($projectId, $stage, $merged);

        db_connect()->table('project_stages')->where('id', $row['id'])->update([
            'status'       => 'done',
            'data'         => json_encode($merged),
            'notes'        => $notes ?? $row['notes'],
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => auth()->id(),
        ]);
        self::log('project', $projectId, 'stage_done', self::stageTitle($stage) . ' completed');
        self::activateNext($projectId);
    }

    /** Saves stage data without completing it (partial progress). */
    public static function save(int $projectId, string $stage, array $data, ?string $notes = null): void
    {
        $row = self::stage($projectId, $stage);
        if ($row === null) {
            throw new RuntimeException('Stage not found.');
        }
        db_connect()->table('project_stages')->where('id', $row['id'])->update([
            'data'  => json_encode(array_merge($row['data'], $data)),
            'notes' => $notes ?? $row['notes'],
        ]);
    }

    /** Survey rejected: project goes on hold and the creator / admins are told. */
    public static function rejectSurvey(int $projectId, string $reason): void
    {
        $row = self::stage($projectId, 'survey');
        db_connect()->table('project_stages')->where('id', $row['id'])->update([
            'status' => 'rejected', 'notes' => $reason,
            'data'   => json_encode(array_merge($row['data'], ['approved' => 'no'])),
            'completed_at' => date('Y-m-d H:i:s'), 'completed_by' => auth()->id(),
        ]);
        db_connect()->table('projects')->where('id', $projectId)->update(['status' => 'on_hold', 'updated_at' => date('Y-m-d H:i:s')]);
        self::log('project', $projectId, 'survey_rejected', 'Site survey rejected: ' . $reason);
        self::notifyCreator($projectId, 'Site survey rejected', $reason);
        Notifier::role('admin', 'Site survey rejected', $reason, 'admin/projects/' . $projectId);
    }

    /** Re-opens a rejected survey (e.g. after the customer fixes the site). */
    public static function reopenSurvey(int $projectId): void
    {
        $row = self::stage($projectId, 'survey');
        db_connect()->table('project_stages')->where('id', $row['id'])->update([
            'status' => 'active', 'started_at' => date('Y-m-d H:i:s'), 'completed_at' => null,
        ]);
        db_connect()->table('projects')->where('id', $projectId)->update(['status' => 'active', 'current_stage' => 'survey']);
        self::log('project', $projectId, 'survey_reopened', 'Site survey reopened');
        self::notifyStage($projectId, 'survey', $row['assigned_to'] ? (int) $row['assigned_to'] : null);
    }

    public static function assign(int $stageId, ?int $userId, ?string $plannedDate = null): void
    {
        $db  = db_connect();
        $row = $db->table('project_stages')->where('id', $stageId)->get()->getRowArray();
        $db->table('project_stages')->where('id', $stageId)->update([
            'assigned_to' => $userId ?: null, 'planned_date' => $plannedDate ?: null,
        ]);
        if ($userId && in_array($row['status'], ['active', 'pending'], true)) {
            $project = $db->table('projects')->where('id', $row['project_id'])->get()->getRowArray();
            Notifier::users([$userId], 'Assigned: ' . self::stageTitle($row['stage']) . ' – ' . $project['project_no'],
                'You have been assigned this stage' . ($plannedDate ? ' for ' . date('d-m-Y', strtotime($plannedDate)) : '') . '.',
                'admin/projects/' . $row['project_id']);
        }
        self::log('project', (int) $row['project_id'], 'assigned', self::stageTitle($row['stage']) . ' assigned to user #' . (int) $userId);
    }

    /**
     * Rules a stage must meet before it can be completed.
     */
    public static function checkGate(int $projectId, string $stage, array $data): void
    {
        $db = db_connect();

        switch ($stage) {
            case 'survey':
                if (($data['approved'] ?? '') !== 'yes') {
                    throw new RuntimeException('Approve the site to complete the survey.');
                }
                break;

            case 'dispatch':
                if (! $db->table('dispatches')->where('project_id', $projectId)->whereIn('status', ['dispatched', 'delivered'])->countAllResults()) {
                    throw new RuntimeException('Dispatch the material first.');
                }
                break;

            case 'delivery':
                if ($db->table('dispatches')->where(['project_id' => $projectId, 'status' => 'dispatched'])->countAllResults()) {
                    throw new RuntimeException('Mark all dispatches as delivered first.');
                }
                if (! self::hasFile($projectId, 'delivery', 'delivery_photo')) {
                    throw new RuntimeException('Upload the delivery photo.');
                }
                break;

            case 'installation':
                $missing = [];
                foreach (['panel_photo' => 'panel photo', 'net_meter_agreement' => 'net-meter agreement', 'panel_serial_photo' => 'panel serial number photo'] as $label => $name) {
                    if (! self::hasFile($projectId, 'installation', $label)) {
                        $missing[] = $name;
                    }
                }
                if (trim($data['inverter_serial'] ?? '') === '') {
                    $missing[] = 'inverter serial number';
                }
                if (trim($data['panel_serials'] ?? '') === '') {
                    $missing[] = 'panel serial numbers';
                }
                if ($missing !== []) {
                    throw new RuntimeException('Still needed: ' . implode(', ', $missing) . '.');
                }
                break;

            case 'dcr':
                if (! self::hasFile($projectId, 'dcr', 'dcr_certificate')) {
                    throw new RuntimeException('Upload the DCR certificate.');
                }
                if (trim($data['submission_ref'] ?? '') === '') {
                    throw new RuntimeException('Enter the installation submission / portal reference number.');
                }
                break;

            case 'eb_process':
                $pending = [];
                foreach (self::config()->ebSteps as $key => $title) {
                    if (empty($data['steps'][$key])) {
                        $pending[] = $title;
                    }
                }
                if ($pending) {
                    throw new RuntimeException('Tick every EB step (with its date) before completing. Pending: ' . implode('; ', $pending) . '. Use "Save progress" as each step happens.');
                }
                if (! self::invoicePaid($projectId)) {
                    throw new RuntimeException('The invoice must be fully paid before the EB / net-meter process can be completed.');
                }
                break;

            case 'subsidy':
                if (! in_array($data['status'] ?? '', self::config()->subsidyFinal, true)) {
                    throw new RuntimeException('Set the subsidy status to Received, Rejected or Refunded to close.');
                }
                break;
        }
    }

    public static function hasFile(int $projectId, string $stage, string $label): bool
    {
        return db_connect()->table('project_files')
            ->where(['project_id' => $projectId, 'stage' => $stage, 'label' => $label])
            ->countAllResults() > 0;
    }

    /** Is the project's invoice fully paid? (needed before EB charges are paid) */
    public static function invoicePaid(int $projectId): bool
    {
        $p = db_connect()->table('projects p')->select('i.total, i.amount_paid')
            ->join('invoices i', 'i.id = p.invoice_id')->where('p.id', $projectId)->get()->getRowArray();

        return $p && (float) $p['amount_paid'] + 0.001 >= (float) $p['total'];
    }

    private static function notifyStage(int $projectId, string $stage, ?int $assignedTo): void
    {
        $db      = db_connect();
        $project = $db->table('projects p')->select('p.project_no, p.capacity_kw, c.name, c.city')
            ->join('customers c', 'c.id = p.customer_id')->where('p.id', $projectId)->get()->getRowArray();
        $title = self::stageTitle($stage) . ' – ' . $project['project_no'];
        $msg   = sprintf('%s, %s – %s kW. This stage is now ready for you.', $project['name'], $project['city'] ?? '', rtrim(rtrim($project['capacity_kw'], '0'), '.'));
        $link  = 'admin/projects/' . $projectId;

        if ($assignedTo) {
            Notifier::users([$assignedTo], $title, $msg, $link);
        } else {
            Notifier::role(self::config()->stages[$stage]['role'], $title, $msg, $link);
        }
    }

    private static function notifyCreator(int $projectId, string $title, string $msg): void
    {
        $p = db_connect()->table('projects')->select('created_by, project_no')->where('id', $projectId)->get()->getRowArray();
        if ($p && $p['created_by']) {
            Notifier::users([(int) $p['created_by']], $title . ' – ' . $p['project_no'], $msg, 'admin/projects/' . $projectId);
        }
    }

    public static function log(string $entity, int $entityId, string $action, string $description): void
    {
        db_connect()->table('activity_log')->insert([
            'user_id' => auth()->id(), 'entity' => $entity, 'entity_id' => $entityId,
            'action' => $action, 'description' => $description, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
