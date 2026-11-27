<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Payroll calculation and accounts posting.
 *
 * Monthly staff: each salary part × paid days ÷ days in the month.
 *   Paid days = days in month − absent − ½ × half days (week offs, holidays and
 *   paid leave are paid). Without attendance for the month: the full month.
 * Daily-wage staff: wage × (present + ½ × half days + paid leave).
 * Overtime: hours × (monthly gross ÷ days ÷ 8, or daily wage ÷ 8) × OT rate.
 *
 * PF  : employee and employer % of the PF wage (basic, or daily wages), capped at the ceiling.
 * ESI : when the full monthly gross is up to the ESI limit; employee / employer % of the
 *       earned gross, rounded up to the rupee.
 * PT  : half-yearly slabs (Tamil Nadu by default) on gross × 6, one sixth deducted each month.
 * TDS : the monthly amount in the employee's record.
 * Advances: the monthly recovery of each open advance, up to what is still due.
 *
 * Accounts (Journal on approval): Dr Salaries & Wages (gross), Dr Employer PF / ESI
 * contribution; Cr Salary Payable (net), PF Payable, ESI Payable, Professional Tax Payable,
 * TDS on Salary Payable, Staff Advances. Payment: Dr Salary Payable, Cr Bank.
 */
class Payroll
{
    public const STATUSES = [
        'P' => ['Present', 'success'], 'H' => ['Half day', 'warning'], 'A' => ['Absent', 'danger'],
        'L' => ['Paid leave', 'info'], 'W' => ['Week off', 'secondary'], 'O' => ['Holiday', 'secondary'],
    ];

    /** Payroll settings with defaults (Settings → Payroll). */
    public static function config(): array
    {
        $n = static fn (string $k, float $d) => is_numeric($v = setting('Solar.' . $k)) ? (float) $v : $d;

        return [
            'pf_employee'   => $n('pfEmployeeRate', 12),
            'pf_employer'   => $n('pfEmployerRate', 12),
            'pf_ceiling'    => $n('pfWageCeiling', 15000),
            'esi_employee'  => $n('esiEmployeeRate', 0.75),
            'esi_employer'  => $n('esiEmployerRate', 3.25),
            'esi_limit'     => $n('esiWageLimit', 21000),
            'ot_rate'       => $n('otRate', 2),
            'pt_slabs'      => self::ptSlabs((string) (setting('Solar.ptSlabs') ?: '21000:0, 30000:180, 45000:425, 60000:930, 75000:1025, 0:1250')),
            'week_off'      => (string) (setting('Solar.weekOffDay') ?? 'Sun'),
        ];
    }

    /** "21000:0, 30000:180, …, 0:1250" → [[upto, amount], …] (0 = above the last). */
    public static function ptSlabs(string $text): array
    {
        $out = [];
        foreach (preg_split('/[,\n;]+/', $text) as $part) {
            if (preg_match('/^\s*(\d+)\s*:\s*(\d+(?:\.\d+)?)\s*$/', $part, $m)) {
                $out[] = [(float) $m[1], (float) $m[2]];
            }
        }

        return $out;
    }

    /** Half-yearly PT for a monthly gross, deducted as one sixth per month. */
    public static function pt(float $monthlyGross, array $slabs): float
    {
        $half = $monthlyGross * 6;
        foreach ($slabs as [$upto, $amount]) {
            if ($upto <= 0 || $half <= $upto) {
                return round($amount / 6);
            }
        }

        return 0.0;
    }

    /** Attendance counts of one employee for a month (Y-m-01). */
    public static function attendance(int $employeeId, string $month): array
    {
        $from = $month;
        $to   = date('Y-m-t', strtotime($month));
        $c    = ['P' => 0, 'H' => 0, 'A' => 0, 'L' => 0, 'W' => 0, 'O' => 0, 'ot' => 0.0, 'entered' => 0];
        foreach (db_connect()->table('attendance')->select('status, ot_hours')->where('employee_id', $employeeId)
            ->where('att_date >=', $from)->where('att_date <=', $to)->get()->getResultArray() as $r) {
            $c[$r['status']] = ($c[$r['status']] ?? 0) + 1;
            $c['ot'] += (float) $r['ot_hours'];
            $c['entered']++;
        }
        $c['ot'] += (float) (db_connect()->table('attendance_months')->select('ot_hours')->where(['employee_id' => $employeeId, 'month' => $month])->get()->getRow()->ot_hours ?? 0);

        return $c;
    }

