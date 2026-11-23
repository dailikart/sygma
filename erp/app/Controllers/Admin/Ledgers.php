<?php

namespace App\Controllers\Admin;

use App\Libraries\Ledger;
use App\Libraries\Numbering;

/**
 * Accounts: ledger master, manual journal / contra vouchers, ledger report,
 * day book and trial balance.
 */
class Ledgers extends AdminController
{
    public function index()
    {
        $this->requirePermission('accounts.view');
        $b = db_connect()->table('ledgers')->orderBy('group_name')->orderBy('name');
        if ($g = $this->request->getGet('group')) {
            $b->where('group_name', $g);
        }

        return $this->render('ledgers/index', ['rows' => $b->get()->getResultArray()]);
    }

    /** Manual ledger (capital, loans, fixed assets…). Customer/supplier/bank ledgers are created automatically. */
    public function store()
    {
        $this->requirePermission('accounts.manage');
        $name  = trim((string) $this->request->getPost('name'));
        $group = $this->request->getPost('group_name');
        if ($name === '' || ! in_array($group, Ledger::GROUPS, true)) {
            return $this->back('Enter a name and choose a group.');
        }
        if (db_connect()->table('ledgers')->where('name', $name)->countAllResults()) {
            return $this->back('A ledger with this name already exists.');
        }
        $ob  = (float) $this->request->getPost('opening_balance');
        $now = date('Y-m-d H:i:s');
        db_connect()->table('ledgers')->insert([
            'name' => $name, 'group_name' => $group, 'ref_type' => 'manual',
            'opening_balance' => $this->request->getPost('opening_type') === 'Cr' ? -$ob : $ob,
            'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return redirect()->back()->with('message', 'Ledger created.');
    }

    public function show($id)
    {
        $this->requirePermission('accounts.view');
        $ledger = $this->findOr404('ledgers', $id);
        [$from, $to] = fy_range();
        $rows = db_connect()->table('voucher_entries e')
            ->select('v.id AS voucher_id, v.voucher_type, v.voucher_no, v.voucher_date, v.narration, v.ref_type, v.ref_id, e.debit, e.credit')
            ->join('vouchers v', 'v.id = e.voucher_id')
            ->where('e.ledger_id', $ledger['id'])->where('v.voucher_date >=', $from)->where('v.voucher_date <=', $to)
            ->orderBy('v.voucher_date')->orderBy('v.id')->get()->getResultArray();

        return $this->render('ledgers/show', [
            'ledger' => $ledger, 'rows' => $rows, 'from' => $from, 'to' => $to,
            'opening' => Ledger::balanceBefore((int) $ledger['id'], $from),
        ]);
    }

    public function dayBook()
    {
        $this->requirePermission('accounts.view');
        [$from, $to] = fy_range();
        if (! service('request')->getGet('from')) {
            $from = $to = date('Y-m-d');
        }
        $db  = db_connect();
        $b   = $db->table('vouchers')->where('voucher_date >=', $from)->where('voucher_date <=', $to);
        if ($t = $this->request->getGet('type')) {
            $b->where('voucher_type', $t);
        }
        $vouchers = $b->orderBy('voucher_date')->orderBy('id')->get()->getResultArray();
        $entries  = [];
        if ($vouchers) {
            foreach ($db->table('voucher_entries e')->select('e.*, l.name')->join('ledgers l', 'l.id = e.ledger_id')
                ->whereIn('e.voucher_id', array_column($vouchers, 'id'))->orderBy('e.debit', 'DESC')->get()->getResultArray() as $e) {
                $entries[$e['voucher_id']][] = $e;
            }
        }

        return $this->render('ledgers/daybook', ['vouchers' => $vouchers, 'entries' => $entries, 'from' => $from, 'to' => $to]);
    }

    public function trialBalance()
    {
        $this->requirePermission('accounts.view');
        [$from, $to] = fy_range();
        $db   = db_connect();
        $move = [];
        foreach ($db->table('voucher_entries e')->select('e.ledger_id, SUM(e.debit) AS dr, SUM(e.credit) AS cr')
            ->join('vouchers v', 'v.id = e.voucher_id')->where('v.voucher_date <=', $to)
            ->groupBy('e.ledger_id')->get()->getResultArray() as $m) {
            $move[$m['ledger_id']] = (float) $m['dr'] - (float) $m['cr'];
        }
        $rows = [];
        foreach ($db->table('ledgers')->orderBy('group_name')->orderBy('name')->get()->getResultArray() as $l) {
            $bal = round((float) $l['opening_balance'] + ($move[$l['id']] ?? 0), 2);
            if (abs($bal) >= 0.01) {
                $rows[] = $l + ['balance' => $bal];
            }
        }

        return $this->render('ledgers/trial', ['rows' => $rows, 'to' => $to, 'from' => $from]);
    }

    public function journal()
    {
        $this->requirePermission('accounts.manage');

        $ledgers = [];
        foreach (db_connect()->table('ledgers')->where('is_active', 1)->orderBy('name')->get()->getResultArray() as $l) {
            $ledgers[$l['id']] = $l['name'] . ' (' . $l['group_name'] . ')';
        }

        return $this->render('ledgers/journal', ['ledgers' => $ledgers, 'nextNo' => Numbering::next('vouchers', 'voucher_no', (string) setting('Solar.journalPrefix'))]);
    }

    public function storeJournal()
    {
        $this->requirePermission('accounts.manage');
        $p    = $this->request->getPost();
        $type = ($p['voucher_type'] ?? '') === 'Contra' ? 'Contra' : 'Journal';
        if (empty($p['voucher_date']) || trim((string) ($p['narration'] ?? '')) === '') {
            return $this->back('Enter the date and narration.');
        }
        $entries = [];
        foreach ((array) ($p['lines'] ?? []) as $l) {
            if (! empty($l['ledger_id']) && ((float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0)) {
                $entries[] = [(int) $l['ledger_id'], (float) ($l['debit'] ?? 0), (float) ($l['credit'] ?? 0)];
            }
        }
        if (count($entries) < 2) {
            return $this->back('A voucher needs at least two lines.');
        }
        try {
            $no = Numbering::next('vouchers', 'voucher_no', (string) setting('Solar.journalPrefix'), $p['voucher_date']);
            Ledger::post($type, $no, $p['voucher_date'], trim($p['narration']), 'manual', null, $entries);
        } catch (\Throwable $e) {
            return $this->back($e->getMessage());
        }

        return redirect()->to('admin/accounts/day-book?from=' . $p['voucher_date'] . '&to=' . $p['voucher_date'])->with('message', $type . ' voucher ' . $no . ' saved.');
    }

    public function deleteJournal($id)
    {
        $this->requirePermission('accounts.manage');
        $v = $this->findOr404('vouchers', $id);
        if ($v['ref_type'] !== 'manual') {
            return $this->back('Only manual journal / contra vouchers can be deleted here. Delete or cancel the source document instead.');
        }
        db_connect()->table('voucher_entries')->where('voucher_id', $v['id'])->delete();
        db_connect()->table('vouchers')->where('id', $v['id'])->delete();

        return redirect()->back()->with('message', 'Voucher deleted.');
    }
}
