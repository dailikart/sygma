<?php

namespace App\Controllers\Admin;

use App\Libraries\Ledger;
use App\Libraries\Numbering;
use App\Libraries\Payroll;
use App\Libraries\Pdf;
use App\Libraries\Workflow;

/**
 * Payroll → Salary runs: one per month.
 * Draft (calculated from attendance, extras editable) → Approved (journal posted,
 * attendance locked, payslips final) → Paid (payment voucher from a bank / cash account).
 */
class PayrollRuns extends AdminController
{
    public function index()
    {
        $this->requirePermission('payroll.view', 'payroll.manage');
        $rows = db_connect()->table('payroll_runs r')->select('r.*, (SELECT COUNT(*) FROM ' . db_connect()->prefixTable('payroll_lines') . ' l WHERE l.run_id = r.id) AS staff', false)
            ->orderBy('r.month', 'DESC')->get()->getResultArray();

        return $this->render('payroll/runs', ['rows' => $rows, 'suggest' => $this->suggestMonth($rows)]);
    }

    public function create()
    {
        $this->requirePermission('payroll.manage');
        $m = (string) $this->request->getPost('month');
        if (! preg_match('/^\d{4}-\d{2}$/', $m)) {
            return $this->back('Choose the month.');
        }
        $month = $m . '-01';
        $db    = db_connect();
        if ($r = $db->table('payroll_runs')->where('month', $month)->get()->getRowArray()) {
            return redirect()->to('admin/payroll/' . $r['id'])->with('warning', 'The salary run for ' . date('F Y', strtotime($month)) . ' already exists.');
        }
        if (! Payroll::employeesFor($month)) {
            return $this->back('No employees for ' . date('F Y', strtotime($month)) . '. Add employees first.');
        }
        $now = date('Y-m-d H:i:s');
        $db->table('payroll_runs')->insert([
            'run_no' => Numbering::next('payroll_runs', 'run_no', 'SAL', date('Y-m-t', strtotime($month))), 'month' => $month, 'status' => 'draft',
            'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        Payroll::recalculate($id);
        Workflow::log('payroll', $id, 'created', 'Salary run for ' . date('F Y', strtotime($month)) . ' created');

        return redirect()->to('admin/payroll/' . $id)->with('message', 'Salaries calculated from the attendance. Check, add incentives or deductions, then approve.');
    }

    public function show($id)
    {
        $this->requirePermission('payroll.view', 'payroll.manage');
        $run   = $this->findOr404('payroll_runs', $id);
        $lines = db_connect()->table('payroll_lines')->where('run_id', $run['id'])->orderBy('emp_code')->get()->getResultArray();

        return $this->render('payroll/run', [
            'run' => $run, 'lines' => $lines, 'accounts' => options('accounts'),
            'noAtt' => count(array_filter($lines, static fn ($l) => ! $l['att_entered'])),
            'log' => db_connect()->table('activity_log')->where(['entity' => 'payroll', 'entity_id' => $run['id']])->orderBy('id', 'DESC')->get()->getResultArray(),
        ]);
    }

    /** Draft: save incentives / other deductions / remarks typed per employee, then recalculate. */
    public function update($id)
    {
        $this->requirePermission('payroll.manage');
        $run = $this->draft($id);
        $db  = db_connect();
        foreach ((array) $this->request->getPost('l') as $lineId => $v) {
            $db->table('payroll_lines')->where(['id' => (int) $lineId, 'run_id' => $run['id']])->update([
                'other_earnings' => round(max(0, (float) ($v['other_earnings'] ?? 0)), 2),
                'other_deductions' => round(max(0, (float) ($v['other_deductions'] ?? 0)), 2),
                'remarks' => trim((string) ($v['remarks'] ?? '')) ?: null,
            ]);
        }
        Payroll::recalculate((int) $run['id']);

        return redirect()->to('admin/payroll/' . $run['id'])->with('message', 'Saved and recalculated.');
    }

    public function recalc($id)
    {
        $this->requirePermission('payroll.manage');
        $run = $this->draft($id);
        Payroll::recalculate((int) $run['id']);

        return redirect()->to('admin/payroll/' . $run['id'])->with('message', 'Recalculated with the latest attendance, salaries and settings.');
    }

    public function approve($id)
    {
        $this->requirePermission('payroll.manage');
        $run = $this->draft($id);
        if ((float) $run['gross'] <= 0) {
            return $this->back('Nothing to pay in this run.');
        }
        $db = db_connect();
        $db->transException(true)->transStart();
        Payroll::post((int) $run['id']);
        $db->table('payroll_runs')->where('id', $run['id'])->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => date('Y-m-d H:i:s')]);
        $db->transComplete();
        Workflow::log('payroll', (int) $run['id'], 'approved', 'Approved: net ' . money($run['net']) . ', journal ' . $run['run_no'] . ' posted');

        return redirect()->to('admin/payroll/' . $run['id'])->with('message', 'Approved. The salary journal is posted and payslips are final. Pay the salaries when transferred.');
    }

