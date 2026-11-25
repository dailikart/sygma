<?php

namespace App\Controllers\Admin;

use App\Libraries\Ledger;

class Reports extends AdminController
{
    /** Income vs expense, month by month (money in vs money out, plus sales and purchases billed). */
    public function incomeExpense()
    {
        $this->requirePermission('reports.view');
        [$from, $to] = fy_range();
        $db     = db_connect();
        $months = [];
        for ($m = strtotime(date('Y-m-01', strtotime($from))); $m <= strtotime($to); $m = strtotime('+1 month', $m)) {
            $months[date('Y-m', $m)] = ['sales' => 0, 'receipts' => 0, 'other_income' => 0, 'purchases' => 0, 'returns' => 0, 'expenses' => 0, 'supplier_payments' => 0];
        }
        $add = static function (array $rows, string $dateKey, string $col) use (&$months) {
            foreach ($rows as $r) {
                $k = substr($r[$dateKey], 0, 7);
                if (isset($months[$k])) {
                    $months[$k][$col] += (float) $r['amount'];
                }
            }
        };
        $range = static fn (string $table, string $date, string $amount = 'amount', array $where = []) => $db->table($table)
            ->select("{$date}, {$amount} AS amount", false)->where("{$date} >=", $from)->where("{$date} <=", $to)->where($where)->get()->getResultArray();

        $add($range('invoices', 'invoice_date', 'total', ['status !=' => 'cancelled']), 'invoice_date', 'sales');
        $add($range('payments', 'payment_date'), 'payment_date', 'receipts');
        $add($range('incomes', 'income_date'), 'income_date', 'other_income');
        $add($range('purchases', 'bill_date', 'total'), 'bill_date', 'purchases');
        $add($range('purchase_returns', 'return_date', 'total'), 'return_date', 'returns');
        $add($range('expenses', 'expense_date'), 'expense_date', 'expenses');
        $add($range('supplier_payments', 'payment_date'), 'payment_date', 'supplier_payments');

        $byHead = static fn (string $table, string $headTable, string $fk, string $date) => $db->table($table . ' t')
            ->select('h.name, SUM(t.amount) AS amount')->join($headTable . ' h', 'h.id = t.' . $fk)
            ->where("t.{$date} >=", $from)->where("t.{$date} <=", $to)->groupBy('h.name')->orderBy('amount', 'DESC')->get()->getResultArray();

        $data = [
            'from' => $from, 'to' => $to, 'months' => $months,
            'expenseHeads' => $byHead('expenses', 'expense_heads', 'expense_head_id', 'expense_date'),
            'incomeHeads'  => $byHead('incomes', 'income_heads', 'income_head_id', 'income_date'),
        ];
        if ($this->request->getGet('export') === 'excel') {
            $rows = [];
            foreach ($months as $k => $m) {
                $in = $m['receipts'] + $m['other_income'];
                $out = $m['expenses'] + $m['supplier_payments'];
                $rows[] = [date('M Y', strtotime($k . '-01')), $m['sales'], $m['purchases'] - $m['returns'], $m['receipts'], $m['other_income'], $in, $m['supplier_payments'], $m['expenses'], $out, $in - $out];
            }

            return $this->excel('Income_Expense', ['Month', 'Sales billed', 'Net purchases', 'Customer receipts', 'Other income', 'Total money in', 'Supplier payments', 'Expenses', 'Total money out', 'Net'], $rows);
        }

        return $this->render('reports/income_expense', $data);
    }

