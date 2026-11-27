<?php

namespace App\Controllers\Admin;

use App\Libraries\Payroll;

/** Payroll → Employees: staff master with salary structure, statutory and bank details. */
class Employees extends AdminController
{
    public const DEPARTMENTS = ['Office', 'Sales', 'Accounts', 'Store', 'Installation', 'Service', 'Survey', 'Management'];

    public function index()
    {
        $this->requirePermission('payroll.view', 'payroll.manage');
        $show = (string) ($this->request->getGet('show') ?? 'active');
        $b    = db_connect()->table('employees e')->select('e.*, u.username')->join('users u', 'u.id = e.user_id', 'left')->where('e.deleted_at', null);
        if ($show !== 'all') {
            $b->where('e.status', $show === 'left' ? 'left' : 'active');
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('e.name', $q)->orLike('e.emp_code', $q)->orLike('e.phone', $q)->orLike('e.designation', $q)->groupEnd();
        }
        if ($d = (string) $this->request->getGet('department')) {
            $b->where('e.department', $d);
        }

        return $this->render('payroll/employees', ['rows' => $b->orderBy('e.emp_code')->get()->getResultArray(), 'show' => $show]);
    }

    public function form($id = null)
    {
        $this->requirePermission('payroll.manage');
        $e = $id ? $this->findOr404('employees', $id) : null;

        return $this->render('payroll/employee_form', [
            'e' => $e, 'code' => $e['emp_code'] ?? Payroll::nextCode(),
            'users' => array_column(db_connect()->table('users')->select('id, username')->where('deleted_at', null)->orderBy('username')->get()->getResultArray(), 'username', 'id'),
        ]);
    }