    /**
     * One payslip line for an employee and month.
     *
     * @param array $extra other_earnings, other_deductions, remarks (kept when recalculating)
     */
    public static function calculate(array $e, string $month, array $cfg, array $extra = []): array
    {
        $days  = (int) date('t', strtotime($month));
        $a     = self::attendance((int) $e['id'], $month);
        $start = $month;
        $end   = date('Y-m-t', strtotime($month));
        // Joined or left during the month: days outside employment are not paid.
        $outside = 0;
        if ($e['join_date'] && $e['join_date'] > $start) {
            $outside += (int) ((strtotime(min($e['join_date'], date('Y-m-d', strtotime($end . ' +1 day')))) - strtotime($start)) / 86400);
        }
        if ($e['exit_date'] && $e['exit_date'] < $end) {
            $outside += (int) ((strtotime($end) - strtotime(max($e['exit_date'], date('Y-m-d', strtotime($start . ' -1 day'))))) / 86400);
        }
        $outside = min($days, $outside);

        $round = static fn ($v) => round($v, 2);
        $l = [
            'employee_id' => (int) $e['id'], 'emp_code' => $e['emp_code'], 'name' => $e['name'], 'designation' => $e['designation'],
            'department' => $e['department'], 'pay_type' => $e['pay_type'], 'days_in_month' => $days,
            'present' => $a['P'], 'half_days' => $a['H'], 'leave_days' => $a['L'], 'week_offs' => $a['W'], 'holidays' => $a['O'],
            'absent' => $a['A'], 'ot_hours' => $a['ot'], 'att_entered' => $a['entered'] > 0 ? 1 : 0,
            'basic' => 0.0, 'hra' => 0.0, 'conveyance' => 0.0, 'special' => 0.0, 'wages' => 0.0, 'ot_amount' => 0.0,
            'other_earnings' => $round((float) ($extra['other_earnings'] ?? 0)), 'other_deductions' => $round((float) ($extra['other_deductions'] ?? 0)),
            'remarks' => $extra['remarks'] ?? null,
        ];

        if ($e['pay_type'] === 'daily') {
            $paid       = $a['P'] + 0.5 * $a['H'] + $a['L'];
            $l['wages'] = $round($paid * (float) $e['daily_wage']);
            $hourly     = (float) $e['daily_wage'] / 8;
            $pfBase     = $l['wages'];
            $fullGross  = (float) $e['daily_wage'] * 26;
        } else {
            $paid  = $a['entered'] ? max(0, $days - $outside - $a['A'] - 0.5 * $a['H']) : $days - $outside;
            $ratio = $days ? $paid / $days : 0;
            foreach (['basic', 'hra', 'conveyance', 'special'] as $k) {
                $l[$k] = $round((float) $e[$k] * $ratio);
            }
            $fullGross = (float) $e['basic'] + (float) $e['hra'] + (float) $e['conveyance'] + (float) $e['special'];
            $hourly    = $days ? $fullGross / $days / 8 : 0;
            $pfBase    = $l['basic'];
        }
        $l['paid_days'] = round($paid, 2);
        $l['ot_amount'] = $round($a['ot'] * $hourly * $cfg['ot_rate']);
        $l['gross']     = $round($l['basic'] + $l['hra'] + $l['conveyance'] + $l['special'] + $l['wages'] + $l['ot_amount'] + $l['other_earnings']);

        // PF
        $l['pf_wage'] = $l['pf_employee'] = $l['pf_employer'] = 0.0;
        if ($e['pf_applicable']) {
            $l['pf_wage']     = $round(min($pfBase, $cfg['pf_ceiling']));
            $l['pf_employee'] = round($l['pf_wage'] * $cfg['pf_employee'] / 100);
            $l['pf_employer'] = round($l['pf_wage'] * $cfg['pf_employer'] / 100);
        }
        // ESI (eligibility on the full monthly rate, contribution on what was earned)
        $l['esi_employee'] = $l['esi_employer'] = 0.0;
        if ($e['esi_applicable'] && $fullGross <= $cfg['esi_limit'] && $l['gross'] > 0) {
            $l['esi_employee'] = ceil($l['gross'] * $cfg['esi_employee'] / 100);
            $l['esi_employer'] = ceil($l['gross'] * $cfg['esi_employer'] / 100);
        }
        $l['pt']  = $e['pt_applicable'] && $l['gross'] > 0 ? self::pt($l['gross'], $cfg['pt_slabs']) : 0.0;
        $l['tds'] = $l['gross'] > 0 ? $round((float) $e['tds_monthly']) : 0.0;

        // Advances: monthly recovery of open advances, never more than what is left after the other deductions.
        $due = 0.0;
        foreach (db_connect()->table('employee_advances')->where('employee_id', $e['id'])->where('recovered < amount', null, false)
            ->where('adv_date <=', $end)->get()->getResultArray() as $adv) {
            $due += min((float) $adv['monthly_recovery'] ?: (float) $adv['amount'], (float) $adv['amount'] - (float) $adv['recovered']);
        }
        $before                = $l['pf_employee'] + $l['esi_employee'] + $l['pt'] + $l['tds'] + $l['other_deductions'];
        $l['advance_recovery'] = $round(max(0, min($due, $l['gross'] - $before)));
        $l['total_deductions'] = $round($before + $l['advance_recovery']);
        $l['net']              = $round($l['gross'] - $l['total_deductions']);

        return $l;
    }

