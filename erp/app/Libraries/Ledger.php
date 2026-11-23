<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Double-entry accounts.
 *
 * Every business document posts one balanced voucher (Σ debit = Σ credit):
 *
 *   Invoice            Sales       Dr Customer          Cr Sales, Cr Output GST
 *   Customer payment   Receipt     Dr Bank/Cash         Cr Customer
 *   Purchase           Purchase    Dr Purchase, Input GST  Cr Supplier
 *   Purchase return    Debit Note  Dr Supplier          Cr Purchase, Cr Input GST
 *   Supplier payment   Payment     Dr Supplier          Cr Bank/Cash
 *   Other income       Receipt     Dr Bank/Cash         Cr Income head
 *   Expense            Payment     Dr Expense head      Cr Bank/Cash
 *   Journal / Contra   (manual, any ledgers)
 *
 * Ledger names and groups follow Tally, so the vouchers export to Tally as is.
 */
class Ledger
{
    public const GROUPS = [
        'Sundry Debtors', 'Sundry Creditors', 'Bank Accounts', 'Cash-in-Hand', 'Sales Accounts',
        'Purchase Accounts', 'Duties & Taxes', 'Direct Expenses', 'Indirect Expenses', 'Direct Incomes',
        'Indirect Incomes', 'Capital Account', 'Loans (Liability)', 'Current Assets', 'Current Liabilities',
        'Fixed Assets', 'Suspense A/c',
    ];

    /** Ledger for a master row (customer, supplier, bank account, income/expense head). Created on first use. */
    public static function forRef(string $refType, int $refId): int
    {
        $db  = db_connect();
        $row = $db->table('ledgers')->select('id')->where(['ref_type' => $refType, 'ref_id' => $refId])->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        [$table, $group] = match ($refType) {
            'customer'     => ['customers', 'Sundry Debtors'],
            'supplier'     => ['suppliers', 'Sundry Creditors'],
            'account'      => ['accounts', null],
            'income_head'  => ['income_heads', 'Indirect Incomes'],
            'expense_head' => ['expense_heads', 'Indirect Expenses'],
            default        => throw new RuntimeException('Unknown ledger type ' . $refType),
        };
        $src = $db->table($table)->where('id', $refId)->get()->getRowArray();
        if (! $src) {
            throw new RuntimeException("Cannot find {$refType} #{$refId} for the ledger.");
        }
        if ($refType === 'account') {
            $group = $src['type'] === 'cash' ? 'Cash-in-Hand' : 'Bank Accounts';
        }
        $opening = match ($refType) {
            'account'  => (float) $src['opening_balance'],    // asset: Dr
            'supplier' => -(float) $src['opening_balance'],   // we owe: Cr
            default    => 0.0,
        };

        return self::create(self::uniqueName($src['name'], $refType, $refId), $group, $refType, $refId, $opening);
    }

    /** System ledger by name (Sales, Purchase, GST…). Created on first use. */
    public static function system(string $name, string $group): int
    {
        $row = db_connect()->table('ledgers')->select('id')->where('name', $name)->get()->getRowArray();

        return $row ? (int) $row['id'] : self::create($name, $group, 'system', null, 0);
    }

    /** Keeps a master's ledger name in step when the master is renamed. */
    public static function syncName(string $refType, int $refId, string $name): void
    {
        $db  = db_connect();
        $row = $db->table('ledgers')->where(['ref_type' => $refType, 'ref_id' => $refId])->get()->getRowArray();
        if ($row && $row['name'] !== $name) {
            $db->table('ledgers')->where('id', $row['id'])->update(['name' => self::uniqueName($name, $refType, $refId, (int) $row['id'])]);
        }
    }

