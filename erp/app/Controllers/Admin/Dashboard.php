<?php

namespace App\Controllers\Admin;

use App\Libraries\Stock;

class Dashboard extends AdminController
{
    public function index()
    {
        $db   = db_connect();
        $data = ['tasks' => Tasks::myTasks()];

        if (can('leads.view')) {
            $data['leadsOpen']   = $db->table('leads')->whereNotIn('status', ['confirmed', 'lost'])->where('deleted_at', null)->countAllResults();
            $data['followToday'] = $db->table('leads')->whereNotIn('status', ['confirmed', 'lost'])->where('deleted_at', null)
                ->where('next_followup_date <=', date('Y-m-d'))->countAllResults();
        }
        if (can('invoices.view')) {
            $m = date('Y-m-01');
            $data['salesMonth'] = (float) ($db->table('invoices')->selectSum('total')->where('status !=', 'cancelled')->where('invoice_date >=', $m)->get()->getRow()->total ?? 0);
            $data['receivable'] = (float) ($db->table('invoices')->select('SUM(total - amount_paid) AS due', false)->whereIn('status', ['unpaid', 'partial'])->get()->getRow()->due ?? 0);
            // All invoices (not cancelled): clients by finance mode, value, paid, outstanding.
            $data['clientsByMode'] = array_column($db->table('invoices')->select('finance_mode, COUNT(DISTINCT customer_id) AS n')
                ->where('status !=', 'cancelled')->groupBy('finance_mode')->get()->getResultArray(), 'n', 'finance_mode');
            $t = $db->table('invoices')->select('COUNT(*) AS n, SUM(total) AS total, SUM(amount_paid) AS paid', false)
                ->where('status !=', 'cancelled')->get()->getRowArray();
            $data['invTotals'] = ['n' => (int) $t['n'], 'total' => (float) $t['total'], 'paid' => (float) $t['paid'], 'due' => (float) $t['total'] - (float) $t['paid']];
            $data['collectedMonth'] = (float) ($db->table('payments')->selectSum('amount')->where('payment_date >=', $m)->get()->getRow()->amount ?? 0);
        }
        if (can('projects.view')) {
            $data['stageCounts'] = array_column($db->table('projects')->select('current_stage, COUNT(*) AS n')
                ->where('status', 'active')->groupBy('current_stage')->get()->getResultArray(), 'n', 'current_stage');
            $data['onHold'] = $db->table('projects')->where('status', 'on_hold')->countAllResults();
        }
        if (can('inventory.view')) {
            $bal = Stock::balances();
            $low = [];
            foreach ($db->table('items')->where(['is_active' => 1, 'deleted_at' => null])->where('min_stock >', 0)->get()->getResultArray() as $it) {
                $have = array_sum($bal[$it['id']] ?? []);
                if ($have < (float) $it['min_stock']) {
                    $low[] = $it + ['have' => $have];
                }
            }
            $data['lowStock'] = $low;
        }

        return $this->render('dashboard', $data);
    }
}