    /** Employees to pay for a month: active during the month. */
    public static function employeesFor(string $month): array
    {
        $end = date('Y-m-t', strtotime($month));

        return db_connect()->table('employees')->where('deleted_at', null)
            ->groupStart()->where('join_date', null)->orWhere('join_date <=', $end)->groupEnd()
            ->groupStart()->where('exit_date', null)->orWhere('exit_date >=', $month)->groupEnd()
            ->orderBy('emp_code')->get()->getResultArray();
    }

    /** (Re)calculates every line of a draft run, keeping typed extras. */
    public static function recalculate(int $runId): void
    {
        $db  = db_connect();
        $run = $db->table('payroll_runs')->where('id', $runId)->get()->getRowArray();
        if (! $run || $run['status'] !== 'draft') {
            throw new RuntimeException('Only a draft salary run can be recalculated.');
        }
        $cfg   = self::config();
        $old   = array_column($db->table('payroll_lines')->where('run_id', $runId)->get()->getResultArray(), null, 'employee_id');
        $keep  = [];
        foreach (self::employeesFor($run['month']) as $e) {
            $line = self::calculate($e, $run['month'], $cfg, $old[$e['id']] ?? []);
            if (isset($old[$e['id']])) {
                $db->table('payroll_lines')->where('id', $old[$e['id']]['id'])->update($line);
            } else {
                $db->table('payroll_lines')->insert($line + ['run_id' => $runId]);
            }
            $keep[] = (int) $e['id'];
        }
        $gone = array_diff(array_map('intval', array_keys($old)), $keep);
        if ($gone) {
            $db->table('payroll_lines')->where('run_id', $runId)->whereIn('employee_id', $gone)->delete();
        }
        self::totals($runId);
    }