    private static function create(string $name, string $group, ?string $refType, ?int $refId, float $opening): int
    {
        $now = date('Y-m-d H:i:s');
        db_connect()->table('ledgers')->insert([
            'name' => $name, 'group_name' => $group, 'ref_type' => $refType, 'ref_id' => $refId,
            'opening_balance' => $opening, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) db_connect()->insertID();
    }

    /** Two customers can share a name; Tally ledger names must be unique. */
    private static function uniqueName(string $name, string $refType, int $refId, int $exceptId = 0): string
    {
        $name = trim($name);
        $b    = db_connect()->table('ledgers')->where('name', $name);
        if ($exceptId) {
            $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() ? $name . ' (' . strtoupper(substr($refType, 0, 1)) . $refId . ')' : $name;
    }

    /**
     * Posts (or re-posts) the voucher of a document.
     *
     * @param list<array{0:int,1:float,2:float}> $entries [ledger_id, debit, credit]
     */
    public static function post(string $type, string $no, string $date, string $narration, ?string $refType, ?int $refId, array $entries): int
    {
        // Merge lines for the same ledger and drop zero lines.
        $merged = [];
        foreach ($entries as [$ledgerId, $dr, $cr]) {
            $merged[$ledgerId] = ($merged[$ledgerId] ?? 0) + round((float) $dr, 2) - round((float) $cr, 2);
        }
        $merged = array_filter($merged, static fn ($v) => abs($v) >= 0.005);
        $dr     = round(array_sum(array_filter($merged, static fn ($v) => $v > 0)), 2);
        $cr     = round(-array_sum(array_filter($merged, static fn ($v) => $v < 0)), 2);
        if (abs($dr - $cr) > 0.01) {
            throw new RuntimeException("Voucher {$no} does not balance (Dr {$dr} / Cr {$cr}).");
        }

        $db = db_connect();
        if ($refType && $refId) {
            self::unpost($refType, $refId);
        }
        $db->table('vouchers')->insert([
            'voucher_type' => $type, 'voucher_no' => $no, 'voucher_date' => $date, 'narration' => $narration,
            'ref_type' => $refType, 'ref_id' => $refId, 'amount' => $dr,
            'created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $vid = (int) $db->insertID();
        foreach ($merged as $ledgerId => $net) {
            $db->table('voucher_entries')->insert([
                'voucher_id' => $vid, 'ledger_id' => $ledgerId,
                'debit' => $net > 0 ? round($net, 2) : 0, 'credit' => $net < 0 ? round(-$net, 2) : 0,
            ]);
        }

        return $vid;
    }

    public static function unpost(string $refType, int $refId): void
    {
        $db  = db_connect();
        $ids = array_column($db->table('vouchers')->select('id')->where(['ref_type' => $refType, 'ref_id' => $refId])->get()->getResultArray(), 'id');
        if ($ids) {
            $db->table('voucher_entries')->whereIn('voucher_id', $ids)->delete();
            $db->table('vouchers')->whereIn('id', $ids)->delete();
        }
    }

    // ── Posting rules per document ──────────────────────────────────

    public static function postInvoice(int $id): void
    {
        $db  = db_connect();
        $inv = $db->table('invoices')->where('id', $id)->get()->getRowArray();
        if ($inv['status'] === 'cancelled') {
            self::unpost('invoice', $id);

            return;
        }
        $lines   = $db->table('invoice_items')->where('invoice_id', $id)->get()->getResultArray();
        $cust    = $db->table('customers')->select('name')->where('id', $inv['customer_id'])->get()->getRowArray();
        $entries = [[self::forRef('customer', (int) $inv['customer_id']), (float) $inv['total'], 0]];
        $entries[] = [self::system(setting('Solar.tallySalesLedger'), 'Sales Accounts'), 0, (float) $inv['subtotal']];
        foreach (TallyXml::gstSplit($lines, $inv['gst_type'], 'output') as $ledger => $amt) {
            $entries[] = [self::system($ledger, 'Duties & Taxes'), 0, $amt];
        }
        self::post('Sales', $inv['invoice_no'], $inv['invoice_date'],
            'Invoice ' . $inv['invoice_no'] . ' – ' . $cust['name'] . ' – ' . qty($inv['capacity_kw']) . ' kW', 'invoice', $id, $entries);
    }

    public static function postPayment(int $id): void
    {
        $db  = db_connect();
        $p   = $db->table('payments')->where('id', $id)->get()->getRowArray();
        $inv = $db->table('invoices')->select('invoice_no')->where('id', $p['invoice_id'])->get()->getRowArray();
        self::post('Receipt', $p['receipt_no'], $p['payment_date'],
            trim('Against ' . $inv['invoice_no'] . ' ' . strtoupper($p['mode']) . ' ' . ($p['reference'] ?? '')), 'payment', $id, [
                [self::forRef('account', (int) $p['account_id']), (float) $p['amount'], 0],
                [self::forRef('customer', (int) $p['customer_id']), 0, (float) $p['amount']],
            ]);
    }

    public static function postPurchase(int $id): void
    {
        $db = db_connect();
        $p  = $db->table('purchases')->where('id', $id)->get()->getRowArray();
        $entries = [[self::system(setting('Solar.tallyPurchaseLedger'), 'Purchase Accounts'), (float) $p['subtotal'], 0]];
        foreach (TallyXml::gstSplit($db->table('purchase_items')->where('purchase_id', $id)->get()->getResultArray(), $p['gst_type'], 'input') as $ledger => $amt) {
            $entries[] = [self::system($ledger, 'Duties & Taxes'), $amt, 0];
        }
        $entries[] = [self::forRef('supplier', (int) $p['supplier_id']), 0, (float) $p['total']];
        self::post('Purchase', $p['purchase_no'], $p['bill_date'], 'Supplier invoice ' . ($p['bill_no'] ?? '') . ' dt. ' . date('d-m-Y', strtotime($p['bill_date'])), 'purchase', $id, $entries);
    }

    public static function postPurchaseReturn(int $id): void
    {
        $db = db_connect();
        $r  = $db->table('purchase_returns')->where('id', $id)->get()->getRowArray();
        $entries = [[self::forRef('supplier', (int) $r['supplier_id']), (float) $r['total'], 0]];
        $entries[] = [self::system(setting('Solar.tallyPurchaseLedger'), 'Purchase Accounts'), 0, (float) $r['subtotal']];
        foreach (TallyXml::gstSplit($db->table('purchase_return_items')->where('purchase_return_id', $id)->get()->getResultArray(), $r['gst_type'], 'input') as $ledger => $amt) {
            $entries[] = [self::system($ledger, 'Duties & Taxes'), 0, $amt];
        }
        self::post('Debit Note', $r['return_no'], $r['return_date'], 'Purchase return: ' . ($r['reason'] ?? ''), 'purchase_return', $id, $entries);
    }

    public static function postSupplierPayment(int $id): void
    {
        $p = db_connect()->table('supplier_payments')->where('id', $id)->get()->getRowArray();
        self::post('Payment', $p['voucher_no'], $p['payment_date'], trim(strtoupper($p['mode']) . ' ' . ($p['reference'] ?? '') . ' ' . ($p['notes'] ?? '')), 'supplier_payment', $id, [
            [self::forRef('supplier', (int) $p['supplier_id']), (float) $p['amount'], 0],
            [self::forRef('account', (int) $p['account_id']), 0, (float) $p['amount']],
        ]);
    }

    public static function postIncome(int $id): void
    {
        $i = db_connect()->table('incomes')->where('id', $id)->get()->getRowArray();
        self::post('Receipt', $i['voucher_no'], $i['income_date'], trim(($i['received_from'] ?? '') . ' ' . ($i['notes'] ?? '')), 'income', $id, [
            [self::forRef('account', (int) $i['account_id']), (float) $i['amount'], 0],
            [self::forRef('income_head', (int) $i['income_head_id']), 0, (float) $i['amount']],
        ]);
    }

    public static function postExpense(int $id): void
    {
        $e = db_connect()->table('expenses')->where('id', $id)->get()->getRowArray();
        self::post('Payment', $e['voucher_no'], $e['expense_date'], trim(($e['paid_to'] ?? '') . ' ' . ($e['notes'] ?? '')), 'expense', $id, [
            [self::forRef('expense_head', (int) $e['expense_head_id']), (float) $e['amount'], 0],
            [self::forRef('account', (int) $e['account_id']), 0, (float) $e['amount']],
        ]);
    }

    // ── Reports ─────────────────────────────────────────────────────

    /** Balance of a ledger before a date (opening + movements). + = Dr, − = Cr. */
    public static function balanceBefore(int $ledgerId, string $date): float
    {
        $db  = db_connect();
        $l   = $db->table('ledgers')->select('opening_balance')->where('id', $ledgerId)->get()->getRowArray();
        $row = $db->table('voucher_entries e')->select('SUM(e.debit) - SUM(e.credit) AS bal', false)
            ->join('vouchers v', 'v.id = e.voucher_id')
            ->where('e.ledger_id', $ledgerId)->where('v.voucher_date <', $date)->get()->getRowArray();

        return round((float) ($l['opening_balance'] ?? 0) + (float) ($row['bal'] ?? 0), 2);
    }

    /** "1,234.00 Dr" style text. */
    public static function drcr(float $v): string
    {
        return money(abs($v)) . ' ' . ($v >= 0 ? 'Dr' : 'Cr');
    }
}
