<?php

namespace App\Controllers\Admin;

use App\Libraries\Ledger;
use App\Libraries\Numbering;

/**
 * Shared code for simple money vouchers: Income, Expense, Supplier Payment.
 */
abstract class VoucherDocs extends AdminController
{
    protected string $table;
    protected string $dateField;
    protected string $prefixKey;
    protected string $uri;
    protected string $title;
    protected string $headTable;   // income_heads | expense_heads | suppliers
    protected string $headField;   // income_head_id | expense_head_id | supplier_id
    protected string $headLabel;
    protected string $partyField;  // received_from | paid_to | ''
    protected string $postMethod;  // Ledger::postIncome …

    public function index()
    {
        $this->requirePermission('accounts.view');
        [$from, $to] = fy_range();
        $b = db_connect()->table($this->table . ' v')->select('v.*, h.name AS head, a.name AS account')
            ->join($this->headTable . ' h', 'h.id = v.' . $this->headField)
            ->join('accounts a', 'a.id = v.account_id')
            ->where('v.' . $this->dateField . ' >=', $from)->where('v.' . $this->dateField . ' <=', $to);
        if ($h = $this->request->getGet('head')) {
            $b->where('v.' . $this->headField, (int) $h);
        }

        $rows = $b->orderBy('v.' . $this->dateField, 'DESC')->orderBy('v.id', 'DESC')->get()->getResultArray();
        if ($this->request->getGet('export') === 'excel') {
            $party = $this->partyField;
            $dateF = $this->dateField;

            return (new Reports())->initControllerFrom($this)->excelOut(str_replace(' ', '_', $this->title) . '_' . $from . '_' . $to,
                array_merge(['Voucher no', 'Date', $this->headLabel], $party ? [$party === 'paid_to' ? 'Paid to' : 'Received from'] : [], ['Account', 'Reference', 'Notes', 'Entered by', 'Amount']),
                array_map(static fn ($r) => array_merge([$r['voucher_no'], fdate($r[$dateF]), $r['head']], $party ? [(string) $r[$party]] : [],
                    [$r['account'] . (isset($r['mode']) ? ' (' . strtoupper($r['mode']) . ')' : ''), (string) $r['reference'], (string) $r['notes'], user_name($r['created_by']), (float) $r['amount']]), $rows));
        }

        return $this->render('vouchers/index', [
            'rows' => $rows,
            'from' => $from, 'to' => $to, 'c' => $this->meta(), 'heads' => options($this->headTable),
        ]);
    }

    public function form()
    {
        $this->requirePermission('accounts.manage');

        return $this->render('vouchers/form', [
            'c' => $this->meta(), 'heads' => options($this->headTable), 'accounts' => options('accounts'),
            'projects' => $this->table === 'expenses' ? $this->projectOptions() : [],
            'nextNo'   => Numbering::next($this->table, 'voucher_no', (string) setting($this->prefixKey)),
        ]);
    }

    public function store()
    {
        $this->requirePermission('accounts.manage');
        if (! $this->validate([
            'head_id'    => 'required|is_natural_no_zero',
            'account_id' => 'required|is_natural_no_zero',
            'doc_date'   => 'required|valid_date[Y-m-d]',
            'amount'     => 'required|decimal|greater_than[0]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p   = $this->request->getPost();
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $row = [
            'voucher_no'      => Numbering::next($this->table, 'voucher_no', (string) setting($this->prefixKey), $p['doc_date']),
            $this->headField  => (int) $p['head_id'],
            'account_id'      => (int) $p['account_id'],
            $this->dateField  => $p['doc_date'],
            'amount'          => round((float) $p['amount'], 2),
            'reference'       => ($p['reference'] ?? '') ?: null,
            'notes'           => ($p['notes'] ?? '') ?: null,
            'created_by'      => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ];
        if ($this->partyField) {
            $row[$this->partyField] = ($p['party'] ?? '') ?: null;
        }
        if ($this->table === 'supplier_payments') {
            $row['mode'] = in_array($p['mode'] ?? '', ['bank', 'upi', 'cheque', 'cash'], true) ? $p['mode'] : 'bank';
        }
        if ($this->table === 'expenses') {
            $row['project_id'] = ($p['project_id'] ?? '') ?: null;
            try {
                $row['attachment'] = $this->storeUpload('attachment', 'expenses');
            } catch (\RuntimeException $e) {
                return $this->back($e->getMessage());
            }
        }

        $db->transException(true)->transStart();
        try {
            $db->table($this->table)->insert($row);
            $id = (int) $db->insertID();
            Ledger::{$this->postMethod}($id);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back($e->getMessage());
        }

        return redirect()->to('admin/' . $this->uri)->with('message', $this->title . ' entry ' . $row['voucher_no'] . ' saved.');
    }

    public function delete($id)
    {
        $this->requirePermission('accounts.manage');
        $row = $this->findOr404($this->table, $id);
        $db  = db_connect();
        $db->transStart();
        Ledger::unpost($this->tableRef(), (int) $row['id']);
        $db->table($this->table)->where('id', $row['id'])->delete();
        $db->transComplete();

        return redirect()->back()->with('message', 'Entry deleted.');
    }

    private function projectOptions(): array
    {
        $out = [];
        foreach (db_connect()->table('projects p')->select('p.id, p.project_no, c.name')->join('customers c', 'c.id = p.customer_id')
            ->whereIn('p.status', ['active', 'on_hold', 'completed'])->orderBy('p.id', 'DESC')->limit(300)->get()->getResultArray() as $r) {
            $out[$r['id']] = $r['project_no'] . ' – ' . $r['name'];
        }

        return $out;
    }

    private function tableRef(): string
    {
        return match ($this->table) {
            'incomes' => 'income', 'expenses' => 'expense', default => 'supplier_payment',
        };
    }

    protected function meta(): array
    {
        return [
            'title' => $this->title, 'uri' => $this->uri, 'date' => $this->dateField, 'headLabel' => $this->headLabel,
            'party' => $this->partyField, 'table' => $this->table,
        ];
    }
}