    public static function totals(int $runId): void
    {
        $db = db_connect();
        $t  = $db->table('payroll_lines')->select('SUM(gross) g, SUM(total_deductions) d, SUM(net) n, SUM(pf_employer) pf, SUM(esi_employer) esi', false)
            ->where('run_id', $runId)->get()->getRowArray();
        $db->table('payroll_runs')->where('id', $runId)->update([
            'gross' => (float) $t['g'], 'deductions' => (float) $t['d'], 'net' => (float) $t['n'],
            'employer_pf' => (float) $t['pf'], 'employer_esi' => (float) $t['esi'], 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Journal for an approved run; advances recovered are marked. */
    public static function post(int $runId): void
    {
        $db  = db_connect();
        $run = $db->table('payroll_runs')->where('id', $runId)->get()->getRowArray();
        $s   = $db->table('payroll_lines')->select('SUM(gross) gross, SUM(pf_employee) pfe, SUM(pf_employer) pfr, SUM(esi_employee) esie, SUM(esi_employer) esir,
            SUM(pt) pt, SUM(tds) tds, SUM(advance_recovery) adv, SUM(other_deductions) oth, SUM(net) net', false)->where('run_id', $runId)->get()->getRowArray();
        $f   = static fn ($k) => (float) ($s[$k] ?? 0);
        $L   = [Ledger::class, 'system'];
        Ledger::post('Journal', $run['run_no'], date('Y-m-t', strtotime($run['month'])), 'Salaries for ' . date('F Y', strtotime($run['month'])), 'payroll', $runId, [
            [$L('Salaries & Wages', 'Indirect Expenses'), $f('gross'), 0],
            [$L('Employer PF Contribution', 'Indirect Expenses'), $f('pfr'), 0],
            [$L('Employer ESI Contribution', 'Indirect Expenses'), $f('esir'), 0],
            [$L('Salary Payable', 'Current Liabilities'), 0, $f('net')],
            [$L('PF Payable', 'Duties & Taxes'), 0, $f('pfe') + $f('pfr')],
            [$L('ESI Payable', 'Duties & Taxes'), 0, $f('esie') + $f('esir')],
            [$L('Professional Tax Payable', 'Duties & Taxes'), 0, $f('pt')],
            [$L('TDS on Salary Payable', 'Duties & Taxes'), 0, $f('tds')],
            [$L('Staff Advances', 'Current Assets'), 0, $f('adv')],
            [$L('Other Salary Deductions', 'Current Liabilities'), 0, $f('oth')],
        ]);
        // Recoveries reduce the open advances, oldest first.
        foreach ($db->table('payroll_lines')->select('employee_id, advance_recovery')->where('run_id', $runId)->where('advance_recovery >', 0)->get()->getResultArray() as $l) {
            self::applyRecovery((int) $l['employee_id'], (float) $l['advance_recovery']);
        }
    }

    /** Reverse of post(): voucher removed, recoveries given back (newest advance first). */
    public static function unpost(int $runId): void
    {
        $db = db_connect();
        Ledger::unpost('payroll', $runId);
        foreach ($db->table('payroll_lines')->select('employee_id, advance_recovery')->where('run_id', $runId)->where('advance_recovery >', 0)->get()->getResultArray() as $l) {
            self::applyRecovery((int) $l['employee_id'], -(float) $l['advance_recovery']);
        }
    }

    /** Payment of the net salaries from a bank / cash account. */
    public static function postPayment(int $runId): void
    {
        $run = db_connect()->table('payroll_runs')->where('id', $runId)->get()->getRowArray();
        Ledger::post('Payment', $run['run_no'] . '-PAY', $run['paid_on'], 'Salaries paid for ' . date('F Y', strtotime($run['month'])), 'payroll_payment', $runId, [
            [Ledger::system('Salary Payable', 'Current Liabilities'), (float) $run['net'], 0],
            [Ledger::forRef('account', (int) $run['account_id']), 0, (float) $run['net']],
        ]);
    }

    public static function postAdvance(int $advanceId): void
    {
        $a = db_connect()->table('employee_advances a')->select('a.*, e.name')->join('employees e', 'e.id = a.employee_id')->where('a.id', $advanceId)->get()->getRowArray();
        Ledger::post('Payment', $a['voucher_no'], $a['adv_date'], 'Salary advance to ' . $a['name'] . ($a['notes'] ? ' – ' . $a['notes'] : ''), 'employee_advance', $advanceId, [
            [Ledger::system('Staff Advances', 'Current Assets'), (float) $a['amount'], 0],
            [Ledger::forRef('account', (int) $a['account_id']), 0, (float) $a['amount']],
        ]);
    }

    private static function applyRecovery(int $employeeId, float $amount): void
    {
        $db   = db_connect();
        $list = $db->table('employee_advances')->where('employee_id', $employeeId)->orderBy('adv_date', $amount >= 0 ? 'ASC' : 'DESC')->orderBy('id', $amount >= 0 ? 'ASC' : 'DESC')->get()->getResultArray();
        foreach ($list as $a) {
            if (abs($amount) < 0.005) {
                break;
            }
            if ($amount > 0) {
                $take = min($amount, (float) $a['amount'] - (float) $a['recovered']);
            } else {
                $take = -min(-$amount, (float) $a['recovered']);
            }
            if (abs($take) < 0.005) {
                continue;
            }
            $db->table('employee_advances')->where('id', $a['id'])->update(['recovered' => round((float) $a['recovered'] + $take, 2)]);
            $amount -= $take;
        }
    }

    /** Next employee code: EMP001, EMP002 … */
    public static function nextCode(): string
    {
        $last = db_connect()->table('employees')->select('emp_code')->like('emp_code', 'EMP', 'after')->orderBy('id', 'DESC')->get()->getResultArray();
        $max  = 0;
        foreach ($last as $r) {
            $max = max($max, (int) substr($r['emp_code'], 3));
        }

        return 'EMP' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /** Amount in words for payslips (uses the app helper). */
    public static function words(float $v): string
    {
        return function_exists('amount_in_words') ? amount_in_words($v) : number_format($v, 2);
    }
}
