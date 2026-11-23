<?php

namespace App\Controllers\Admin;

use App\Libraries\Pdf;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Tally-style voucher printing for every accounting entry:
 *   Receipt  (customer payment, other income) → Formal Receipt
 *   Payment  (expense, supplier payment)      → Payment Voucher
 *   Journal / Contra / Sales / Purchase / Debit Note → Dr / Cr voucher
 */
class VoucherPrint extends AdminController
{
    /** /admin/vouchers/{id}/print */
    public function show($id)
    {
        $this->requirePermission('accounts.view', 'invoices.view', 'payments.manage', 'purchases.view');

        return $this->printVoucher($this->findOr404('vouchers', $id));
    }

    /** /admin/vouchers/print/{refType}/{refId}  e.g. income/5, expense/3, payment/9 */
    public function byRef($refType, $refId)
    {
        $this->requirePermission('accounts.view', 'invoices.view', 'payments.manage', 'purchases.view');
        $v = db_connect()->table('vouchers')->where(['ref_type' => $refType, 'ref_id' => (int) $refId])->get()->getRowArray();
        if (! $v) {
            throw PageNotFoundException::forPageNotFound('Voucher not found.');
        }

        return $this->printVoucher($v);
    }

    private function printVoucher(array $v)
    {
        [$view, $data] = self::build($v);
        Pdf::stream($view, $data, str_replace('/', '-', $v['voucher_no']), (bool) $this->request->getGet('download'), $data['_format'] ?? 'A5-L');
    }

    /** PDF file of a document's voucher (for email attachments), or null. */
    public static function fileForRef(string $refType, int $refId): ?string
    {
        $v = db_connect()->table('vouchers')->where(['ref_type' => $refType, 'ref_id' => $refId])->get()->getRowArray();
        if (! $v) {
            return null;
        }
        [$view, $data] = self::build($v);

        return Pdf::toFile($view, $data, $v['voucher_no'], $data['_format'] ?? 'A5-L');
    }

    /** @return array{0:string,1:array} [view, data] */
    public static function build(array $v): array
    {
        $db      = db_connect();
        $entries = $db->table('voucher_entries e')->select('e.*, l.name, l.group_name, l.ref_type, l.ref_id')
            ->join('ledgers l', 'l.id = e.ledger_id')->where('e.voucher_id', $v['id'])
            ->orderBy('e.debit', 'DESC')->get()->getResultArray();

        $isCash = static fn ($e) => in_array($e['group_name'], ['Bank Accounts', 'Cash-in-Hand'], true);
        $data   = ['v' => $v, 'entries' => $entries, 'party' => null, 'by' => [], 'mode' => '', 'reference' => '', 'cheque' => false];

        // The source document gives mode / reference / party details.
        $src = match ($v['ref_type']) {
            'payment'          => $db->table('payments')->where('id', $v['ref_id'])->get()->getRowArray(),
            'income'           => $db->table('incomes')->where('id', $v['ref_id'])->get()->getRowArray(),
            'expense'          => $db->table('expenses')->where('id', $v['ref_id'])->get()->getRowArray(),
            'supplier_payment' => $db->table('supplier_payments')->where('id', $v['ref_id'])->get()->getRowArray(),
            default            => null,
        } ?? [];
        $data['mode']      = strtoupper((string) ($src['mode'] ?? ''));
        $data['reference'] = (string) ($src['reference'] ?? '');
        $data['cheque']    = ($src['mode'] ?? '') === 'cheque';
        if (($src['mode'] ?? '') === 'loan' && ! empty($src['invoice_id'])) {
            $bank         = $db->table('invoices')->select('loan_bank')->where('id', $src['invoice_id'])->get()->getRow()->loan_bank ?? '';
            $data['mode'] = 'LOAN DISBURSEMENT' . ($bank ? ' from ' . $bank : '');
        }

        if (in_array($v['voucher_type'], ['Receipt', 'Payment'], true)) {
            $receipt         = $v['voucher_type'] === 'Receipt';
            $data['by']      = array_values(array_filter($entries, static fn ($e) => $isCash($e) && ($receipt ? $e['debit'] > 0 : $e['credit'] > 0)));
            $partyEntries    = array_values(array_filter($entries, static fn ($e) => ! $isCash($e)));
            $partyLedger     = $partyEntries[0] ?? null;
            $party           = ['name' => $partyLedger['name'] ?? '', 'address' => ''];
            if ($partyLedger && in_array($partyLedger['ref_type'], ['customer', 'supplier'], true)) {
                $row = $db->table($partyLedger['ref_type'] === 'customer' ? 'customers' : 'suppliers')->where('id', $partyLedger['ref_id'])->get()->getRowArray();
                if ($row) {
                    $party['name']    = $row['name'];
                    $party['address'] = trim(implode("\n", array_filter([$row['address'] ?? '', trim(($row['city'] ?? '') . ' ' . ($row['state'] ?? '') . ' ' . ($row['pincode'] ?? '')), $row['phone'] ?? ''])));
                }
            }
            // "Received from" / "Paid to" typed on income / expense wins, with the head shown as the account.
            $typed = trim((string) ($src['received_from'] ?? $src['paid_to'] ?? ''));
            if ($typed !== '') {
                $party['account'] = $party['name'];
                $party['name']    = $typed;
            }
            if ($v['ref_type'] === 'payment' && ! empty($src['invoice_id'])) {
                $inv              = $db->table('invoices')->select('invoice_no, invoice_date, total, amount_paid')->where('id', $src['invoice_id'])->get()->getRowArray();
                $data['against']  = $inv;
            }
            $data['party'] = $party;
            $view          = $receipt ? 'pdf/voucher_receipt' : 'pdf/voucher_payment';
            if ($v['ref_type'] === 'payment') {
                // Customer payment: company "Payment Receipt" layout.
                $data['pay']     = $src;
                $data['invoice'] = $db->table('invoices')->where('id', $src['invoice_id'])->get()->getRowArray();
                $data['account'] = $db->table('accounts')->select('name, type')->where('id', $src['account_id'])->get()->getRowArray();
                $data['_format'] = 'A4';
                $view            = 'pdf/payment_receipt';
            }
        } else {
            $view = 'pdf/voucher_journal';
        }

        return [$view, $data];
    }
}
