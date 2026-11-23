<?php

namespace App\Controllers\Admin;

use App\Libraries\Finance;
use App\Libraries\Ledger;
use App\Libraries\Messenger;
use App\Libraries\Notifier;
use App\Libraries\Numbering;
use App\Libraries\Pdf;
use App\Libraries\Tax;
use App\Libraries\Workflow;

/**
 * Invoices. Saving an invoice also creates the installation project
 * (subsidy / non-subsidy) which starts with the site survey.
 */
class Invoices extends AdminController
{
    public function index()
    {
        $this->requirePermission('invoices.view');
        $b = db_connect()->table('invoices i')->select('i.*, c.name AS customer, c.type AS customer_type')
            ->join('customers c', 'c.id = i.customer_id');
        if ($s = $this->request->getGet('status')) {
            $s === 'unpaid' ? $b->whereIn('i.status', ['unpaid', 'partial']) : $b->where('i.status', $s);
        }
        if ($t = $this->request->getGet('subsidy')) {
            $b->where('i.subsidy_type', $t);
        }
        if (($fm = $this->request->getGet('finance')) !== null && $fm !== '') {
            $fm === 'none' ? $b->where('i.finance_mode', '') : $b->where('i.finance_mode', $fm);
        }
        if ($ct = $this->request->getGet('type')) {
            $b->where('c.type', $ct);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('i.invoice_no', $q)->orLike('c.name', $q)->groupEnd();
        }
        [$from, $to] = fy_range();
        $b->where('i.invoice_date >=', $from)->where('i.invoice_date <=', $to);

        $rows = $b->orderBy('i.id', 'DESC')->get()->getResultArray();

        // EB status per invoice, from its project's EB / Net Meter stage.
        $eb = [];
        if ($rows) {
            foreach (db_connect()->table('projects p')->select('p.invoice_id, s.data')
                ->join('project_stages s', "s.project_id = p.id AND s.stage = 'eb_process'")
                ->whereIn('p.invoice_id', array_column($rows, 'id'))->get()->getResultArray() as $e) {
                $d = json_decode((string) $e['data'], true) ?: [];
                $eb[$e['invoice_id']] = [
                    'payment'    => ! empty($d['steps']['eb_charges_paid']),
                    'submission' => ($d['eb_submission_status'] ?? 'no') === 'yes',
                ];
            }
        }

        return $this->render('invoices/index', ['rows' => $rows, 'eb' => $eb, 'from' => $from, 'to' => $to]);
    }

    public function create()
    {
        $this->requirePermission('invoices.manage');
        $customerId = (int) $this->request->getGet('customer');

        return $this->render('invoices/form', [
            'customers' => db_connect()->table('customers')->select('id, name, type, city, state, eb_charges_by, eb_charges_amount')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
            'items'     => db_connect()->table('items')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray(),
            'kits'      => db_connect()->table('kits')->where('is_active', 1)->orderBy('capacity_kw')->get()->getResultArray(),
            'types'     => options('installation_types'),
            'customerId' => $customerId,
            'nextNo'    => Numbering::next('invoices', 'invoice_no', setting('Solar.invoicePrefix')),
        ]);
    }

