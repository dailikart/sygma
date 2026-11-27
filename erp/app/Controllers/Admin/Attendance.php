<?php

namespace App\Controllers\Admin;

use App\Libraries\Payroll;

/** Payroll → Attendance: one sheet per month, a cell per employee and day. */
class Attendance extends AdminController
{
    public function index()
    {
        $this->requirePermission('payroll.view', 'payroll.manage');
        $month = $this->month();
        $db    = db_connect();
        $emps  = Payroll::employeesFor($month);
        $marks = [];
        $ot    = [];
        if ($emps) {
            $ids = array_column($emps, 'id');
            foreach ($db->table('attendance')->select('employee_id, att_date, status')->whereIn('employee_id', $ids)
                ->where('att_date >=', $month)->where('att_date <=', date('Y-m-t', strtotime($month)))->get()->getResultArray() as $r) {
                $marks[$r['employee_id']][(int) substr($r['att_date'], 8, 2)] = $r['status'];
            }
            $ot = array_column($db->table('attendance_months')->whereIn('employee_id', $ids)->where('month', $month)->get()->getResultArray(), 'ot_hours', 'employee_id');
        }
        $run = $db->table('payroll_runs')->where('month', $month)->get()->getRowArray();

        return $this->render('payroll/attendance', [
            'month' => $month, 'emps' => $emps, 'marks' => $marks, 'ot' => $ot,
            'locked' => $run && $run['status'] !== 'draft', 'run' => $run, 'weekOff' => Payroll::config()['week_off'],
        ]);
    }

    public function save()
    {
        $this->requirePermission('payroll.manage');
        $month = $this->month();
        $db    = db_connect();
        $run   = $db->table('payroll_runs')->where('month', $month)->get()->getRowArray();
        if ($run && $run['status'] !== 'draft') {
            return $this->back('The salary for ' . date('F Y', strtotime($month)) . ' is already approved. Undo the approval to change attendance.');
        }
        $days  = (int) date('t', strtotime($month));
        $att   = (array) $this->request->getPost('att');
        $ots   = (array) $this->request->getPost('ot');
        $valid = array_column(Payroll::employeesFor($month), 'id');
        $now   = date('Y-m-d H:i:s');
        $n     = 0;
        $db->transException(true)->transStart();
        foreach ($valid as $empId) {
            $row = (array) ($att[$empId] ?? []);
            $db->table('attendance')->where('employee_id', $empId)->where('att_date >=', $month)->where('att_date <=', date('Y-m-t', strtotime($month)))->delete();
            $batch = [];
            for ($d = 1; $d <= $days; $d++) {
                $s = strtoupper(trim((string) ($row[$d] ?? '')));
                if (isset(Payroll::STATUSES[$s])) {
                    $batch[] = ['employee_id' => $empId, 'att_date' => date('Y-m-', strtotime($month)) . str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'status' => $s, 'ot_hours' => 0, 'updated_by' => auth()->id(), 'updated_at' => $now];
                }
            }
            if ($batch) {
                $db->table('attendance')->insertBatch($batch);
                $n += count($batch);
            }
            $hours = round(max(0, (float) ($ots[$empId] ?? 0)), 2);
            $db->table('attendance_months')->where(['employee_id' => $empId, 'month' => $month])->delete();
            if ($hours > 0) {
                $db->table('attendance_months')->insert(['employee_id' => $empId, 'month' => $month, 'ot_hours' => $hours, 'updated_at' => $now]);
            }
        }
        $db->transComplete();
        if ($run) {
            Payroll::recalculate((int) $run['id']);
        }

        return redirect()->to('admin/attendance?month=' . substr($month, 0, 7))->with('message', 'Attendance saved (' . $n . ' day entries).' . ($run ? ' The draft salary run was recalculated.' : ''));
    }

    private function month(): string
    {
        $m = (string) ($this->request->getGetPost('month') ?: date('Y-m'));

        return preg_match('/^\d{4}-\d{2}$/', $m) ? $m . '-01' : date('Y-m-01');
    }
}