    /** Inventory items used (delivered/dispatched to site) split by B2B and B2C customers. */
    public function usage()
    {
        $this->requirePermission('reports.view');
        [$from, $to] = fy_range();
        $rows = db_connect()->table('dispatch_items di')
            ->select('i.id, i.name, i.unit, i.purchase_price, c.name AS category, p.customer_type, SUM(di.qty) AS qty')
            ->join('dispatches d', 'd.id = di.dispatch_id')->join('projects p', 'p.id = d.project_id')
            ->join('items i', 'i.id = di.item_id')->join('item_categories c', 'c.id = i.category_id', 'left')
            ->whereIn('d.status', ['dispatched', 'delivered'])
            ->where('d.dispatched_at >=', $from . ' 00:00:00')->where('d.dispatched_at <=', $to . ' 23:59:59')
            ->groupBy(['i.id', 'i.name', 'i.unit', 'i.purchase_price', 'c.name', 'p.customer_type'])
            ->orderBy('c.name')->orderBy('i.name')->get()->getResultArray();
        $items = [];
        foreach ($rows as $r) {
            $items[$r['id']] ??= ['name' => $r['name'], 'unit' => $r['unit'], 'category' => $r['category'], 'price' => (float) $r['purchase_price'], 'b2b' => 0, 'b2c' => 0];
            $items[$r['id']][$r['customer_type']] += (float) $r['qty'];
        }
        $projects = db_connect()->table('dispatches d')->select('p.customer_type, COUNT(DISTINCT p.id) AS n', false)
            ->join('projects p', 'p.id = d.project_id')->whereIn('d.status', ['dispatched', 'delivered'])
            ->where('d.dispatched_at >=', $from . ' 00:00:00')->where('d.dispatched_at <=', $to . ' 23:59:59')
            ->groupBy('p.customer_type')->get()->getResultArray();

        if ($this->request->getGet('export') === 'excel') {
            $out = [];
            foreach ($items as $i) {
                $out[] = [$i['category'], $i['name'], $i['unit'], $i['b2b'], $i['b2c'], $i['b2b'] + $i['b2c'], round(($i['b2b'] + $i['b2c']) * $i['price'], 2)];
            }

            return $this->excel('Inventory_Usage', ['Category', 'Item', 'Unit', 'B2B qty', 'B2C qty', 'Total qty', 'Value (purchase price)'], $out);
        }

        return $this->render('reports/usage', ['items' => $items, 'from' => $from, 'to' => $to, 'projects' => array_column($projects, null, 'customer_type')]);
    }