    public function unapprove($id)
    {
        $this->requirePermission('payroll.manage');
        $run = $this->findOr404('payroll_runs', $id);
        if ($run['status'] !== 'approved') {
            return $this->back($run['status'] === 'paid' ? 'Salaries are marked paid. Undo the payment first.' : 'This run is not approved.');
        }
        $db = db_connect();
        $db->transException(true)->transStart();
        Payroll::unpost((int) $run['id']);
        $db->table('payroll_runs')->where('id', $run['id'])->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
        $db->transComplete();
        Workflow::log('payroll', (int) $run['id'], 'unapproved', 'Approval undone; journal removed');

        return redirect()->to('admin/payroll/' . $run['id'])->with('message', 'Back to draft. You can change attendance and amounts again.');
    }

    public function pay($id)
    {
        $this->requirePermission('payroll.manage');
        $run  = $this->findOr404('payroll_runs', $id);
        $acc  = (int) $this->request->getPost('account_id');
        $date = (string) $this->request->getPost('paid_on');
        if ($run['status'] !== 'approved') {
            return $this->back('Approve the salaries first.');
        }
        if (! $acc || ! strtotime($date)) {
            return $this->back('Choose the account the salaries were paid from and the date.');
        }
        $db = db_connect();
        $db->transException(true)->transStart();
        $db->table('payroll_runs')->where('id', $run['id'])->update(['status' => 'paid', 'account_id' => $acc, 'paid_on' => $date]);
        Payroll::postPayment((int) $run['id']);
        $db->transComplete();
        Workflow::log('payroll', (int) $run['id'], 'paid', 'Salaries ' . money($run['net']) . ' paid on ' . fdate($date));

        return redirect()->to('admin/payroll/' . $run['id'])->with('message', 'Marked paid. The payment voucher is posted.');
    }

    public function unpay($id)
    {
        $this->requirePermission('payroll.manage');
        $run = $this->findOr404('payroll_runs', $id);
        if ($run['status'] !== 'paid') {
            return $this->back('Not marked paid.');
        }
        Ledger::unpost('payroll_payment', (int) $run['id']);
        db_connect()->table('payroll_runs')->where('id', $run['id'])->update(['status' => 'approved', 'account_id' => null, 'paid_on' => null]);
        Workflow::log('payroll', (int) $run['id'], 'unpaid', 'Payment undone');

        return redirect()->to('admin/payroll/' . $run['id'])->with('message', 'Payment undone.');
    }

    public function delete($id)
    {
        $this->requirePermission('payroll.manage');
        $run = $this->draft($id);
        db_connect()->table('payroll_lines')->where('run_id', $run['id'])->delete();
        db_connect()->table('payroll_runs')->where('id', $run['id'])->delete();
        Workflow::log('payroll', (int) $run['id'], 'deleted', 'Draft salary run ' . $run['run_no'] . ' deleted');

        return redirect()->to('admin/payroll')->with('message', 'Draft deleted.');
    }

    /** Payslip PDF of one employee (or every employee with ?all). */
    public function payslip($id, $employeeId = null)
    {
        $run = $this->findOr404('payroll_runs', $id);
        $b   = db_connect()->table('payroll_lines l')->select('l.*, e.pan, e.uan, e.esi_no, e.bank_name, e.bank_account, e.ifsc, e.join_date, e.user_id')
            ->join('employees e', 'e.id = l.employee_id')->where('l.run_id', $run['id'])->orderBy('l.emp_code');
        if ($employeeId !== null) {
            $b->where('l.employee_id', (int) $employeeId);
        }
        $lines = $b->get()->getResultArray();
        if (! $lines) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        // Staff may open their own payslip; payroll users any.
        $own = $employeeId !== null && (int) $lines[0]['user_id'] === (int) auth()->id() && $run['status'] !== 'draft';
        if (! $own) {
            $this->requirePermission('payroll.view', 'payroll.manage');
        }
        $name = 'Payslip-' . date('M-Y', strtotime($run['month'])) . ($employeeId !== null ? '-' . $lines[0]['emp_code'] : '');
        Pdf::stream('pdf/payslip', ['run' => $run, 'lines' => $lines], $name, (bool) $this->request->getGet('download'));
    }