    public function save($id = null)
    {
        $this->requirePermission('payroll.manage');
        $old = $id ? $this->findOr404('employees', $id) : null;
        if (! $this->validate([
            'emp_code'   => 'required|max_length[20]',
            'name'       => 'required|max_length[150]',
            'pay_type'   => 'required|in_list[monthly,daily]',
            'email'      => 'permit_empty|valid_email',
            'join_date'  => 'permit_empty|valid_date[Y-m-d]',
            'exit_date'  => 'permit_empty|valid_date[Y-m-d]',
            'pan'        => 'permit_empty|regex_match[/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/]',
            'ifsc'       => 'permit_empty|regex_match[/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/]',
        ], ['pan' => ['regex_match' => 'PAN must look like ABCDE1234F.'], 'ifsc' => ['regex_match' => 'IFSC must look like SBIN0001234.']])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p   = $this->request->getPost();
        $db  = db_connect();
        $dup = $db->table('employees')->where('emp_code', trim($p['emp_code']))->where('deleted_at', null);
        if ($id) {
            $dup->where('id !=', (int) $id);
        }
        if ($dup->countAllResults()) {
            return $this->back('Employee code ' . $p['emp_code'] . ' is already used.');
        }
        $s   = static fn ($k) => trim((string) ($p[$k] ?? '')) ?: null;
        $m   = static fn ($k) => round(max(0, (float) ($p[$k] ?? 0)), 2);
        $row = [
            'emp_code' => strtoupper(trim($p['emp_code'])), 'name' => trim($p['name']), 'user_id' => ((int) ($p['user_id'] ?? 0)) ?: null,
            'department' => $s('department'), 'designation' => $s('designation'), 'phone' => $s('phone'), 'email' => $s('email'),
            'gender' => $s('gender'), 'dob' => $s('dob'), 'join_date' => $s('join_date'), 'exit_date' => $s('exit_date'),
            'status' => $s('exit_date') && $s('exit_date') <= date('Y-m-d') ? 'left' : 'active',
            'pay_type' => $p['pay_type'], 'basic' => $m('basic'), 'hra' => $m('hra'), 'conveyance' => $m('conveyance'), 'special' => $m('special'),
            'daily_wage' => $m('daily_wage'), 'pf_applicable' => empty($p['pf_applicable']) ? 0 : 1, 'esi_applicable' => empty($p['esi_applicable']) ? 0 : 1,
            'pt_applicable' => empty($p['pt_applicable']) ? 0 : 1, 'tds_monthly' => $m('tds_monthly'),
            'bank_name' => $s('bank_name'), 'bank_account' => $s('bank_account'), 'ifsc' => $s('ifsc') ? strtoupper($s('ifsc')) : null,
            'pan' => $s('pan') ? strtoupper($s('pan')) : null, 'aadhaar' => $s('aadhaar'), 'uan' => $s('uan'), 'esi_no' => $s('esi_no'),
            'address' => $s('address'), 'notes' => $s('notes'), 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($row['pay_type'] === 'monthly' && $row['basic'] + $row['hra'] + $row['conveyance'] + $row['special'] <= 0) {
            return $this->back('Enter the monthly salary (at least the basic).');
        }
        if ($row['pay_type'] === 'daily' && $row['daily_wage'] <= 0) {
            return $this->back('Enter the daily wage.');
        }
        if ($id) {
            $db->table('employees')->where('id', $id)->update($row);
            $changed = [];
            foreach (['basic', 'hra', 'conveyance', 'special', 'daily_wage'] as $k) {
                if ((float) $old[$k] !== (float) $row[$k]) {
                    $changed[] = $k . ' ' . money($old[$k], false) . ' → ' . money($row[$k], false);
                }
            }
            \App\Libraries\Workflow::log('employee', (int) $id, 'updated', 'Employee ' . $row['name'] . ' updated' . ($changed ? ': ' . implode(', ', $changed) : ''));
        } else {
            $db->table('employees')->insert($row + ['created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s')]);
            $id = (int) $db->insertID();
            \App\Libraries\Workflow::log('employee', $id, 'created', 'Employee ' . $row['emp_code'] . ' ' . $row['name'] . ' added');
        }

        return redirect()->to('admin/employees/' . $id)->with('message', 'Employee saved.');
    }

    public function show($id)
    {
        $this->requirePermission('payroll.view', 'payroll.manage');
        $e  = $this->findOr404('employees', $id);
        $db = db_connect();

        return $this->render('payroll/employee_show', [
            'e'        => $e,
            'user'     => $e['user_id'] ? $db->table('users')->select('username')->where('id', $e['user_id'])->get()->getRowArray() : null,
            'slips'    => $db->table('payroll_lines l')->select('l.*, r.month, r.status, r.run_no')->join('payroll_runs r', 'r.id = l.run_id')
                ->where('l.employee_id', $e['id'])->orderBy('r.month', 'DESC')->limit(24)->get()->getResultArray(),
            'advances' => $db->table('employee_advances')->where('employee_id', $e['id'])->orderBy('adv_date', 'DESC')->get()->getResultArray(),
            'accounts' => options('accounts'),
        ]);
    }

    public function delete($id)
    {
        $this->requirePermission('payroll.manage');
        $e = $this->findOr404('employees', $id);
        if (db_connect()->table('payroll_lines')->where('employee_id', $e['id'])->countAllResults()) {
            return $this->back('This employee has salary records. Set an exit date instead (they stay in the history).');
        }
        db_connect()->table('employees')->where('id', $e['id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        \App\Libraries\Workflow::log('employee', (int) $e['id'], 'deleted', 'Employee ' . $e['emp_code'] . ' ' . $e['name'] . ' deleted');

        return redirect()->to('admin/employees')->with('message', 'Employee deleted.');
    }

    /** Salary advance (loan to the employee), recovered from salaries. */
    public function advance($id)
    {
        $this->requirePermission('payroll.manage');
        $e = $this->findOr404('employees', $id);
        $p = $this->request->getPost();
        $amount = round((float) ($p['amount'] ?? 0), 2);
        if ($amount <= 0 || ! (int) ($p['account_id'] ?? 0) || ! strtotime((string) ($p['adv_date'] ?? ''))) {
            return $this->back('Enter the date, amount and the account it was paid from.');
        }
        $db = db_connect();
        $db->transException(true)->transStart();
        $db->table('employee_advances')->insert([
            'employee_id' => $e['id'], 'voucher_no' => \App\Libraries\Numbering::next('employee_advances', 'voucher_no', 'ADV', $p['adv_date']),
            'adv_date' => $p['adv_date'], 'amount' => $amount, 'monthly_recovery' => min($amount, round(max(0, (float) ($p['monthly_recovery'] ?? 0)), 2)) ?: $amount,
            'account_id' => (int) $p['account_id'], 'notes' => trim((string) ($p['notes'] ?? '')) ?: null,
            'created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $aid = (int) $db->insertID();
        Payroll::postAdvance($aid);
        $db->transComplete();
        \App\Libraries\Workflow::log('employee', (int) $e['id'], 'advance', 'Salary advance ' . money($amount) . ' given to ' . $e['name']);

        return redirect()->to('admin/employees/' . $e['id'])->with('message', 'Advance of ' . money($amount) . ' recorded. It is recovered from the next salaries.');
    }

    public function deleteAdvance($id)
    {
        $this->requirePermission('payroll.manage');
        $a = $this->findOr404('employee_advances', $id);
        if ((float) $a['recovered'] > 0) {
            return $this->back('Part of this advance is already recovered from a salary; it cannot be deleted.');
        }
        \App\Libraries\Ledger::unpost('employee_advance', (int) $a['id']);
        db_connect()->table('employee_advances')->where('id', $a['id'])->delete();

        return redirect()->to('admin/employees/' . $a['employee_id'])->with('message', 'Advance deleted.');
    }
}