    /** Supplier-wise summary: opening, purchases, returns, payments, balance. */
    public function suppliers()
    {
        $this->requirePermission('reports.view');
        [$from, $to] = fy_range();
        $db  = db_connect();
        $sum = static function (string $table, string $date, string $col, bool $before) use ($db, $from, $to) {
            $b = $db->table($table)->select("supplier_id, SUM({$col}) AS s")->groupBy('supplier_id');
            $before ? $b->where("{$date} <", $from) : $b->where("{$date} >=", $from)->where("{$date} <=", $to);

            return array_column($b->get()->getResultArray(), 's', 'supplier_id');
        };
        $pb = $sum('purchases', 'bill_date', 'total', true);
        $rb = $sum('purchase_returns', 'return_date', 'total', true);
        $yb = $sum('supplier_payments', 'payment_date', 'amount', true);
        $p  = $sum('purchases', 'bill_date', 'total', false);
        $r  = $sum('purchase_returns', 'return_date', 'total', false);
        $y  = $sum('supplier_payments', 'payment_date', 'amount', false);
        $openPo = array_column($db->table('purchase_orders')->select('supplier_id, COUNT(*) AS n')->whereIn('status', ['open', 'partial'])->groupBy('supplier_id')->get()->getResultArray(), 'n', 'supplier_id');

        $rows = [];
        foreach ($db->table('suppliers')->where('deleted_at', null)->orderBy('name')->get()->getResultArray() as $s) {
            $id      = $s['id'];
            $opening = (float) $s['opening_balance'] + ($pb[$id] ?? 0) - ($rb[$id] ?? 0) - ($yb[$id] ?? 0);
            $row     = ['id' => $id, 'name' => $s['name'], 'gstin' => $s['gstin'], 'opening' => $opening,
                'purchases' => (float) ($p[$id] ?? 0), 'returns' => (float) ($r[$id] ?? 0), 'paid' => (float) ($y[$id] ?? 0), 'open_po' => (int) ($openPo[$id] ?? 0)];
            $row['balance'] = $opening + $row['purchases'] - $row['returns'] - $row['paid'];
            if ($row['opening'] || $row['purchases'] || $row['returns'] || $row['paid'] || $row['open_po'] || $this->request->getGet('all')) {
                $rows[] = $row;
            }
        }
        if ($this->request->getGet('export') === 'excel') {
            return $this->excel('Supplier_Summary', ['Supplier', 'GSTIN', 'Opening', 'Purchases', 'Returns', 'Paid', 'Balance payable', 'Open POs'],
                array_map(static fn ($r) => [$r['name'], $r['gstin'], $r['opening'], $r['purchases'], $r['returns'], $r['paid'], $r['balance'], $r['open_po']], $rows));
        }

        return $this->render('reports/suppliers', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    /** Supplier statement = the supplier's ledger. */
    public function supplierLedger($supplierId)
    {
        $this->requirePermission('reports.view');
        $this->findOr404('suppliers', $supplierId);
        $ledgerId = Ledger::forRef('supplier', (int) $supplierId);
        $qs       = http_build_query(array_intersect_key($this->request->getGet(), ['from' => 1, 'to' => 1]));
        if (! can('accounts.view')) {
            throw new \App\Exceptions\ForbiddenException('Supplier statements need access to Accounts.');
        }

        return redirect()->to('admin/ledgers/' . $ledgerId . ($qs ? '?' . $qs : ''));
    }

    public function projects()
    {
        $this->requirePermission('reports.view');
        $db   = db_connect();
        $rows = $db->table('projects p')
            ->select('p.id, p.project_no, p.capacity_kw, p.customer_type, p.subsidy_type, p.status, p.current_stage, p.created_at, c.name AS customer, s.started_at, s.assigned_to')
            ->join('customers c', 'c.id = p.customer_id')
            ->join('project_stages s', "s.project_id = p.id AND s.status = 'active'", 'left')
            ->whereIn('p.status', ['active', 'on_hold'])->orderBy('s.started_at')->get()->getResultArray();

        if ($this->request->getGet('export') === 'excel') {
            return $this->excel('Project_Status', ['Project', 'Customer', 'kW', 'Type', 'Subsidy', 'Stage', 'Assigned', 'Days in stage', 'Status'], array_map(static fn ($r) => [
                $r['project_no'], $r['customer'], $r['capacity_kw'], strtoupper($r['customer_type']), $r['subsidy_type'],
                \App\Libraries\Workflow::stageTitle((string) $r['current_stage']), user_name($r['assigned_to']),
                $r['started_at'] ? (int) floor((time() - strtotime($r['started_at'])) / 86400) : '', $r['status'],
            ], $rows));
        }

        return $this->render('reports/projects', [
            'rows' => $rows,
            'completed' => $db->table('projects')->select('customer_type, COUNT(*) AS n, SUM(capacity_kw) AS kw')->where('status', 'completed')->groupBy('customer_type')->get()->getResultArray(),
        ]);
    }

    /** Profit per project: invoice value (before GST) − material dispatched − project expenses − EB charges paid by us. */
    public function projectProfit()
    {
        $this->requirePermission('reports.view');
        [$from, $to] = fy_range();
        $db   = db_connect();
        $b    = $db->table('projects p')
            ->select('p.id, p.project_no, p.capacity_kw, p.customer_type, p.subsidy_type, p.status, p.current_stage, c.name AS customer, i.invoice_no, i.invoice_date, i.id AS invoice_id')
            ->join('customers c', 'c.id = p.customer_id')->join('invoices i', 'i.id = p.invoice_id')
            ->where('i.invoice_date >=', $from)->where('i.invoice_date <=', $to)->where('i.status !=', 'cancelled');
        if ($t = $this->request->getGet('type')) {
            $b->where('p.customer_type', $t);
        }
        $rows   = $b->orderBy('i.invoice_date', 'DESC')->get()->getResultArray();
        $profit = \App\Libraries\ProjectProfit::forProjects(array_map('intval', array_column($rows, 'id')));
        foreach ($rows as &$r) {
            $r += $profit[(int) $r['id']] ?? ['revenue' => 0, 'material' => 0, 'expenses' => 0, 'eb' => 0, 'cost' => 0, 'profit' => 0, 'margin' => null];
        }
        unset($r);
        if ($this->request->getGet('export') === 'excel') {
            return $this->excel('Project_Profit', ['Project', 'Invoice', 'Date', 'Customer', 'kW', 'Type', 'Stage', 'Invoice value (before GST)', 'Material', 'Expenses', 'EB charges (paid by us)', 'Total cost', 'Profit', 'Margin %', 'Profit per kW'], array_map(static fn ($r) => [
                $r['project_no'], $r['invoice_no'], fdate($r['invoice_date']), $r['customer'], (float) $r['capacity_kw'], strtoupper($r['customer_type']),
                \App\Libraries\Workflow::stageTitle((string) $r['current_stage']), $r['revenue'], $r['material'], $r['expenses'], $r['eb'], $r['cost'], $r['profit'],
                $r['margin'] ?? '', (float) $r['capacity_kw'] > 0 ? round($r['profit'] / (float) $r['capacity_kw'], 2) : '',
            ], $rows));
        }

        return $this->render('reports/project_profit', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    /** Customers / invoices by payment mode (cash or bank loan) with loan disbursement status. */
    public function finance()
    {
        $this->requirePermission('reports.view');
        [$from, $to] = fy_range();
        $db = db_connect();
        $b  = $db->table('invoices i')->select('i.*, c.name AS customer, c.type AS customer_type, c.phone, c.city, p.project_no, p.status AS project_status, p.current_stage')
            ->join('customers c', 'c.id = i.customer_id')->join('projects p', 'p.id = i.project_id', 'left')
            ->where('i.status !=', 'cancelled')->where('i.invoice_date >=', $from)->where('i.invoice_date <=', $to);
        $mode = $this->request->getGet('mode');
        if ($mode !== null && $mode !== '') {
            $b->where('i.finance_mode', $mode === 'none' ? '' : $mode);
        }
        $rows = $b->orderBy('i.invoice_date', 'DESC')->get()->getResultArray();
        $tr   = [];
        if ($rows) {
            foreach ($db->table('loan_tranches')->whereIn('invoice_id', array_column($rows, 'id'))->get()->getResultArray() as $t) {
                $tr[$t['invoice_id']][] = $t;
            }
        }
        foreach ($rows as &$r) {
            $list            = $tr[$r['id']] ?? [];
            $r['tranches']   = count($list);
            $r['loan_recd']  = array_sum(array_map(static fn ($t) => $t['status'] === 'received' ? (float) $t['amount'] : 0, $list));
            $r['loan_pending'] = array_sum(array_map(static fn ($t) => $t['status'] === 'pending' ? (float) $t['amount'] : 0, $list));
            $r['recd_count'] = count(array_filter($list, static fn ($t) => $t['status'] === 'received'));
        }
        unset($r);
        $labels = \App\Libraries\Finance::MODES;
        if ($this->request->getGet('export') === 'excel') {
            return $this->excel('Cash_Loan_Customers', ['Invoice', 'Date', 'Customer', 'Type', 'Phone', 'Payment mode', 'Bank', 'Loan amount', 'Disbursed', 'Pending disbursement', 'Invoice total', 'Paid', 'Due', 'Project stage'],
                array_map(static fn ($r) => [$r['invoice_no'], fdate($r['invoice_date']), $r['customer'], strtoupper($r['customer_type']), (string) $r['phone'], $labels[$r['finance_mode']] ?? $r['finance_mode'],
                    (string) $r['loan_bank'], (float) $r['loan_amount'], $r['loan_recd'], $r['loan_pending'], (float) $r['total'], (float) $r['amount_paid'], (float) $r['total'] - (float) $r['amount_paid'],
                    \App\Libraries\Workflow::stageTitle((string) $r['current_stage'])], $rows));
        }

        return $this->render('reports/finance', ['rows' => $rows, 'from' => $from, 'to' => $to, 'labels' => $labels]);
    }

    /**
     * Profit & Loss account (Tally style): trading account (opening stock,
     * purchases, direct expenses / sales, direct income, closing stock →
     * gross profit), then indirect expenses / incomes → net profit.
     * Stock is valued at the item's purchase price.
     */
    public function profitLoss()
    {
        $this->requirePermission('reports.view', 'accounts.view');
        [$from, $to] = fy_range();
        $db  = db_connect();
        $mov = $db->table('voucher_entries e')->select('l.name, l.group_name, SUM(e.debit) AS dr, SUM(e.credit) AS cr')
            ->join('vouchers v', 'v.id = e.voucher_id')->join('ledgers l', 'l.id = e.ledger_id')
            ->where('v.voucher_date >=', $from)->where('v.voucher_date <=', $to)
            ->whereIn('l.group_name', ['Sales Accounts', 'Purchase Accounts', 'Direct Expenses', 'Indirect Expenses', 'Direct Incomes', 'Indirect Incomes'])
            ->groupBy(['l.name', 'l.group_name'])->orderBy('l.name')->get()->getResultArray();
        $g = array_fill_keys(['Sales Accounts', 'Purchase Accounts', 'Direct Expenses', 'Indirect Expenses', 'Direct Incomes', 'Indirect Incomes'], []);
        foreach ($mov as $m) {
            $isIncome = in_array($m['group_name'], ['Sales Accounts', 'Direct Incomes', 'Indirect Incomes'], true);
            $amt      = $isIncome ? (float) $m['cr'] - (float) $m['dr'] : (float) $m['dr'] - (float) $m['cr'];
            if (abs($amt) >= 0.005) {
                $g[$m['group_name']][$m['name']] = round($amt, 2);
            }
        }
        // Opening / closing stock at FIFO cost (the actual purchase rates of the lots still in store).
        $stockValue = static fn (string $before): float => \App\Libraries\Lots::value($before);
        $opening = $stockValue($from);
        $closing = $stockValue(date('Y-m-d', strtotime($to . ' +1 day')));
        $sum     = static fn (array $a) => round(array_sum($a), 2);
        $tradingDr = $opening + $sum($g['Purchase Accounts']) + $sum($g['Direct Expenses']);
        $tradingCr = $sum($g['Sales Accounts']) + $sum($g['Direct Incomes']) + $closing;
        $gross     = round($tradingCr - $tradingDr, 2);
        $net       = round($gross + $sum($g['Indirect Incomes']) - $sum($g['Indirect Expenses']), 2);

        $data = compact('from', 'to', 'g', 'opening', 'closing', 'gross', 'net', 'tradingDr', 'tradingCr');
        if ($this->request->getGet('export') === 'excel') {
            $rows = [['Opening stock', '', $opening, '']];
            foreach (['Purchase Accounts', 'Direct Expenses'] as $k) { foreach ($g[$k] as $n => $a) { $rows[] = [$n, $k, $a, '']; } }
            foreach (['Sales Accounts', 'Direct Incomes'] as $k) { foreach ($g[$k] as $n => $a) { $rows[] = [$n, $k, '', $a]; } }
            $rows[] = ['Closing stock', '', '', $closing];
            $rows[] = [$gross >= 0 ? 'Gross profit' : 'Gross loss', '', '', $gross];
            foreach ($g['Indirect Expenses'] as $n => $a) { $rows[] = [$n, 'Indirect Expenses', $a, '']; }
            foreach ($g['Indirect Incomes'] as $n => $a) { $rows[] = [$n, 'Indirect Incomes', '', $a]; }
            $rows[] = [$net >= 0 ? 'Net profit' : 'Net loss', '', '', $net];

            return $this->excel('Profit_Loss_' . $from . '_' . $to, ['Particulars', 'Group', 'Debit (₹)', 'Credit (₹)'], $rows);
        }

        return $this->render('reports/profit_loss', $data);
    }

    public function subsidy()
    {
        $this->requirePermission('reports.view');
        $rows = db_connect()->table('project_stages s')
            ->select('s.status AS stage_status, s.data, p.id, p.project_no, p.capacity_kw, p.status, p.commissioned_on, c.name AS customer, c.phone')
            ->join('projects p', 'p.id = s.project_id')->join('customers c', 'c.id = p.customer_id')
            ->where('s.stage', 'subsidy')->where('p.status !=', 'cancelled')->orderBy('p.id', 'DESC')->get()->getResultArray();
        $filter = $this->request->getGet('status');
        $out    = [];
        foreach ($rows as $r) {
            $r['data'] = json_decode($r['data'] ?? '[]', true) ?: [];
            $st        = $r['data']['status'] ?? 'not_applied';
            if (! $filter || $filter === $st) {
                $out[] = $r + ['subsidy_status' => $st];
            }
        }
        if ($this->request->getGet('export') === 'excel') {
            $labels = config('Workflow')->subsidyStatuses;

            return $this->excel('Subsidy_Status', ['Project', 'Customer', 'Phone', 'kW', 'Commissioned', 'Application no', 'Applied', 'Status', 'Amount', 'Received on'], array_map(static fn ($r) => [
                $r['project_no'], $r['customer'], $r['phone'], $r['capacity_kw'], $r['commissioned_on'], $r['data']['application_no'] ?? '',
                $r['data']['applied_date'] ?? '', $labels[$r['subsidy_status']] ?? $r['subsidy_status'], $r['data']['subsidy_amount'] ?? '', $r['data']['received_date'] ?? '',
            ], $out));
        }

        return $this->render('reports/subsidy', ['rows' => $out]);
    }

    /** Lets another controller reuse the Excel export with its own request/response. */
    public function initControllerFrom(AdminController $c): self
    {
        $this->request  = $c->request;
        $this->response = $c->response;

        return $this;
    }

    public function excelOut(string $name, array $headers, array $rows)
    {
        return $this->excel($name, $headers, $rows);
    }

    /** Excel (.xls) export: an HTML table Excel opens directly (same approach as the school ERP). */
    protected function excel(string $name, array $headers, array $rows)
    {
        $html = "\xEF\xBB\xBF<table border=\"1\"><thead><tr>";
        foreach ($headers as $h) {
            $html .= '<th>' . esc($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            foreach ($r as $cell) {
                $html .= is_float($cell) || is_int($cell) ? '<td>' . $cell . '</td>' : '<td style="mso-number-format:\@">' . esc((string) $cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $this->response->setHeader('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '_' . date('Ymd_His') . '.xls"')
            ->setBody($html);
    }
}