    public function store()
    {
        $this->requirePermission('invoices.manage');
        if (! $this->validate([
            'customer_id'  => 'required|is_natural_no_zero',
            'invoice_date' => 'required|valid_date[Y-m-d]',
            'due_date'     => 'permit_empty|valid_date[Y-m-d]',
            'subsidy_type' => 'required|in_list[subsidy,non_subsidy]',
            'capacity_kw'  => 'required|decimal|greater_than[0]',
            'print_format' => 'permit_empty|in_list[itemwise,cumulative]',
            'finance_mode' => 'permit_empty|in_list[cash,loan]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p        = $this->request->getPost();
        $customer = $this->findOr404('customers', $p['customer_id']);
        [$lines, $sub, $tax, $total] = Tax::lines($this->postedRows());
        if ($lines === []) {
            return $this->back('Add at least one item line.');
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transException(true)->transStart();
        try {
            $db->table('invoices')->insert([
                'invoice_no'   => Numbering::next('invoices', 'invoice_no', setting('Solar.invoicePrefix'), $p['invoice_date']),
                'customer_id'  => $customer['id'],
                'invoice_date' => $p['invoice_date'],
                'due_date'     => ($p['due_date'] ?? '') ?: null,
                'subsidy_type' => $p['subsidy_type'],
                'capacity_kw'  => (float) $p['capacity_kw'],
                'installation_type_id' => ($p['installation_type_id'] ?? '') ?: null,
                'gst_type'     => Tax::gstType($customer['state']),
                'subtotal'     => $sub, 'tax_total' => $tax, 'total' => $total,
                'status'       => 'unpaid',
                'print_format' => ($p['print_format'] ?? '') ?: ($customer['type'] === 'b2b' ? 'itemwise' : 'cumulative'),
                'finance_mode' => '',
                'notes'        => ($p['notes'] ?? '') ?: null,
            ] + eb_charges_input(array_key_exists('eb_charges_by', $p) ? $p : $customer) + [
                'created_by'   => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $invoiceId = (int) $db->insertID();
            foreach ($lines as $l) {
                $db->table('invoice_items')->insert([
                    'invoice_id' => $invoiceId, 'item_id' => ($l['item_id'] ?? '') ?: null,
                    'description' => trim($l['description'] ?? '') ?: 'Item', 'hsn' => $l['hsn'] ?? null,
                    'qty' => $l['qty'], 'rate' => $l['rate'], 'tax_percent' => $l['tax_percent'],
                    'tax_amount' => $l['tax_amount'], 'amount' => $l['amount'],
                ]);
            }
            Ledger::postInvoice($invoiceId);
            if (($p['finance_mode'] ?? '') !== '') {
                Finance::setMode($invoiceId, $p['finance_mode'], $p['loan_bank'] ?? null, (float) ($p['loan_amount'] ?? 0), $p['loan_ref'] ?? null);
            }
            if (! empty($p['create_project'])) {
                Workflow::createProject($invoiceId, (int) ($p['kit_id'] ?? 0) ?: null);
            }
            $db->transComplete();
            $sent = ! empty($p['email_invoice']) ? ' ' . $this->sendInvoice($invoiceId) : '';
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back('Could not save the invoice: ' . $e->getMessage());
        }

        return redirect()->to('admin/invoices/' . $invoiceId)->with(str_contains($sent, 'FAILED') ? 'warning' : 'message', 'Invoice created' . (! empty($p['create_project']) ? ' and the installation project started (site survey).' : '.') . $sent);
    }

    public function show($id)
    {
        $this->requirePermission('invoices.view');

        return $this->render('invoices/show', $this->data((int) $id) + [
            'accounts' => options('accounts'),
        ]);
    }

    public function pdf($id)
    {
        $this->requirePermission('invoices.view');
        $d = $this->data((int) $id);
        Pdf::stream('pdf/invoice', $d, str_replace('/', '-', $d['inv']['invoice_no']), (bool) $this->request->getGet('download'));
    }

    /** Emails the invoice PDF (and WhatsApp, if set up). Returns the result text. */
    private function sendInvoice(int $id, ?string $to = null): string
    {
        $d        = $this->data($id);
        $customer = $d['customer'];
        if ($to) {
            $customer['email'] = $to;
        }
        $pdf = \App\Libraries\Pdf::toFile('pdf/invoice', $d, $d['inv']['invoice_no']);
        $res = Messenger::customer('invoice_created', $customer, [
            'invoice_no' => $d['inv']['invoice_no'], 'invoice_date' => fdate($d['inv']['invoice_date']),
            'total' => money($d['inv']['total']), 'capacity_kw' => qty($d['inv']['capacity_kw']),
        ], 'Invoice ' . $d['inv']['invoice_no'] . ' – ' . setting('Solar.companyName'), sprintf(
            "Dear %s,\n\nThank you for choosing us. Please find attached invoice %s dated %s for your %s kW solar system.\nInvoice total: %s\nAmount due: %s",
            $customer['name'], $d['inv']['invoice_no'], fdate($d['inv']['invoice_date']), qty($d['inv']['capacity_kw']),
            money($d['inv']['total']), money($d['inv']['total'] - $d['inv']['amount_paid'])
        ), true, [['path' => $pdf, 'name' => str_replace('/', '-', $d['inv']['invoice_no']) . '.pdf']]);
        Workflow::log('invoice', $id, 'emailed', $res);

        return $res;
    }

    public function email($id)
    {
        $this->requirePermission('invoices.view');
        $d  = $this->data((int) $id);
        $to = trim((string) $this->request->getPost('to')) ?: $d['customer']['email'];
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->back('The customer has no valid email address.');
        }
        $res = $this->sendInvoice((int) $id, $to);

        return redirect()->back()->with(str_contains($res, 'FAILED') ? 'error' : 'message', $res);
    }

    /** Who pays the EB / meter charges (can be changed any time). */
    public function ebCharges($id)
    {
        $this->requirePermission('invoices.manage', 'payments.manage');
        $inv  = $this->findOr404('invoices', $id);
        $data = eb_charges_input($this->request->getPost());
        db_connect()->table('invoices')->where('id', $inv['id'])->update($data + ['updated_at' => date('Y-m-d H:i:s')]);
        \App\Libraries\Workflow::log('invoice', (int) $inv['id'], 'eb_charges', 'EB / meter charges: ' . (eb_charges_label($data) ?: 'not decided') . ($data['eb_charges_by'] ? ' (' . $data['eb_charges_by'] . ')' : ''));

        return redirect()->back()->with('message', 'EB / meter charges saved.');
    }

    /** Cash / loan details (until material is dispatched). */
    public function finance($id)
    {
        $this->requirePermission('invoices.manage', 'projects.manage', 'payments.manage');
        $inv = $this->findOr404('invoices', $id);
        if (! Finance::editable($inv)) {
            return $this->back('Material is already dispatched – the payment mode can no longer be changed.');
        }
        $p = $this->request->getPost();
        try {
            Finance::setMode((int) $id, (string) ($p['finance_mode'] ?? ''), $p['loan_bank'] ?? null, (float) ($p['loan_amount'] ?? 0), $p['loan_ref'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->back($e->getMessage());
        }
        if (in_array($p['print_format'] ?? '', ['itemwise', 'cumulative'], true)) {
            db_connect()->table('invoices')->where('id', $id)->update(['print_format' => $p['print_format']]);
        }

        return redirect()->back()->with('message', 'Payment mode saved: ' . Finance::MODES[$p['finance_mode'] ?? ''] . '.');
    }

    /** Save loan disbursement schedule rows (amount, expected date) and add rows. */
    public function tranches($id)
    {
        $this->requirePermission('invoices.manage', 'payments.manage');
        $inv  = $this->findOr404('invoices', $id);
        if ($inv['finance_mode'] !== 'loan') {
            return $this->back('This invoice is not on a bank loan.');
        }
        $db   = db_connect();
        $now  = date('Y-m-d H:i:s');
        $seq  = (int) $db->table('loan_tranches')->selectMax('seq')->where('invoice_id', $id)->get()->getRow()->seq;
        foreach ((array) $this->request->getPost('t') as $tid => $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            $date   = ($row['expected_date'] ?? '') ?: null;
            if (str_starts_with((string) $tid, 'new')) {
                if ($amount > 0) {
                    $db->table('loan_tranches')->insert(['invoice_id' => $id, 'seq' => ++$seq, 'amount' => $amount, 'expected_date' => $date,
                        'status' => 'pending', 'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
                }
                continue;
            }
            $db->table('loan_tranches')->where(['id' => (int) $tid, 'invoice_id' => $id, 'status' => 'pending'])
                ->update(['amount' => $amount, 'expected_date' => $date, 'notes' => trim((string) ($row['notes'] ?? '')) ?: null, 'updated_at' => $now]);
        }
        foreach ((array) $this->request->getPost('delete') as $tid) {
            $db->table('loan_tranches')->where(['id' => (int) $tid, 'invoice_id' => $id, 'status' => 'pending'])->delete();
        }
        $sum = (float) $db->table('loan_tranches')->selectSum('amount')->where('invoice_id', $id)->get()->getRow()->amount;
        $msg = 'Loan schedule saved.';
        if (abs($sum - (float) $inv['loan_amount']) > 0.01) {
            $msg .= ' Note: disbursements total ' . money($sum) . ' but the loan amount is ' . money($inv['loan_amount']) . '.';
        }

        return redirect()->to('admin/invoices/' . $id . '#loan')->with('message', $msg);
    }

    /** Admin: change a saved invoice (customer, dates, kW, lines, amounts). */
    public function edit($id)
    {
        $this->requirePermission('entries.edit');
        $inv = $this->findOr404('invoices', $id);
        if ($inv['status'] === 'cancelled') {
            return $this->back('A cancelled invoice cannot be edited.');
        }
        $db = db_connect();

        return $this->render('invoices/form', [
            'inv'       => $inv,
            'rows'      => $db->table('invoice_items')->where('invoice_id', $inv['id'])->orderBy('id')->get()->getResultArray(),
            'customers' => $db->table('customers')->select('id, name, type, city, state, eb_charges_by, eb_charges_amount')->groupStart()->where('deleted_at', null)->orWhere('id', $inv['customer_id'])->groupEnd()->orderBy('name')->get()->getResultArray(),
            'items'     => $db->table('items')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray(),
            'kits'      => $db->table('kits')->where('is_active', 1)->orderBy('capacity_kw')->get()->getResultArray(),
            'types'     => options('installation_types'),
            'customerId' => (int) $inv['customer_id'],
            'nextNo'    => $inv['invoice_no'],
        ]);
    }

    public function update($id)
    {
        $this->requirePermission('entries.edit');
        $inv = $this->findOr404('invoices', $id);
        if ($inv['status'] === 'cancelled') {
            return $this->back('A cancelled invoice cannot be edited.');
        }
        if (! $this->validate([
            'customer_id'  => 'required|is_natural_no_zero',
            'invoice_date' => 'required|valid_date[Y-m-d]',
            'due_date'     => 'permit_empty|valid_date[Y-m-d]',
            'subsidy_type' => 'required|in_list[subsidy,non_subsidy]',
            'capacity_kw'  => 'required|decimal|greater_than[0]',
            'print_format' => 'permit_empty|in_list[itemwise,cumulative]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $p        = $this->request->getPost();
        $customer = $this->findOr404('customers', $p['customer_id']);
        [$lines, $sub, $tax, $total] = Tax::lines($this->postedRows());
        if ($lines === []) {
            return $this->back('Add at least one item line.');
        }
        if ($total + 0.001 < (float) $inv['amount_paid']) {
            return $this->back('The new total (' . money($total) . ') is less than the amount already paid (' . money($inv['amount_paid']) . '). Delete or edit a payment first.');
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transException(true)->transStart();
        try {
            $db->table('invoices')->where('id', $inv['id'])->update([
                'customer_id'  => $customer['id'],
                'invoice_date' => $p['invoice_date'],
                'due_date'     => ($p['due_date'] ?? '') ?: null,
                'subsidy_type' => $p['subsidy_type'],
                'capacity_kw'  => (float) $p['capacity_kw'],
                'installation_type_id' => ($p['installation_type_id'] ?? '') ?: null,
                'gst_type'     => Tax::gstType($customer['state']),
                'subtotal'     => $sub, 'tax_total' => $tax, 'total' => $total,
                'print_format' => ($p['print_format'] ?? '') ?: $inv['print_format'],
                'notes'        => ($p['notes'] ?? '') ?: null,
                'updated_at'   => $now,
            ] + eb_charges_input($p));
            $db->table('invoice_items')->where('invoice_id', $inv['id'])->delete();
            foreach ($lines as $l) {
                $db->table('invoice_items')->insert([
                    'invoice_id' => $inv['id'], 'item_id' => ($l['item_id'] ?? '') ?: null,
                    'description' => trim($l['description'] ?? '') ?: 'Item', 'hsn' => $l['hsn'] ?? null,
                    'qty' => $l['qty'], 'rate' => $l['rate'], 'tax_percent' => $l['tax_percent'],
                    'tax_amount' => $l['tax_amount'], 'amount' => $l['amount'],
                ]);
            }
            // Keep the project and payments in step with the invoice.
            $db->table('projects')->where('invoice_id', $inv['id'])->update([
                'customer_id' => $customer['id'], 'customer_type' => $customer['type'], 'subsidy_type' => $p['subsidy_type'],
                'capacity_kw' => (float) $p['capacity_kw'], 'installation_type_id' => ($p['installation_type_id'] ?? '') ?: null,
                'kit_id' => ((int) ($p['kit_id'] ?? 0)) ?: $db->table('projects')->select('kit_id')->where('invoice_id', $inv['id'])->get()->getRow('kit_id'),
            ]);
            $db->table('payments')->where('invoice_id', $inv['id'])->update(['customer_id' => $customer['id']]);
            \App\Libraries\Removal::recalcInvoice((int) $inv['id']);
            Ledger::unpost('invoice', (int) $inv['id']);
            Ledger::postInvoice((int) $inv['id']);
            foreach ($db->table('payments')->select('id')->where('invoice_id', $inv['id'])->get()->getResultArray() as $pay) {
                Ledger::unpost('payment', (int) $pay['id']);
                Ledger::postPayment((int) $pay['id']);
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back('Could not save: ' . $e->getMessage());
        }
        Workflow::log('invoice', (int) $inv['id'], 'edited', sprintf('Invoice edited: total %s → %s', money($inv['total']), money($total)));

        return redirect()->to('admin/invoices/' . $inv['id'])->with('message', 'Invoice ' . $inv['invoice_no'] . ' updated.');
    }

    /** Admin: delete the invoice with its payments, loan schedule and project (stock returned). */
    public function delete($id)
    {
        $this->requirePermission('entries.delete');
        $inv = $this->findOr404('invoices', $id);
        $db  = db_connect();
        $db->transException(true)->transStart();
        try {
            \App\Libraries\Removal::deleteInvoice((int) $inv['id']);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->back('Could not delete: ' . $e->getMessage());
        }
        Workflow::log('invoice', (int) $inv['id'], 'deleted', 'Invoice ' . $inv['invoice_no'] . ' (' . money($inv['total']) . ') deleted with its payments and project');

        return redirect()->to('admin/invoices')->with('message', 'Invoice ' . $inv['invoice_no'] . ' deleted.');
    }

    public function cancel($id)
    {
        $this->requirePermission('invoices.manage');
        $inv = $this->findOr404('invoices', $id);
        if ((float) $inv['amount_paid'] > 0) {
            return $this->back('This invoice has payments. It cannot be cancelled.');
        }
        $db = db_connect();
        if ($inv['project_id'] && $db->table('dispatches')->where('project_id', $inv['project_id'])->whereIn('status', ['dispatched', 'delivered'])->countAllResults()) {
            return $this->back('Material has already been dispatched for this project. It cannot be cancelled.');
        }
        $db->table('invoices')->where('id', $inv['id'])->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        Ledger::unpost('invoice', (int) $inv['id']);
        if ($inv['project_id']) {
            $db->table('projects')->where('id', $inv['project_id'])->update(['status' => 'cancelled']);
            $db->table('project_stages')->where('project_id', $inv['project_id'])->whereIn('status', ['active', 'pending'])->update(['status' => 'pending']);
        }
        Workflow::log('invoice', (int) $inv['id'], 'cancelled', 'Invoice cancelled');

        return redirect()->back()->with('message', 'Invoice marked as cancelled.');
    }

    private function data(int $id): array
    {
        $inv = $this->findOr404('invoices', $id);
        $db  = db_connect();

        return [
            'inv'      => $inv,
            'customer' => $db->table('customers')->where('id', $inv['customer_id'])->get()->getRowArray(),
            'lines'    => $db->table('invoice_items')->where('invoice_id', $id)->orderBy('id')->get()->getResultArray(),
            'payments' => $db->table('payments p')->select('p.*, a.name AS account')->join('accounts a', 'a.id = p.account_id', 'left')
                ->where('p.invoice_id', $id)->orderBy('p.id')->get()->getResultArray(),
            'project'  => $inv['project_id'] ? $db->table('projects')->where('id', $inv['project_id'])->get()->getRowArray() : null,
            'tranches' => Finance::tranches($id),
            'financeEditable' => Finance::editable($inv),
            'type'     => $inv['installation_type_id'] ? $db->table('installation_types')->select('name')->where('id', $inv['installation_type_id'])->get()->getRow()->name ?? '' : '',
            'log'      => $db->table('activity_log')->where(['entity' => 'invoice', 'entity_id' => $id])->orderBy('id', 'DESC')->get()->getResultArray(),
        ];
    }
}
