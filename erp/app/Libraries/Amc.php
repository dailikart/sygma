<?php

namespace App\Libraries;

/**
 * AMC contracts and service visits.
 *
 * Plans are a master (Admin → AMC Plans): each company sets its own duration
 * and service interval (e.g. free 5-year AMC with a visit every 6 months).
 * A contract starts on the commissioning date and its visits are scheduled
 * right away.
 */
class Amc
{
    public static function createForProject(int $projectId, ?int $planId, string $startDate): ?array
    {
        $db = db_connect();
        if ($db->table('amc_contracts')->where('project_id', $projectId)->countAllResults()) {
            return $db->table('amc_contracts')->where('project_id', $projectId)->get()->getRowArray();
        }
        $plan = $planId
            ? $db->table('amc_plans')->where(['id' => $planId, 'is_active' => 1])->get()->getRowArray()
            : $db->table('amc_plans')->where('is_active', 1)->orderBy('is_default', 'DESC')->orderBy('id')->limit(1)->get()->getRowArray();
        if (! $plan) {
            return null; // no AMC plans set up
        }
        $project = $db->table('projects')->where('id', $projectId)->get()->getRowArray();
        $end     = date('Y-m-d', strtotime($startDate . ' +' . (int) $plan['duration_months'] . ' months -1 day'));
        $now     = date('Y-m-d H:i:s');

        $db->table('amc_contracts')->insert([
            'contract_no' => Numbering::next('amc_contracts', 'contract_no', setting('Solar.amcPrefix'), $startDate),
            'project_id' => $projectId, 'customer_id' => $project['customer_id'], 'amc_plan_id' => $plan['id'],
            'start_date' => $startDate, 'end_date' => $end, 'amount' => $plan['is_free'] ? 0 : $plan['price'],
            'status' => 'active', 'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $contractId = (int) $db->insertID();

        $interval = max(1, (int) $plan['interval_months']);
        $n        = 0;
        for ($m = $interval; $m <= (int) $plan['duration_months']; $m += $interval) {
            $db->table('amc_visits')->insert([
                'amc_contract_id' => $contractId, 'visit_no' => ++$n,
                'due_date' => min($end, date('Y-m-d', strtotime($startDate . ' +' . $m . ' months'))),
                'status' => 'scheduled', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        Workflow::log('project', $projectId, 'amc', 'AMC ' . $plan['name'] . ' started (' . $n . ' visits until ' . fdate($end) . ')');

        return $db->table('amc_contracts')->where('id', $contractId)->get()->getRowArray();
    }

    /**
     * Sends reminders for visits due within the reminder window and marks
     * expired contracts. Run daily: php spark amc:remind
     *
     * @return array{reminded:int, expired:int}
     */
    public static function sendReminders(): array
    {
        $db     = db_connect();
        $days   = (int) setting('Solar.amcReminderDays');
        $until  = date('Y-m-d', strtotime('+' . max(0, $days) . ' days'));
        $visits = $db->table('amc_visits v')
            ->select('v.*, ac.contract_no, ac.project_id, p.project_no, c.name, c.phone, c.email, c.address, c.city')
            ->join('amc_contracts ac', 'ac.id = v.amc_contract_id')
            ->join('projects p', 'p.id = ac.project_id')
            ->join('customers c', 'c.id = ac.customer_id')
            ->where(['v.status' => 'scheduled', 'ac.status' => 'active'])
            ->where('v.due_date <=', $until)->where('v.reminded_at', null)
            ->get()->getResultArray();

        foreach ($visits as $v) {
            $title = 'AMC visit due ' . fdate($v['due_date']) . ' – ' . $v['project_no'];
            $msg   = $v['name'] . ', ' . trim(($v['address'] ?? '') . ' ' . ($v['city'] ?? '')) . ' – visit ' . $v['visit_no'];
            $v['assigned_to'] ? Notifier::users([(int) $v['assigned_to']], $title, $msg, 'admin/amc') : Notifier::role('service', $title, $msg, 'admin/amc');
            Messenger::customer('amc_visit_due', ['name' => $v['name'], 'phone' => $v['phone'], 'email' => $v['email']], [
                'project_no' => $v['project_no'], 'due_date' => fdate($v['due_date']), 'visit_no' => $v['visit_no'],
            ], 'Solar AMC service visit on ' . fdate($v['due_date']), "Dear {$v['name']},\n\nYour free AMC service visit (no. {$v['visit_no']}) for your solar system is due on " . fdate($v['due_date']) . '. Our technician will contact you.');
            $db->table('amc_visits')->where('id', $v['id'])->update(['reminded_at' => date('Y-m-d H:i:s')]);
        }

        $db->table('amc_contracts')->where('status', 'active')->where('end_date <', date('Y-m-d'))->update(['status' => 'expired']);

        return ['reminded' => count($visits), 'expired' => $db->affectedRows()];
    }
}
