<?php

namespace App\Controllers\Admin;

use App\Libraries\Messenger;
use App\Libraries\Numbering;
use App\Libraries\Pdf;
use App\Libraries\Quote;
use App\Libraries\Tax;
use App\Libraries\Workflow;
use RuntimeException;

/**
 * Quotations / proposals for a lead or customer: system lines, subsidy,
 * savings and payback; PDF, email and a WhatsApp share link; convert to invoice.
 */
class Quotations extends AdminController
{
    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'converted' => 'Converted to invoice'];

    public function index()
    {
        $this->requirePermission('quotations.view', 'quotations.manage');
        [$from, $to] = fy_range();
        $b = db_connect()->table('quotations q')->select('q.*')
            ->where('q.quote_date >=', $from)->where('q.quote_date <=', $to);
        if (($s = $this->request->getGet('status')) && isset(self::STATUSES[$s])) {
            $b->where('q.status', $s);
        }
        if ($term = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('q.quote_no', $term)->orLike('q.party_name', $term)->orLike('q.party_phone', $term)->groupEnd();
        }

        return $this->render('quotations/index', ['rows' => $b->orderBy('q.id', 'DESC')->get()->getResultArray(), 'from' => $from, 'to' => $to]);
    }

    public function create()
    {
        $this->requirePermission('quotations.manage');
        $db   = db_connect();
        $head = ['quote_date' => date('Y-m-d'), 'valid_until' => date('Y-m-d', strtotime('+' . (int) setting('Solar.quoteValidDays') . ' days')),
            'subsidy_type' => 'subsidy', 'customer_type' => 'b2c', 'party_state' => (string) setting('Solar.companyState'),
            'units_per_kw_day' => (float) setting('Solar.quoteUnitsPerKw'), 'tariff' => (float) setting('Solar.quoteTariff'),
            'notes' => (string) setting('Solar.quoteTerms'), 'print_format' => 'cumulative'];
        if ($leadId = (int) $this->request->getGet('lead')) {
            $l     = $this->findOr404('leads', $leadId);
            $head += ['lead_id' => $l['id'], 'customer_id' => $l['customer_id']];
            $head  = array_merge($head, ['party_name' => $l['name'], 'party_phone' => $l['phone'], 'party_email' => $l['email'], 'party_address' => $l['address'],
                'party_city' => $l['city'], 'customer_type' => $l['customer_type'] ?: 'b2c', 'capacity_kw' => $l['required_kw']]);
            if ($l['customer_id'] && ($c = db_connect()->table('customers')->where('id', $l['customer_id'])->get()->getRowArray())) {
                $head = array_merge($head, array_filter($this->partyFromCustomer($c), static fn ($x) => $x !== null && $x !== ''));
            }
        }
        if ($custId = (int) $this->request->getGet('customer')) {
            $c    = $this->findOr404('customers', $custId);
            $head = array_merge($head, ['customer_id' => $c['id']] + $this->partyFromCustomer($c));
        }
        if ($designId = (int) $this->request->getGet('design')) {
            $head = $this->fromDesign($head, $designId);
        }
        if (($head['customer_type'] ?? 'b2c') === 'b2b') {
            $head['print_format'] = 'itemwise';
        }

        return $this->render('quotations/form', $this->formData($head, []));
    }

    public function store()
    {
        $this->requirePermission('quotations.manage');
        try {
            [$head, $lines] = $this->collect();
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transException(true)->transStart();
        $db->table('quotations')->insert($head + [
            'quote_no'    => Numbering::next('quotations', 'quote_no', (string) setting('Solar.quotePrefix'), $head['quote_date']),
            'status'      => 'draft', 'share_token' => bin2hex(random_bytes(16)),
            'created_by'  => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        $this->saveLines($id, $lines);
        $db->transComplete();
        $this->saveDesign($id);
        $this->linkRoofDesign($id, (int) $this->request->getPost('roof_design_id'));
        Workflow::log('quotation', $id, 'created', 'Quotation created for ' . $head['party_name'] . ' (' . money($head['total']) . ')');

        return redirect()->to('admin/quotations/' . $id)->with('message', 'Quotation saved.');
    }

    public function show($id)
    {
        $this->requirePermission('quotations.view', 'quotations.manage');

        return $this->render('quotations/show', $this->data((int) $id));
    }

    public function edit($id)
    {
        $this->requirePermission('quotations.manage');
        $q = $this->findOr404('quotations', $id);
        if ($q['status'] === 'converted') {
            return $this->back('This quotation is already converted to an invoice.');
        }
        $lines = db_connect()->table('quotation_items')->where('quotation_id', $q['id'])->orderBy('id')->get()->getResultArray();

        return $this->render('quotations/form', $this->formData($q, $lines));
    }

    public function update($id)
    {
        $this->requirePermission('quotations.manage');
        $q = $this->findOr404('quotations', $id);
        if ($q['status'] === 'converted') {
            return $this->back('This quotation is already converted to an invoice.');
        }
        try {
            [$head, $lines] = $this->collect();
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        $db = db_connect();
        $db->transException(true)->transStart();
        $db->table('quotations')->where('id', $q['id'])->update($head + ['updated_at' => date('Y-m-d H:i:s')]);
        $db->table('quotation_items')->where('quotation_id', $q['id'])->delete();
        $this->saveLines((int) $q['id'], $lines);
        $db->transComplete();
        $this->saveDesign((int) $q['id']);
        Workflow::log('quotation', (int) $q['id'], 'edited', 'Quotation edited (' . money($head['total']) . ')');

        return redirect()->to('admin/quotations/' . $q['id'])->with('message', 'Quotation updated.');
    }

    public function delete($id)
    {
        $this->requirePermission('quotations.delete');
        $q = $this->findOr404('quotations', $id);
        if ($q['status'] === 'converted') {
            return $this->back('This quotation is already an invoice. Cancel or delete the invoice instead.');
        }
        $db = db_connect();
        $db->table('quotation_items')->where('quotation_id', $q['id'])->delete();
        $db->table('quotations')->where('id', $q['id'])->delete();
        if ($q['design_path']) {
            @unlink(upload_path() . $q['design_path']);
        }
        Workflow::log('quotation', (int) $q['id'], 'deleted', 'Quotation ' . $q['quote_no'] . ' deleted');

        return redirect()->to('admin/quotations')->with('message', 'Quotation ' . $q['quote_no'] . ' deleted.');
    }

    public function status($id)
    {
        $this->requirePermission('quotations.manage');
        $q = $this->findOr404('quotations', $id);
        $s = (string) $this->request->getPost('status');
        if ($q['status'] === 'converted' || ! in_array($s, ['draft', 'sent', 'accepted', 'rejected'], true)) {
            return $this->back('This status cannot be set.');
        }
        db_connect()->table('quotations')->where('id', $q['id'])->update(['status' => $s, 'updated_at' => date('Y-m-d H:i:s')] + ($s === 'sent' && ! $q['sent_at'] ? ['sent_at' => date('Y-m-d H:i:s')] : []));
        Workflow::log('quotation', (int) $q['id'], 'status', 'Status: ' . self::STATUSES[$s]);

        return redirect()->back()->with('message', 'Quotation marked as ' . self::STATUSES[$s] . '.');
    }

    public function pdf($id)
    {
        $this->requirePermission('quotations.view', 'quotations.manage');
        $d = $this->data((int) $id);
        Pdf::stream('pdf/quotation', $d, str_replace('/', '-', $d['q']['quote_no']), (bool) $this->request->getGet('download'));
    }

    public function design($id)
    {
        $this->requirePermission('quotations.view', 'quotations.manage');
        $q    = $this->findOr404('quotations', $id);
        $path = upload_path() . $q['design_path'];
        if (! $q['design_path'] || ! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setContentType(mime_content_type($path) ?: 'application/octet-stream')->setBody(file_get_contents($path));
    }

    public function email($id)
    {
        $this->requirePermission('quotations.manage');
        $d  = $this->data((int) $id);
        $q  = $d['q'];
        $to = trim((string) $this->request->getPost('to')) ?: (string) $q['party_email'];
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->back('Enter a valid email address.');
        }
        $pdf = Pdf::toFile('pdf/quotation', $d, $q['quote_no']);
        $res = Messenger::customer('quotation', ['name' => $q['party_name'], 'email' => $to, 'phone' => $q['party_phone']], [
            'quote_no' => $q['quote_no'], 'capacity_kw' => qty($q['capacity_kw']), 'total' => money($q['total']), 'link' => self::shareUrl($q),
        ], 'Quotation ' . $q['quote_no'] . ' – ' . setting('Solar.companyName'), sprintf(
            "Dear %s,\n\nThank you for your interest. Please find attached our quotation %s for a %s kW solar power system.\nTotal: %s%s\nValid until: %s\n\nYou can also view it here: %s",
            $q['party_name'], $q['quote_no'], qty($q['capacity_kw']), money($q['total']),
            (float) $q['subsidy_amount'] > 0 ? "\nSubsidy (expected): " . money($q['subsidy_amount']) . "\nNet cost after subsidy: " . money($d['roi']['net_cost']) : '',
            fdate($q['valid_until']), self::shareUrl($q)
        ), true, [['path' => $pdf, 'name' => str_replace('/', '-', $q['quote_no']) . '.pdf']]);
        if (! str_contains($res, 'FAILED')) {
            $this->markSent($q);
        }
        Workflow::log('quotation', (int) $q['id'], 'emailed', $res);

        return redirect()->back()->with(str_contains($res, 'FAILED') ? 'error' : 'message', $res);
    }

    /** Opens WhatsApp with the share link (and marks the quotation as sent). */
    public function whatsapp($id)
    {
        $this->requirePermission('quotations.manage');
        $q     = $this->findOr404('quotations', $id);
        $phone = preg_replace('/\D/', '', (string) $q['party_phone']);
        $phone = strlen($phone) === 10 ? '91' . $phone : $phone;
        $text  = sprintf("Dear %s, here is our quotation %s for your %s kW solar system (total %s). View / download: %s\n– %s",
            $q['party_name'], $q['quote_no'], qty($q['capacity_kw']), money($q['total']), self::shareUrl($q), setting('Solar.companyName'));
        $this->markSent($q);
        Workflow::log('quotation', (int) $q['id'], 'whatsapp', 'Shared on WhatsApp');

        return redirect()->to('https://wa.me/' . $phone . '?text=' . rawurlencode($text));
    }

    /** Accepted quotation → customer (from the lead if needed) → invoice form filled from the quotation. */
    public function convert($id)
    {
        $this->requirePermission('quotations.manage');
        $this->requirePermission('invoices.manage');
        $q = $this->findOr404('quotations', $id);
        if ($q['status'] === 'converted' && $q['invoice_id']) {
            return redirect()->to('admin/invoices/' . $q['invoice_id']);
        }
        $db = db_connect();
        if (! $q['customer_id']) {
            $now = date('Y-m-d H:i:s');
            $db->table('customers')->insert([
                'type' => $q['customer_type'], 'name' => $q['party_name'], 'phone' => $q['party_phone'], 'email' => $q['party_email'] ?: null,
                'address' => $q['party_address'], 'city' => $q['party_city'], 'state' => $q['party_state'] ?: setting('Solar.companyState'),
                'lead_id' => $q['lead_id'], 'eb_charges_by' => $q['eb_charges_by'], 'eb_charges_amount' => $q['eb_charges_amount'],
                'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $q['customer_id'] = (int) $db->insertID();
            $db->table('quotations')->where('id', $q['id'])->update(['customer_id' => $q['customer_id']]);
            if ($q['lead_id']) {
                $db->table('leads')->where('id', $q['lead_id'])->update(['status' => 'confirmed', 'customer_id' => $q['customer_id'], 'updated_at' => $now]);
            }
        }
        if ($q['status'] !== 'accepted') {
            $db->table('quotations')->where('id', $q['id'])->update(['status' => 'accepted']);
        }

        return redirect()->to('admin/invoices/new?quotation=' . $q['id'])->with('message', 'Check the invoice filled from quotation ' . $q['quote_no'] . ' and save it.');
    }

    public static function shareUrl(array $q): string
    {
        return site_url('quote/' . $q['share_token']);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function markSent(array $q): void
    {
        if (in_array($q['status'], ['draft', 'sent'], true)) {
            db_connect()->table('quotations')->where('id', $q['id'])->update(['status' => 'sent'] + ($q['sent_at'] ? [] : ['sent_at' => date('Y-m-d H:i:s')]));
        }
    }

    /** Quotation pre-filled from a 3D roof design: kW, matching kit, real generation. */
    private function fromDesign(array $head, int $designId): array
    {
        $d = db_connect()->table('roof_designs')->where(['id' => $designId, 'deleted_at' => null])->get()->getRowArray();
        if (! $d || $d['capacity_kw'] <= 0) {
            return $head;
        }
        $kw  = (float) $d['capacity_kw'];
        $kit = db_connect()->table('kits')->where('is_active', 1)->where('capacity_kw >=', $kw - 0.01)->orderBy('capacity_kw')->limit(1)->get()->getRowArray();
        $head = array_merge($head, [
            'roof_design_id' => $d['id'], 'design_info' => $d, 'capacity_kw' => $kw,
            'units_per_kw_day' => $d['kwh_year'] > 0 ? round((float) $d['kwh_year'] / ($kw * 365), 2) : ($head['units_per_kw_day'] ?? 4),
        ]);
        if ($kit) {
            $head['kit_id'] = $kit['id'];
        }
        if (empty($head['lead_id']) && $d['lead_id']) {
            $head['lead_id'] = $d['lead_id'];
        }

        return $head;
    }

    /** After saving: link the design, and use its 3D picture as the quotation's design image (unless a file was uploaded). */
    private function linkRoofDesign(int $quotationId, int $designId): void
    {
        if ($designId < 1) {
            return;
        }
        $db = db_connect();
        $d  = $db->table('roof_designs')->where(['id' => $designId, 'deleted_at' => null])->get()->getRowArray();
        if (! $d) {
            return;
        }
        $db->table('quotations')->where('id', $quotationId)->update(['roof_design_id' => $d['id']]);
        $db->table('roof_designs')->where('id', $d['id'])->update(['quotation_id' => $quotationId]);
        $q = $db->table('quotations')->select('design_path')->where('id', $quotationId)->get()->getRowArray();
        $src = upload_path() . $d['snapshot_path'];
        if (empty($q['design_path']) && $d['snapshot_path'] && is_file($src)) {
            is_dir(upload_path() . 'quotations') || mkdir(upload_path() . 'quotations', 0775, true);
            $rel = 'quotations/design-' . $quotationId . '-' . time() . '.png';
            if (copy($src, upload_path() . $rel)) {
                $db->table('quotations')->where('id', $quotationId)->update(['design_path' => $rel]);
            }
        }
    }

    private function partyFromCustomer(array $c): array
    {
        return ['party_name' => $c['name'], 'party_phone' => $c['phone'], 'party_email' => $c['email'], 'party_address' => $c['address'],
            'party_city' => $c['city'], 'party_state' => $c['state'], 'customer_type' => $c['type'],
            'eb_charges_by' => $c['eb_charges_by'] ?? null, 'eb_charges_amount' => $c['eb_charges_amount'] ?? null];
    }

    private function formData(array $head, array $rows): array
    {
        $db = db_connect();

        return [
            'q'         => $head, 'rows' => $rows,
            'customers' => $db->table('customers')->select('id, name, type, phone, email, address, city, state, eb_charges_by, eb_charges_amount')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
            'leads'     => $db->table('leads')->select('id, name, phone, email, address, city, customer_type, required_kw, customer_id')->where('deleted_at', null)->whereNotIn('status', ['lost'])->orderBy('id', 'DESC')->limit(500)->get()->getResultArray(),
            'items'     => $db->table('items')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray(),
            'kits'      => $db->table('kits')->where('is_active', 1)->orderBy('capacity_kw')->get()->getResultArray(),
            'types'     => options('installation_types'),
        ];
    }

    /** Validated header + lines from the form. */
    private function collect(): array
    {
        if (! $this->validate([
            'party_name'   => 'required|max_length[150]',
            'party_phone'  => 'permit_empty|max_length[20]',
            'party_email'  => 'permit_empty|valid_email',
            'quote_date'   => 'required|valid_date[Y-m-d]',
            'valid_until'  => 'permit_empty|valid_date[Y-m-d]',
            'subsidy_type' => 'required|in_list[subsidy,non_subsidy]',
            'customer_type' => 'required|in_list[b2b,b2c]',
            'capacity_kw'  => 'required|decimal|greater_than[0]',
        ])) {
            throw new RuntimeException(implode(' ', $this->validator->getErrors()));
        }
        $p = $this->request->getPost();
        [$lines, $sub, $tax, $total] = Tax::lines($this->postedRows());
        if (! $lines) {
            throw new RuntimeException('Add at least one line.');
        }
        $customerId = (int) ($p['customer_id'] ?? 0) ?: null;
        $leadId     = (int) ($p['lead_id'] ?? 0) ?: null;
        $num        = static fn ($k, $d = 0) => round((float) str_replace(',', '', (string) ($p[$k] ?? $d)), 2);

        return [[
            'lead_id' => $leadId, 'customer_id' => $customerId,
            'party_name' => trim($p['party_name']), 'party_phone' => trim((string) ($p['party_phone'] ?? '')) ?: null,
            'party_email' => trim((string) ($p['party_email'] ?? '')) ?: null, 'party_address' => trim((string) ($p['party_address'] ?? '')) ?: null,
            'party_city' => trim((string) ($p['party_city'] ?? '')) ?: null, 'party_state' => trim((string) ($p['party_state'] ?? '')) ?: (string) setting('Solar.companyState'),
            'customer_type' => $p['customer_type'], 'quote_date' => $p['quote_date'], 'valid_until' => ($p['valid_until'] ?? '') ?: null,
            'subsidy_type' => $p['subsidy_type'], 'installation_type_id' => ($p['installation_type_id'] ?? '') ?: null,
            'capacity_kw' => (float) $p['capacity_kw'], 'kit_id' => ((int) ($p['kit_id'] ?? 0)) ?: null,
            'gst_type' => Tax::gstType(trim((string) ($p['party_state'] ?? '')) ?: (string) setting('Solar.companyState')),
            'print_format' => in_array($p['print_format'] ?? '', ['itemwise', 'cumulative'], true) ? $p['print_format'] : 'cumulative',
            'subtotal' => $sub, 'tax_total' => $tax, 'total' => $total,
            'subsidy_amount' => $p['subsidy_type'] === 'subsidy' ? $num('subsidy_amount') : 0,
            'units_per_kw_day' => $num('units_per_kw_day', 4), 'tariff' => $num('tariff'),
            'notes' => trim((string) ($p['notes'] ?? '')) ?: null,
        ] + eb_charges_input($p), $lines];
    }

    private function saveLines(int $id, array $lines): void
    {
        foreach ($lines as $l) {
            db_connect()->table('quotation_items')->insert([
                'quotation_id' => $id, 'item_id' => ($l['item_id'] ?? '') ?: null,
                'description' => trim($l['description'] ?? '') ?: 'Item', 'hsn' => $l['hsn'] ?? null,
                'qty' => $l['qty'], 'rate' => $l['rate'], 'tax_percent' => $l['tax_percent'],
                'tax_amount' => $l['tax_amount'], 'amount' => $l['amount'],
            ]);
        }
    }

    private function saveDesign(int $id): void
    {
        $db = db_connect();
        $q  = $db->table('quotations')->select('design_path')->where('id', $id)->get()->getRowArray();
        if ($this->request->getPost('remove_design') && $q['design_path']) {
            @unlink(upload_path() . $q['design_path']);
            $db->table('quotations')->where('id', $id)->update(['design_path' => null]);
            $q['design_path'] = null;
        }
        try {
            $path = $this->storeUpload('design', 'quotations');
        } catch (RuntimeException $e) {
            session()->setFlashdata('warning', 'Quotation saved, but the design file was not: ' . $e->getMessage());

            return;
        }
        if ($path) {
            if ($q['design_path']) {
                @unlink(upload_path() . $q['design_path']);
            }
            $db->table('quotations')->where('id', $id)->update(['design_path' => $path]);
        }
    }

    /** Everything the page and the PDF need. Also used by the public share link. */
    public static function dataFor(array $q): array
    {
        $db = db_connect();

        return [
            'q'     => $q,
            'lines' => $db->table('quotation_items')->where('quotation_id', $q['id'])->orderBy('id')->get()->getResultArray(),
            'roi'   => Quote::roi($q),
            'type'  => $q['installation_type_id'] ? (string) $db->table('installation_types')->select('name')->where('id', $q['installation_type_id'])->get()->getRow('name') : '',
            'kit'   => $q['kit_id'] ? $db->table('kits')->where('id', $q['kit_id'])->get()->getRowArray() : null,
        ];
    }

    private function data(int $id): array
    {
        $q = $this->findOr404('quotations', $id);

        return self::dataFor($q) + [
            'lead'     => $q['lead_id'] ? db_connect()->table('leads')->select('id, name')->where('id', $q['lead_id'])->get()->getRowArray() : null,
            'customer' => $q['customer_id'] ? db_connect()->table('customers')->select('id, name')->where('id', $q['customer_id'])->get()->getRowArray() : null,
            'invoice'  => $q['invoice_id'] ? db_connect()->table('invoices')->select('id, invoice_no, status')->where('id', $q['invoice_id'])->get()->getRowArray() : null,
        ];
    }
}
