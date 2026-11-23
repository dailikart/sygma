<?php

namespace App\Controllers\Admin;

use App\Libraries\Ledger;
use App\Libraries\Messenger;
use App\Libraries\Notifier;
use App\Libraries\Numbering;
use App\Libraries\Pdf;
use App\Libraries\Workflow;

/**
 * Customer payments (advance / part / full). A receipt is emailed to the
 * customer straight away unless "do not send" is ticked.
 */
class Payments extends AdminController
{
    public function index()
    {
        $this->requirePermission('invoices.view', 'payments.manage');
        [$from, $to] = fy_range();
        $b = db_connect()->table('payments p')
            ->select('p.*, i.invoice_no, c.name AS customer, a.name AS account')
            ->join('invoices i', 'i.id = p.invoice_id')->join('customers c', 'c.id = p.customer_id')
            ->join('accounts a', 'a.id = p.account_id', 'left')
            ->where('p.payment_date >=', $from)->where('p.payment_date <=', $to);
        if ($m = $this->request->getGet('mode')) {
            $b->where('p.mode', $m);
        }
        $rows = $b->orderBy('p.id', 'DESC')->get()->getResultArray();

        return $this->render('payments/index', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    public function store($invoiceId)
    {
        $this->requirePermission('payments.manage');
        $inv = $this->findOr404('invoices', $invoiceId);
        if ($inv['status'] === 'cancelled') {
            return $this->back('This invoice is cancelled.');
        }
        if (! $this->validate([
            'amount'       => 'required|decimal|greater_than[0]',
            'payment_date' => 'required|valid_date[Y-m-d]',
            'mode'         => 'required|in_list[bank,upi,cheque,cash,loan]',
            'account_id'   => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p      = $this->request->getPost();
        $amount = round((float) $p['amount'], 2);
        $due    = round((float) $inv['total'] - (float) $inv['amount_paid'], 2);
        if ($amount > $due + 0.001) {
            return $this->back('Amount is more than the balance due (' . money($due) . ').');
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('payments')->insert([
            'receipt_no'   => Numbering::next('payments', 'receipt_no', setting('Solar.receiptPrefix'), $p['payment_date']),
            'invoice_id'   => $inv['id'], 'customer_id' => $inv['customer_id'], 'account_id' => (int) $p['account_id'],
            'loan_tranche_id' => ($p['loan_tranche_id'] ?? '') ?: null,
            'payment_date' => $p['payment_date'], 'amount' => $amount, 'mode' => $p['mode'],
            'reference'    => ($p['reference'] ?? '') ?: null, 'notes' => ($p['notes'] ?? '') ?: null,
            'created_by'   => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $paymentId = (int) $db->insertID();
        if (! empty($p['loan_tranche_id'])) {
            $db->table('loan_tranches')->where(['id' => (int) $p['loan_tranche_id'], 'invoice_id' => $inv['id'], 'status' => 'pending'])
                ->update(['status' => 'received', 'payment_id' => $paymentId, 'updated_at' => $now]);
        }
        $paid      = round((float) $inv['amount_paid'] + $amount, 2);
        $db->table('invoices')->where('id', $inv['id'])->update([
            'amount_paid' => $paid,
            'status'      => $paid + 0.001 >= (float) $inv['total'] ? 'paid' : 'partial',
            'updated_at'  => $now,
        ]);
        Ledger::postPayment($paymentId);
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->back('Could not save the payment.');
        }
        Workflow::log('invoice', (int) $inv['id'], 'payment', 'Payment of ' . money($amount) . ' recorded');

        // Receipt to the customer, right away (sent by the queue worker).
        $customer = $db->table('customers')->where('id', $inv['customer_id'])->get()->getRowArray();
        $sent     = '';
        if (! empty($p['send_receipt'])) {
            $receiptNo = $db->table('payments')->select('receipt_no')->where('id', $paymentId)->get()->getRow()->receipt_no;
            $pdf       = VoucherPrint::fileForRef('payment', $paymentId);
            $sent = ' ' . Messenger::customer('payment_received', $customer, [
                'amount' => money($amount), 'payment_date' => fdate($p['payment_date']), 'invoice_no' => $inv['invoice_no'],
                'receipt_no' => $receiptNo, 'balance' => money((float) $inv['total'] - $paid),
            ], 'Payment receipt ' . $receiptNo, sprintf(
                "Dear %s,\n\nWe have received %s on %s against invoice %s. Your receipt %s is attached.\nBalance due: %s\n\nThank you.",
                $customer['name'], money($amount), fdate($p['payment_date']), $inv['invoice_no'], $receiptNo,
                money((float) $inv['total'] - $paid)
            ), true, $pdf ? [['path' => $pdf, 'name' => str_replace('/', '-', $receiptNo) . '.pdf']] : []);
        }
        if ($paid + 0.001 >= (float) $inv['total'] && $inv['project_id']) {
            Notifier::role('liaison', 'Invoice fully paid – ' . $inv['invoice_no'], 'EB charges can now be paid for this project.', 'admin/projects/' . $inv['project_id']);
        }

        $rno = $db->table('payments')->select('receipt_no')->where('id', $paymentId)->get()->getRow()->receipt_no;

        return redirect()->to('admin/invoices/' . $inv['id'])->with(str_contains($sent, 'FAILED') ? 'warning' : 'message', 'Payment ' . $rno . ' of ' . money($amount) . ' recorded on ' . $inv['invoice_no'] . '.' . $sent);
    }

    /** Admin: change a saved payment (date, amount, mode, account, reference). The receipt number stays. */
    public function edit($id)
    {
        $this->requirePermission('entries.edit');
        $pay = $this->findOr404('payments', $id);
        $inv = $this->findOr404('invoices', $pay['invoice_id']);

        return $this->render('payments/edit', ['pay' => $pay, 'inv' => $inv, 'accounts' => options('accounts')]);
    }

    public function update($id)
    {
        $this->requirePermission('entries.edit');
        $pay = $this->findOr404('payments', $id);
        $inv = $this->findOr404('invoices', $pay['invoice_id']);
        if (! $this->validate([
            'amount'       => 'required|decimal|greater_than[0]',
            'payment_date' => 'required|valid_date[Y-m-d]',
            'mode'         => 'required|in_list[bank,upi,cheque,cash,loan]',
            'account_id'   => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p      = $this->request->getPost();
        $amount = round((float) $p['amount'], 2);
        $max    = round((float) $inv['total'] - (float) $inv['amount_paid'] + (float) $pay['amount'], 2);
        if ($amount > $max + 0.001) {
            return $this->back('Amount is more than the invoice balance allows (' . money($max) . ').');
        }
        $db = db_connect();
        $db->transException(true)->transStart();
        try {
            $db->table('payments')->where('id', $pay['id'])->update([
                'payment_date' => $p['payment_date'], 'amount' => $amount, 'mode' => $p['mode'], 'account_id' => (int) $p['account_id'],
                'reference' => trim((string) ($p['reference'] ?? '')) ?: null, 'notes' => trim((string) ($p['notes'] ?? '')) ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            \App\Libraries\Removal::recalcInvoice((int) $inv['id']);
            Ledger::unpost('payment', (int) $pay['id']);
            Ledger::postPayment((int) $pay['id']);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back('Could not save: ' . $e->getMessage());
        }
        Workflow::log('invoice', (int) $inv['id'], 'payment_edited', sprintf('Payment %s edited: %s → %s', $pay['receipt_no'], money($pay['amount']), money($amount)));

        return redirect()->to('admin/invoices/' . $inv['id'])->with('message', 'Payment ' . $pay['receipt_no'] . ' updated.');
    }

    public function delete($id)
    {
        $this->requirePermission('entries.delete');
        $pay = $this->findOr404('payments', $id);
        $db  = db_connect();
        $db->transException(true)->transStart();
        try {
            \App\Libraries\Removal::deletePayment((int) $pay['id']);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back('Could not delete: ' . $e->getMessage());
        }
        Workflow::log('invoice', (int) $pay['invoice_id'], 'payment_deleted', 'Payment ' . $pay['receipt_no'] . ' of ' . money($pay['amount']) . ' deleted');

        return redirect()->to('admin/invoices/' . $pay['invoice_id'])->with('message', 'Payment ' . $pay['receipt_no'] . ' deleted.');
    }

    public function receipt($id)
    {
        $this->requirePermission('invoices.view', 'payments.manage');
        $db  = db_connect();
        $pay = $db->table('payments p')->select('p.*, a.name AS account')->join('accounts a', 'a.id = p.account_id', 'left')
            ->where('p.id', (int) $id)->get()->getRowArray();
        if (! $pay) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $inv = $db->table('invoices')->where('id', $pay['invoice_id'])->get()->getRowArray();
        Pdf::stream('pdf/receipt', [
            'pay' => $pay, 'inv' => $inv,
            'customer' => $db->table('customers')->where('id', $pay['customer_id'])->get()->getRowArray(),
        ], str_replace('/', '-', $pay['receipt_no']));
    }
}
