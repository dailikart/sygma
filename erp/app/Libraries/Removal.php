<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Deleting saved entries (admin, "entries.delete" permission) without leaving
 * broken links: accounting vouchers are reversed, dispatched stock goes back
 * into the store, and the invoice's paid / due figures are recalculated.
 * Every function expects to run inside a DB transaction.
 */
class Removal
{
    /** Re-adds up the payments of an invoice and sets paid amount + status. */
    public static function recalcInvoice(int $invoiceId): void
    {
        $db  = db_connect();
        $inv = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        if (! $inv) {
            return;
        }
        $paid = round((float) ($db->table('payments')->selectSum('amount')->where('invoice_id', $invoiceId)->get()->getRow()->amount ?? 0), 2);
        $data = ['amount_paid' => $paid, 'updated_at' => date('Y-m-d H:i:s')];
        if ($inv['status'] !== 'cancelled') {
            $data['status'] = $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= (float) $inv['total'] ? 'paid' : 'partial');
        }
        $db->table('invoices')->where('id', $invoiceId)->update($data);
    }

    public static function deletePayment(int $paymentId): array
    {
        $db  = db_connect();
        $pay = $db->table('payments')->where('id', $paymentId)->get()->getRowArray();
        if (! $pay) {
            throw new RuntimeException('Payment not found.');
        }
        Ledger::unpost('payment', $paymentId);
        $db->table('loan_tranches')->where('payment_id', $paymentId)->update(['status' => 'pending', 'payment_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        $db->table('payments')->where('id', $paymentId)->delete();
        self::recalcInvoice((int) $pay['invoice_id']);

        return $pay;
    }

    /** Project with its stages, files, dispatches (stock returned) and AMC. The invoice stays. */
    public static function deleteProject(int $projectId): void
    {
        $db = db_connect();
        foreach ($db->table('dispatches')->select('id')->where('project_id', $projectId)->get()->getResultArray() as $d) {
            Stock::reverse('dispatch', (int) $d['id']);
            $db->table('dispatch_items')->where('dispatch_id', $d['id'])->delete();
        }
        $db->table('dispatches')->where('project_id', $projectId)->delete();
        foreach ($db->table('project_files')->where('project_id', $projectId)->get()->getResultArray() as $f) {
            $path = WRITEPATH . 'uploads/' . ($f['file_path'] ?? $f['path'] ?? '');
            if (! empty($f['file_path'] ?? $f['path'] ?? '') && is_file($path)) {
                @unlink($path);
            }
        }
        $db->table('project_files')->where('project_id', $projectId)->delete();
        $db->table('project_stages')->where('project_id', $projectId)->delete();
        foreach ($db->table('amc_contracts')->select('id')->where('project_id', $projectId)->get()->getResultArray() as $c) {
            $db->table('amc_visits')->where('amc_contract_id', $c['id'])->delete();
        }
        $db->table('amc_contracts')->where('project_id', $projectId)->delete();
        // Keep the customer's documents and project expenses, just unlink them.
        $db->table('customer_documents')->where('project_id', $projectId)->update(['project_id' => null]);
        $db->table('expenses')->where('project_id', $projectId)->update(['project_id' => null]);
        $db->table('invoices')->where('project_id', $projectId)->update(['project_id' => null]);
        $db->table('projects')->where('id', $projectId)->delete();
    }

    /** Invoice with its lines, payments, loan schedule and project. */
    public static function deleteInvoice(int $invoiceId): array
    {
        $db  = db_connect();
        $inv = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        if (! $inv) {
            throw new RuntimeException('Invoice not found.');
        }
        foreach ($db->table('payments')->select('id')->where('invoice_id', $invoiceId)->get()->getResultArray() as $p) {
            Ledger::unpost('payment', (int) $p['id']);
        }
        $db->table('payments')->where('invoice_id', $invoiceId)->delete();
        $db->table('loan_tranches')->where('invoice_id', $invoiceId)->delete();
        foreach ($db->table('projects')->select('id')->where('invoice_id', $invoiceId)->get()->getResultArray() as $pr) {
            self::deleteProject((int) $pr['id']);
        }
        Ledger::unpost('invoice', $invoiceId);
        $db->table('invoice_items')->where('invoice_id', $invoiceId)->delete();
        $db->table('invoices')->where('id', $invoiceId)->delete();

        return $inv;
    }
}
