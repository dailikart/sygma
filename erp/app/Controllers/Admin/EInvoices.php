<?php

namespace App\Controllers\Admin;

use App\Libraries\EInvoice;

/**
 * Sales → GST e-Invoice: ready-to-upload JSON for einvoice1.gst.gov.in
 * (e-Invoice → Bulk Upload), and the IRN / Ack no. / QR the portal gives back.
 */
class EInvoices extends AdminController
{
    public function index()
    {
        $this->requirePermission('invoices.view');
        if (! db_connect()->fieldExists('irn', 'invoices')) {
            return redirect()->to('admin/maintenance')->with('error', 'GST e-invoice needs a database update: click Run database updates.');
        }
        [$from, $to] = fy_range();
        $show = (string) $this->request->getGet('show');
        $b    = db_connect()->table('invoices i')->select('i.id, i.invoice_no, i.invoice_date, i.total, i.status, i.irn, i.irn_ack_no, i.irn_ack_date, c.name, c.company, c.gstin')
            ->join('customers c', 'c.id = i.customer_id')
            ->where("TRIM(IFNULL(c.gstin, '')) <>", '')->where('i.status <>', 'cancelled')
            ->where('i.invoice_date >=', $from)->where('i.invoice_date <=', $to);
        $rows = $b->orderBy('i.invoice_date')->orderBy('i.id')->get()->getResultArray();
        $sellerErrors = EInvoice::seller()[1];
        foreach ($rows as &$r) { // company problems are shown once at the top
            $r['errors'] = $r['irn'] ? [] : EInvoice::build((int) $r['id'])[1];
            $own         = array_values(array_diff($r['errors'], $sellerErrors));
            $r['errors'] = $own ?: ($r['errors'] ? ['Complete the company details above.'] : []);
        }
        unset($r);
        $counts = ['pending' => 0, 'ready' => 0, 'problem' => 0, 'done' => 0];
        foreach ($rows as $r) {
            $counts[$r['irn'] ? 'done' : ($r['errors'] ? 'problem' : 'ready')]++;
            $counts['pending'] += $r['irn'] ? 0 : 1;
        }
        if ($show !== 'all') {
            $rows = array_values(array_filter($rows, static fn ($r) => ! $r['irn']));
        }

        return $this->render('einvoice/index', ['rows' => $rows, 'from' => $from, 'to' => $to, 'show' => $show, 'counts' => $counts, 'sellerErrors' => $sellerErrors]);
    }

    /** Download the JSON for the ticked invoices (or one invoice: /admin/einvoice/5/json). */
    public function download($id = null)
    {
        $this->requirePermission('invoices.view');
        $ids = $id ? [(int) $id] : array_map('intval', (array) ($this->request->getPost('ids') ?? []));
        if (! $ids) {
            return $this->back('Tick the invoices to download.');
        }
        $out     = [];
        $skipped = [];
        foreach ($ids as $i) {
            [$json, $errors] = EInvoice::build($i);
            if ($json) {
                $out[] = $json;
            } else {
                $skipped[] = (db_connect()->table('invoices')->select('invoice_no')->where('id', $i)->get()->getRow()->invoice_no ?? '#' . $i) . ': ' . implode(' ', $errors);
            }
        }
        if (! $out) {
            return $this->back('Not downloaded. Fix these first: ' . implode(' | ', $skipped));
        }
        $name = $id ? 'einvoice-' . str_replace('/', '-', $out[0]['DocDtls']['No']) : 'einvoice-' . count($out) . '-invoices-' . date('Ymd-His');
        \App\Libraries\Workflow::log('einvoice', 0, 'exported', 'E-invoice JSON downloaded: ' . implode(', ', array_map(static fn ($j) => $j['DocDtls']['No'], $out)));

        return $this->response->setHeader('Content-Type', 'application/json')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '.json"')
            ->setBody(EInvoice::encode($out));
    }

    /** Save the IRN, Ack no. / date and signed QR code from the portal on the invoice. */
    public function saveIrn($id)
    {
        $this->requirePermission('invoices.manage');
        $inv = $this->findOr404('invoices', $id);
        if (! array_key_exists('irn', $inv)) {
            return redirect()->to('admin/maintenance')->with('error', 'GST e-invoice needs a database update: click Run database updates.');
        }
        $p   = $this->request->getPost();
        if (! empty($p['clear'])) {
            db_connect()->table('invoices')->where('id', $inv['id'])->update(['irn' => null, 'irn_ack_no' => null, 'irn_ack_date' => null, 'irn_qr' => null]);
            \App\Libraries\Workflow::log('invoice', (int) $inv['id'], 'irn_removed', 'E-invoice IRN removed');

            return redirect()->back()->with('message', 'IRN removed from the invoice.');
        }
        $irn = strtolower(preg_replace('/\s+/', '', (string) ($p['irn'] ?? '')));
        if (! preg_match('/^[0-9a-f]{64}$/', $irn)) {
            return $this->back('The IRN is the 64-character code shown by the portal (letters a–f and digits). Copy it exactly.');
        }
        $ack = preg_replace('/\D/', '', (string) ($p['irn_ack_no'] ?? ''));
        $dt  = trim((string) ($p['irn_ack_date'] ?? ''));
        $qr  = trim((string) ($p['irn_qr'] ?? ''));
        db_connect()->table('invoices')->where('id', $inv['id'])->update([
            'irn' => $irn, 'irn_ack_no' => $ack ?: null, 'irn_ack_date' => $dt ? date('Y-m-d H:i:s', strtotime($dt)) : null, 'irn_qr' => $qr ?: null,
        ]);
        \App\Libraries\Workflow::log('invoice', (int) $inv['id'], 'irn_saved', 'E-invoice IRN saved' . ($ack ? ' (Ack ' . $ack . ')' : ''));

        return redirect()->back()->with('message', 'E-invoice details saved. They now print on the invoice' . ($qr ? ' with the QR code.' : '.'));
    }
}
