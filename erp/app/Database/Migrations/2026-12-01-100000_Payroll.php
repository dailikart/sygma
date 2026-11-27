<?php

namespace App\Database\Migrations;

/**
 * Payroll: employees with salary structure, daily attendance, staff advances,
 * monthly salary runs with one line per employee (a frozen copy of the payslip).
 */
class Payroll extends BaseSchema
{
    public function up()
    {
        $this->create('employees', $this->id() + [
            'emp_code'       => $this->str(20, false),
            'name'           => $this->str(150, false),
            'user_id'        => $this->fk(true),              // login, if the person uses the ERP
            'department'     => $this->str(60),
            'designation'    => $this->str(60),
            'phone'          => $this->str(20),
            'email'          => $this->str(150),
            'gender'         => $this->str(10),
            'dob'            => $this->date(),
            'join_date'      => $this->date(),
            'exit_date'      => $this->date(),
            'status'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'active'], // active | left
            'pay_type'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'monthly'], // monthly | daily
            'basic'          => $this->money(),
            'hra'            => $this->money(),
            'conveyance'     => $this->money(),
            'special'        => $this->money(),
            'daily_wage'     => $this->money(),
            'pf_applicable'  => $this->bool(0),
            'esi_applicable' => $this->bool(0),
            'pt_applicable'  => $this->bool(1),
            'tds_monthly'    => $this->money(),
            'bank_name'      => $this->str(100),
            'bank_account'   => $this->str(40),
            'ifsc'           => $this->str(15),
            'pan'            => $this->str(10),
            'aadhaar'        => $this->str(14),
            'uan'            => $this->str(20),
            'esi_no'         => $this->str(20),
            'address'        => $this->text(),
            'notes'          => $this->text(),
        ] + $this->stamps(true), ['user_id', 'status'], ['emp_code']);

        $this->create('attendance', $this->id() + [
            'employee_id' => $this->fk(),
            'att_date'    => $this->date(false),
            'status'      => ['type' => 'CHAR', 'constraint' => 1, 'default' => 'P'], // P present, H half day, A absent, L paid leave, W week off, O holiday
            'ot_hours'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'updated_by'  => $this->fk(true),
            'updated_at'  => $this->datetime(),
        ], ['att_date'], [['employee_id', 'att_date']]);

        // Month totals typed in the attendance sheet (overtime hours).
        $this->create('attendance_months', $this->id() + [
            'employee_id' => $this->fk(),
            'month'       => $this->date(false),
            'ot_hours'    => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
            'updated_at'  => $this->datetime(),
        ], [], [['employee_id', 'month']]);

        $this->create('employee_advances', $this->id() + [
            'employee_id'      => $this->fk(),
            'voucher_no'       => $this->str(30, false),
            'adv_date'         => $this->date(false),
            'amount'           => $this->money(),
            'monthly_recovery' => $this->money(),
            'recovered'        => $this->money(),
            'account_id'       => $this->fk(),
            'notes'            => $this->str(255),
            'created_by'       => $this->fk(true),
        ] + $this->stamps(), ['employee_id'], ['voucher_no']);

        $this->create('payroll_runs', $this->id() + [
            'run_no'        => $this->str(30, false),
            'month'         => $this->date(false),          // first day of the month
            'status'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'draft'], // draft | approved | paid
            'gross'         => $this->money(),
            'deductions'    => $this->money(),
            'net'           => $this->money(),
            'employer_pf'   => $this->money(),
            'employer_esi'  => $this->money(),
            'account_id'    => $this->fk(true),
            'paid_on'       => $this->date(),
            'approved_by'   => $this->fk(true),
            'approved_at'   => $this->datetime(),
            'notes'         => $this->text(),
            'created_by'    => $this->fk(true),
        ] + $this->stamps(), [], ['run_no', 'month']);

        $this->create('payroll_lines', $this->id() + [
            'run_id'           => $this->fk(),
            'employee_id'      => $this->fk(),
            'emp_code'         => $this->str(20),
            'name'             => $this->str(150),
            'designation'      => $this->str(60),
            'department'       => $this->str(60),
            'pay_type'         => $this->str(10),
            'days_in_month'    => $this->qty(),
            'present'          => $this->qty(),
            'half_days'        => $this->qty(),
            'leave_days'       => $this->qty(),
            'week_offs'        => $this->qty(),
            'holidays'         => $this->qty(),
            'absent'           => $this->qty(),
            'paid_days'        => $this->qty(),
            'ot_hours'         => $this->qty(),
            'att_entered'      => $this->bool(0),
            'basic'            => $this->money(),
            'hra'              => $this->money(),
            'conveyance'       => $this->money(),
            'special'          => $this->money(),
            'wages'            => $this->money(),         // daily-wage earnings
            'ot_amount'        => $this->money(),
            'other_earnings'   => $this->money(),         // incentive / bonus typed in the run
            'gross'            => $this->money(),
            'pf_wage'          => $this->money(),
            'pf_employee'      => $this->money(),
            'pf_employer'      => $this->money(),
            'esi_employee'     => $this->money(),
            'esi_employer'     => $this->money(),
            'pt'               => $this->money(),
            'tds'              => $this->money(),
            'advance_recovery' => $this->money(),
            'other_deductions' => $this->money(),
            'total_deductions' => $this->money(),
            'net'              => $this->money(),
            'remarks'          => $this->str(255),
        ], ['run_id', 'employee_id'], [['run_id', 'employee_id']]);
    }

    public function down()
    {
        foreach (['payroll_lines', 'payroll_runs', 'employee_advances', 'attendance_months', 'attendance', 'employees'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
