<?php

namespace App\Controllers;

use App\Libraries\Workflow;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The customer's project status page (no login). The long random token in the
 * link is the only key; it shows only this customer's own project.
 */
class PublicStatus extends BaseController
{
    public function show(string $token)
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw PageNotFoundException::forPageNotFound();
        }
        helper('app');
        $db = db_connect();
        $p  = $db->table('projects')->where('status_token', $token)->get()->getRowArray();
        if (! $p) {
            throw PageNotFoundException::forPageNotFound();
        }
        $db->table('projects')->where('id', $p['id'])->update(['status_viewed_at' => date('Y-m-d H:i:s')]);
        $customer = $db->table('customers')->select('id, name, city, phone')->where('id', $p['customer_id'])->get()->getRowArray();
        $invoice  = $p['invoice_id'] ? $db->table('invoices')->select('id, invoice_no, invoice_date, total, amount_paid, status, finance_mode, loan_bank, eb_charges_by, eb_charges_amount')->where('id', $p['invoice_id'])->get()->getRowArray() : null;
        $stages   = Workflow::stages((int) $p['id']);
        $subsidy  = null;
        foreach ($stages as $s) {
            if ($s['stage'] === 'subsidy') {
                $subsidy = $s;
            }
        }
        $required = $db->table('document_types')->select('id, name')->where(['is_active' => 1, 'is_required' => 1])->orderBy('sort_order')->orderBy('name')->get()->getResultArray();
        $have     = array_flip(array_map('intval', array_column($db->table('customer_documents')->select('document_type_id')->where('customer_id', $p['customer_id'])->where('document_type_id IS NOT NULL', null, false)->get()->getResultArray(), 'document_type_id')));
        $contract = $db->table('amc_contracts')->where('project_id', $p['id'])->orderBy('id', 'DESC')->get()->getRowArray();

        return view('public/status', [
            'p' => $p, 'customer' => $customer, 'invoice' => $invoice, 'stages' => $stages, 'subsidy' => $subsidy,
            'type' => $p['installation_type_id'] ? (string) $db->table('installation_types')->select('name')->where('id', $p['installation_type_id'])->get()->getRow('name') : '',
            'payments' => $invoice ? $db->table('payments')->select('receipt_no, payment_date, amount, mode')->where('invoice_id', $invoice['id'])->orderBy('payment_date')->get()->getResultArray() : [],
            'tranches' => $invoice && $invoice['finance_mode'] === 'loan' ? $db->table('loan_tranches')->where('invoice_id', $invoice['id'])->orderBy('seq')->get()->getResultArray() : [],
            'dispatches' => $db->table('dispatches')->select('dispatch_no, status, dispatched_at, delivered_at')->where('project_id', $p['id'])->whereIn('status', ['dispatched', 'delivered'])->orderBy('id')->get()->getResultArray(),
            'docs' => array_map(static fn ($d) => $d + ['ok' => isset($have[(int) $d['id']])], $required),
            'contract' => $contract,
            'tickets'  => $db->table('service_tickets')->select('ticket_no, category, status, created_at, resolved_at, resolution')->where('project_id', $p['id'])->orderBy('id', 'DESC')->limit(10)->get()->getResultArray(),
            'visits' => $contract ? $db->table('amc_visits')->select('visit_no, due_date, status, done_date')->where('amc_contract_id', $contract['id'])->orderBy('visit_no')->get()->getResultArray() : [],
        ]);
    }

    /** "Report a problem" from the status page (after installation). */
    public function complaint(string $token)
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw PageNotFoundException::forPageNotFound();
        }
        helper('app');
        $db = db_connect();
        $p  = $db->table('projects')->where('status_token', $token)->get()->getRowArray();
        if (! $p) {
            throw PageNotFoundException::forPageNotFound();
        }
        $back = redirect()->to('track/' . $token . '#service');
        $desc = trim((string) $this->request->getPost('description'));
        if (mb_strlen($desc) < 5 || mb_strlen($desc) > 1000) {
            return $back->with('complaint_error', 'Please describe the problem in a few words.');
        }
        $open = $db->table('service_tickets')->where('project_id', $p['id'])->whereIn('status', ['open', 'assigned', 'in_progress'])->countAllResults();
        if ($open >= 3) {
            return $back->with('complaint_error', 'You already have open complaints. Our team will contact you soon.');
        }
        $no = (string) $db->table('service_tickets')->select('ticket_no')->where('id', \App\Libraries\ServiceDesk::create((int) $p['customer_id'], (int) $p['id'],
            (string) $this->request->getPost('category'), 'normal', $desc, 'status_page', trim((string) $this->request->getPost('phone')) ?: null))->get()->getRow('ticket_no');

        return $back->with('complaint_ok', 'Thank you. Your complaint ' . $no . ' is registered. Our service team will contact you soon.');
    }
}
