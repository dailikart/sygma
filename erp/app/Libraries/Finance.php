<?php

namespace App\Libraries;

use RuntimeException;

/**
 * How the customer pays: cash (self-funded) or bank loan. For a loan, the
 * bank pays the company directly, usually in 3 disbursements (tranches).
 */
class Finance
{
    public const MODES = ['' => 'Not decided yet', 'cash' => 'Cash / self-funded', 'loan' => 'Bank loan'];

    /** Finance mode can change only until material is dispatched. */
    public static function editable(array $invoice): bool
    {
        if ($invoice['status'] === 'cancelled') {
            return false;
        }
        if (! $invoice['project_id']) {
            return true;
        }

        return ! db_connect()->table('dispatches')->where('project_id', $invoice['project_id'])
            ->whereIn('status', ['dispatched', 'delivered'])->countAllResults();
    }

    public static function setMode(int $invoiceId, string $mode, ?string $bank, float $loanAmount, ?string $ref): void
    {
        if (! array_key_exists($mode, self::MODES)) {
            throw new RuntimeException('Choose cash or loan.');
        }
        $db  = db_connect();
        $inv = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        if ($mode === 'loan') {
            if ($loanAmount <= 0 || $loanAmount > (float) $inv['total'] + 0.001) {
                throw new RuntimeException('Enter the loan amount (not more than the invoice total).');
            }
            if (trim((string) $bank) === '') {
                throw new RuntimeException('Enter the bank / lender name.');
            }
        }
        $db->table('invoices')->where('id', $invoiceId)->update([
            'finance_mode' => $mode,
            'loan_bank'    => $mode === 'loan' ? trim((string) $bank) : null,
            'loan_amount'  => $mode === 'loan' ? round($loanAmount, 2) : 0,
            'loan_ref'     => $mode === 'loan' ? (trim((string) $ref) ?: null) : null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        // Default schedule: 3 equal disbursements (can be edited / more added).
        if ($mode === 'loan' && ! $db->table('loan_tranches')->where('invoice_id', $invoiceId)->countAllResults()) {
            $part = floor($loanAmount / 3 * 100) / 100;
            foreach ([1, 2, 3] as $n) {
                $db->table('loan_tranches')->insert([
                    'invoice_id' => $invoiceId, 'seq' => $n,
                    'amount' => $n < 3 ? $part : round($loanAmount - 2 * $part, 2),
                    'status' => 'pending', 'created_by' => auth()->id(),
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        if ($mode !== 'loan') {
            $db->table('loan_tranches')->where(['invoice_id' => $invoiceId, 'status' => 'pending'])->delete();
        }
        Workflow::log('invoice', $invoiceId, 'finance', 'Payment mode set to ' . self::MODES[$mode]
            . ($mode === 'loan' ? ' – ' . $bank . ', loan ' . money($loanAmount) : ''));
    }

    public static function tranches(int $invoiceId): array
    {
        return db_connect()->table('loan_tranches t')->select('t.*, p.receipt_no, p.payment_date')
            ->join('payments p', 'p.id = t.payment_id', 'left')
            ->where('t.invoice_id', $invoiceId)->orderBy('t.seq')->get()->getResultArray();
    }
}