    /** Emails each employee their payslip (approved / paid runs). */
    public function email($id)
    {
        $this->requirePermission('payroll.manage');
        $run = $this->findOr404('payroll_runs', $id);
        if ($run['status'] === 'draft') {
            return $this->back('Approve the salaries before sending payslips.');
        }
        $sent = $skipped = 0;
        $errors = [];
        foreach (db_connect()->table('payroll_lines l')->select('l.*, e.email, e.pan, e.uan, e.esi_no, e.bank_name, e.bank_account, e.ifsc, e.join_date, e.user_id')
            ->join('employees e', 'e.id = l.employee_id')->where('l.run_id', $run['id'])->get()->getResultArray() as $l) {
            if (! filter_var($l['email'], FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            $file = Pdf::toFile('pdf/payslip', ['run' => $run, 'lines' => [$l]], 'Payslip-' . date('M-Y', strtotime($run['month'])) . '-' . $l['emp_code']);
            $r    = \App\Libraries\Mailer::send($l['email'], 'Payslip for ' . date('F Y', strtotime($run['month'])) . ' – ' . setting('Solar.companyName'),
                'Dear ' . $l['name'] . ',<br><br>Please find your payslip for ' . date('F Y', strtotime($run['month'])) . ' attached. Net pay: ' . money($l['net']) . '.<br><br>Regards,<br>' . esc((string) setting('Solar.companyName')),
                [['path' => $file, 'name' => basename($file)]]);
            ($r['ok'] ?? false) ? $sent++ : ($errors[] = $l['name'] . ': ' . ($r['error'] ?? 'not sent'));
        }
        Workflow::log('payroll', (int) $run['id'], 'payslips_emailed', "Payslips emailed: {$sent}; without email: {$skipped}");

        return redirect()->to('admin/payroll/' . $run['id'])->with($errors ? 'error' : 'message', "Payslips emailed: {$sent}." . ($skipped ? " {$skipped} employee(s) have no email." : '') . ($errors ? ' Failed: ' . implode('; ', array_slice($errors, 0, 3)) : ''));
    }

    /** Excel: bank transfer list (account, IFSC, net) or the full salary register. */
    public function export($id, $what)
    {
        $this->requirePermission('payroll.view', 'payroll.manage');
        $run   = $this->findOr404('payroll_runs', $id);
        $lines = db_connect()->table('payroll_lines l')->select('l.*, e.bank_name, e.bank_account, e.ifsc, e.uan, e.esi_no, e.pan')->join('employees e', 'e.id = l.employee_id')
            ->where('l.run_id', $run['id'])->orderBy('l.emp_code')->get()->getResultArray();
        $m = date('M-Y', strtotime($run['month']));
        if ($what === 'bank') {
            return (new Reports())->initControllerFrom($this)->excelOut('Salary-transfer-' . $m, ['Emp code', 'Name', 'Bank', 'Account number', 'IFSC', 'Net pay', 'Narration'],
                array_map(static fn ($l) => [$l['emp_code'], $l['name'], (string) $l['bank_name'], (string) $l['bank_account'], (string) $l['ifsc'], (float) $l['net'], 'Salary ' . $m], $lines));
        }

        return (new Reports())->initControllerFrom($this)->excelOut('Salary-register-' . $m,
            ['Code', 'Name', 'Designation', 'Paid days', 'OT hrs', 'Basic', 'HRA', 'Conveyance', 'Special', 'Wages', 'OT', 'Other earnings', 'Gross',
                'PF wage', 'PF (emp)', 'ESI (emp)', 'PT', 'TDS', 'Advance', 'Other ded.', 'Total ded.', 'Net', 'PF (employer)', 'ESI (employer)', 'UAN', 'ESI no', 'PAN'],
            array_map(static fn ($l) => [$l['emp_code'], $l['name'], (string) $l['designation'], (float) $l['paid_days'], (float) $l['ot_hours'], (float) $l['basic'], (float) $l['hra'],
                (float) $l['conveyance'], (float) $l['special'], (float) $l['wages'], (float) $l['ot_amount'], (float) $l['other_earnings'], (float) $l['gross'], (float) $l['pf_wage'],
                (float) $l['pf_employee'], (float) $l['esi_employee'], (float) $l['pt'], (float) $l['tds'], (float) $l['advance_recovery'], (float) $l['other_deductions'],
                (float) $l['total_deductions'], (float) $l['net'], (float) $l['pf_employer'], (float) $l['esi_employer'], (string) $l['uan'], (string) $l['esi_no'], (string) $l['pan']], $lines));
    }

    /** Staff: my payslips (employee linked to my login). */
    public function mine()
    {
        $db  = db_connect();
        $emp = $db->table('employees')->where(['user_id' => auth()->id(), 'deleted_at' => null])->get()->getRowArray();
        $rows = $emp ? $db->table('payroll_lines l')->select('l.*, r.month, r.status')->join('payroll_runs r', 'r.id = l.run_id')
            ->where('l.employee_id', $emp['id'])->where('r.status <>', 'draft')->orderBy('r.month', 'DESC')->get()->getResultArray() : [];

        return $this->render('payroll/mine', ['emp' => $emp, 'rows' => $rows]);
    }

    private function draft($id): array
    {
        $run = $this->findOr404('payroll_runs', $id);
        if ($run['status'] !== 'draft') {
            throw new \App\Exceptions\ForbiddenException('This salary run is approved. Undo the approval first.');
        }

        return $run;
    }

    /** The month after the latest run, or last month. */
    private function suggestMonth(array $rows): string
    {
        return $rows ? date('Y-m', strtotime($rows[0]['month'] . ' +1 month')) : date('Y-m', strtotime('first day of last month'));
    }
}
