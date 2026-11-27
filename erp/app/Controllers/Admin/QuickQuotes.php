<?php

namespace App\Controllers\Admin;

use App\Libraries\Numbering;
use App\Libraries\QuickQuote;
use App\Libraries\Tax;
use App\Libraries\Workflow;

/**
 * Quotations → Quick quote: pick the options, one click makes the full
 * proposal (cover letter, bill of materials, price with subsidy, payback chart,
 * terms). Saved as a normal quotation, so email, WhatsApp, share link and
 * convert-to-invoice all work as before.
 */
class QuickQuotes extends AdminController
{
    public function create()
    {
        $this->requirePermission('quotations.manage');
        if ($missing = $this->missingColumn()) {
            return $missing;
        }
        $cfg  = QuickQuote::config();
        $head = ['party_state' => (string) setting('Solar.companyState')];
        $spec = ['input' => ['mode' => 'bill', 'system' => $cfg['default_system'], 'tech' => $cfg['default_tech'], 'watt' => $cfg['default_watt'][$cfg['default_tech']],
            'structure' => 'low', 'dc_cable' => QuickQuote::DC_CABLES[0], 'ac_cable' => QuickQuote::AC_CABLES[2], 'phase' => 1, 'inverter_kw' => 'auto', 'subsidy' => true, 'battery_kwh' => 5]];
        if ($leadId = (int) $this->request->getGet('lead')) {
            $l    = $this->findOr404('leads', $leadId);
            $head = array_merge($head, ['lead_id' => $l['id'], 'customer_id' => $l['customer_id'], 'party_name' => $l['name'], 'party_phone' => $l['phone'],
                'party_email' => $l['email'], 'party_address' => $l['address'], 'party_city' => $l['city']]);
            if ((float) $l['required_kw'] > 0) {
                $spec['input']['mode'] = 'kw';
                $spec['input']['kw']   = (float) $l['required_kw'];
            }
        }
        if ($custId = (int) $this->request->getGet('customer')) {
            $c    = $this->findOr404('customers', $custId);
            $head = array_merge($head, ['customer_id' => $c['id'], 'party_name' => $c['name'], 'party_phone' => $c['phone'], 'party_email' => $c['email'],
                'party_address' => $c['address'], 'party_city' => $c['city'], 'party_state' => $c['state']]);
        }

        return $this->render('quotations/quick', $this->formData($head, $spec, $cfg));
    }

    /** Re-open a quick quotation with its saved choices. */
    public function edit($id)
    {
        $this->requirePermission('quotations.manage');
        if ($missing = $this->missingColumn()) {
            return $missing;
        }
        $q = $this->findOr404('quotations', $id);
        if ($q['status'] === 'converted') {
            return $this->back('This quotation is already converted to an invoice.');
        }
        if ($q['superseded_by']) {
            return redirect()->to('admin/quotations/' . $this->latestId((int) $q['id']) . '/quick')->with('warning', $q['quote_no'] . ' has a newer version: revising the latest one.');
        }
        $spec = json_decode((string) ($q['quick_spec'] ?? ''), true);
        if (! is_array($spec)) {
            return redirect()->to('admin/quotations/' . $q['id'] . '/edit');
        }

        return $this->render('quotations/quick', $this->formData($q, $spec, QuickQuote::config()));
    }

    /** Live price / size summary while the form is filled (JSON). */
    public function preview()
    {
        $this->requirePermission('quotations.manage');
        $cfg = $this->effectiveConfig();
        $r   = QuickQuote::build($this->input(), $cfg, (string) setting('Solar.companyName'), $this->stockItems());
        if (! $r['ok']) {
            return $this->response->setJSON(['ok' => false, 'error' => $r['error']]);
        }
        $net = $r['price']['total'] - $r['subsidy']['total'];

        return $this->response->setJSON([
            'ok' => true, 'warnings' => $r['warnings'], 'title' => $r['title'], 'size' => $r['size'], 'price' => $r['price'],
            'subsidy' => $r['subsidy'], 'savings' => $r['savings'], 'gst' => $r['gst'], 'net' => $net,
            'payback' => QuickQuote::projection($r['savings']['year'], $net, $cfg['escalation'], $cfg['degradation'], $cfg['years'])['payback'],
            'cost' => can('settings.manage') ? $r['cost'] : null,
            'items' => array_map(static fn ($b) => ['part' => $b['item'], 'qty' => $b['qty'], 'uom' => $b['uom'], 'item' => $b['item_name'],
                'auto' => $b['item_auto'] ?? false, 'stock' => $b['stock'] ?? null], $r['bom']),
        ]);
    }

    public function store()
    {
        return $this->save(null);
    }

    /** Revise: saves the changed quotation as a new version (…-R1, -R2); the old version is kept. */
    public function update($id)
    {
        return $this->save((int) $id);
    }

    private function save(?int $id)
    {
        $this->requirePermission('quotations.manage');
        if ($missing = $this->missingColumn()) {
            return $missing;
        }
        $old = $id ? $this->findOr404('quotations', $id) : null;
        if ($old && $old['status'] === 'converted') {
            return $this->back('This quotation is already converted to an invoice.');
        }
        if ($old && $old['superseded_by']) {
            return redirect()->to('admin/quotations/' . $this->latestId((int) $old['id']))->with('error', 'A newer version of this quotation exists: revise that one.');
        }
        if (! $this->validate([
            'party_name'  => 'required|max_length[150]',
            'party_phone' => 'permit_empty|max_length[20]',
            'party_email' => 'permit_empty|valid_email',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }
        $cfg = $this->effectiveConfig();
        $r   = QuickQuote::build($this->input(), $cfg, (string) setting('Solar.companyName'), $this->stockItems());
        if (! $r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }
        $p     = $this->request->getPost();
        $s     = static fn ($k) => trim((string) ($p[$k] ?? '')) ?: null;
        $state = $s('party_state') ?? (string) setting('Solar.companyState');
        [$lines, $sub, $tax, $total] = Tax::lines($r['lines']);
        $r['party']       = ['pincode' => $s('pincode'), 'eb_number' => $s('eb_number')];
        $r['prepared_by'] = auth()->user()->username ?? '';
        unset($r['ok'], $r['lines']);
        $date = date('Y-m-d');
        $now  = date('Y-m-d H:i:s');
        $head = [
            'lead_id' => ((int) ($p['lead_id'] ?? 0)) ?: null, 'customer_id' => ((int) ($p['customer_id'] ?? 0)) ?: null,
            'party_name' => trim($p['party_name']), 'party_phone' => $s('party_phone'), 'party_email' => $s('party_email'),
            'party_address' => $s('party_address'), 'party_city' => $s('party_city'), 'party_state' => $state,
            'customer_type' => 'b2c', 'quote_date' => $date,
            'valid_until' => date('Y-m-d', strtotime($date . ' +' . max(1, (int) setting('Solar.quoteValidDays')) . ' days')),
            'subsidy_type' => $r['subsidy']['total'] > 0 ? 'subsidy' : 'non_subsidy', 'capacity_kw' => $r['size']['kw'],
            'gst_type' => Tax::gstType($state), 'print_format' => $cfg['line_gst'] === 'items' ? 'cumulative' : 'itemwise',
            'subtotal' => $sub, 'tax_total' => $tax, 'total' => $total, 'subsidy_amount' => $r['subsidy']['total'],
            'units_per_kw_day' => $r['savings']['units_per_kw_day'], 'tariff' => $r['savings']['tariff_avg'],
            'eb_charges_by' => null, 'eb_charges_amount' => null, // the EB meter cost is printed as a note, not added to the net cost
            'notes' => $s('notes'), 'quick_spec' => json_encode($r, JSON_UNESCAPED_UNICODE),
            'status' => 'draft', 'share_token' => bin2hex(random_bytes(16)), 'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
        ];
        $db = db_connect();
        $db->transException(true)->transStart();
        if ($old) {
            $rootId = (int) ($old['root_id'] ?: $old['id']);
            $root   = $db->table('quotations')->select('quote_no')->where('id', $rootId)->get()->getRowArray();
            $rev    = 1 + (int) $db->table('quotations')->selectMax('revision', 'r')->groupStart()->where('id', $rootId)->orWhere('root_id', $rootId)->groupEnd()->get()->getRow('r');
            $head  += [
                'quote_no' => ($root['quote_no'] ?? $old['quote_no']) . '-R' . $rev, 'root_id' => $rootId, 'revision' => $rev,
                'roof_design_id' => $old['roof_design_id'], 'installation_type_id' => $old['installation_type_id'], 'agreement' => $this->carryAgreement($old['agreement'] ?? null),
            ];
        } else {
            $head['quote_no'] = Numbering::next('quotations', 'quote_no', (string) setting('Solar.quotePrefix'), $date);
        }
        $db->table('quotations')->insert($head);
        $id = (int) $db->insertID();
        foreach ($lines as $l) {
            $db->table('quotation_items')->insert(['quotation_id' => $id, 'item_id' => $l['item_id'] ?? null, 'description' => $l['description'], 'hsn' => $l['hsn'],
                'qty' => $l['qty'], 'rate' => $l['rate'], 'tax_percent' => $l['tax_percent'], 'tax_amount' => $l['tax_amount'], 'amount' => $l['amount']]);
        }
        if ($old) {
            $db->table('quotations')->where('id', $old['id'])->update(['superseded_by' => $id, 'updated_at' => $now]);
        }
        $db->transComplete();
        if ($old && $old['design_path'] && is_file(upload_path() . $old['design_path'])) {
            $rel = 'quotations/design-' . $id . '-' . time() . '.' . pathinfo($old['design_path'], PATHINFO_EXTENSION);
            if (copy(upload_path() . $old['design_path'], upload_path() . $rel)) {
                $db->table('quotations')->where('id', $id)->update(['design_path' => $rel]);
            }
        }
        Workflow::log('quotation', $id, $old ? 'revised' : 'created', ($old ? 'Revised from ' . $old['quote_no'] . ': ' : 'Quick quote ') . $r['title'] . ' for ' . $head['party_name'] . ' (' . money($total) . ')');
        if ($old) {
            Workflow::log('quotation', (int) $old['id'], 'superseded', 'Replaced by ' . $head['quote_no']);
        }
        $linked = count(array_filter($lines, static fn ($l) => ! empty($l['item_id'])));

        return redirect()->to('admin/quotations/' . $id)
            ->with('message', ($old ? 'New version ' . $head['quote_no'] . ' saved: ' : 'Quotation ready: ') . $r['title'] . ', ' . money($total) . '. ' . $linked . ' stock item(s) added from Inventory.')
            ->with('warning', $r['warnings'] ? implode(' ', $r['warnings']) : null);
    }

    /** Agreement details carried to a new version; amounts are worked out again from the new total. */
    private function carryAgreement(?string $json): ?string
    {
        $a = json_decode((string) $json, true);
        if (! is_array($a)) {
            return null;
        }
        unset($a['date']);
        $a['stages'] = array_map(static function ($s) {
            unset($s['amount']);

            return $s;
        }, $a['stages'] ?? []);

        return json_encode($a, JSON_UNESCAPED_UNICODE);
    }

    /** Newest version in the chain of a quotation. */
    private function latestId(int $id): int
    {
        $db = db_connect();
        for ($i = 0; $i < 100; $i++) {
            $next = $db->table('quotations')->select('superseded_by')->where('id', $id)->get()->getRow('superseded_by');
            if (! $next) {
                break;
            }
            $id = (int) $next;
        }

        return $id;
    }

    /** Active inventory items with the quantity in stock (all locations). */
    private function stockItems(): array
    {
        $stock = array_map('array_sum', \App\Libraries\Stock::balances());

        return array_map(static fn ($i) => $i + ['stock' => $stock[(int) $i['id']] ?? 0.0],
            db_connect()->table('items')->select('id, name, unit, hsn, tax_percent')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('id')->get()->getResultArray());
    }

    // ── Rate card (admins) ──────────────────────────────────────────────────

    public function settings()
    {
        $this->requirePermission('settings.manage');

        return $this->render('quotations/quick_settings', ['cfg' => QuickQuote::config(), 'saved' => (string) setting('Solar.quickQuote') !== '',
            'items' => db_connect()->table('items')->select('id, name')->where(['is_active' => 1, 'deleted_at' => null])->orderBy('name')->get()->getResultArray()]);
    }

    public function saveSettings()
    {
        $this->requirePermission('settings.manage');
        if ($this->request->getPost('reset')) {
            setting('Solar.quickQuote', '');
            Workflow::log('settings', 0, 'quick_quote', 'Quick quote rate card reset to defaults');

            return redirect()->to('admin/quotations/quick-settings')->with('message', 'Rate card reset to the defaults.');
        }
        $cfg = QuickQuote::sanitize((array) $this->request->getPost('cfg'));
        $sum = array_sum(array_column($cfg['stages'], 'pct'));
        setting('Solar.quickQuote', json_encode($cfg, JSON_UNESCAPED_UNICODE));
        Workflow::log('settings', 0, 'quick_quote', 'Quick quote rate card saved by ' . (auth()->user()->username ?? 'user'));

        return redirect()->to('admin/quotations/quick-settings')->with('message', 'Rate card saved for the whole team.')
            ->with('warning', $cfg['stages'] && abs($sum - 100) > 0.01 ? 'Payment stages add up to ' . QuickQuote::fmt($sum) . '%, not 100%.' : null);
    }

    // ── Team progress ───────────────────────────────────────────────────────

    public function team()
    {
        $this->requirePermission('quotations.view', 'quotations.manage');
        [$from, $to] = fy_range();
        $b = db_connect()->table('quotations q')
            ->select('q.created_by, u.username, q.status, COUNT(*) AS n, SUM(q.total) AS value')
            ->join('users u', 'u.id = q.created_by', 'left')
            ->where('q.quote_date >=', $from)->where('q.quote_date <=', $to)
            ->groupBy('q.created_by, u.username, q.status');
        if (Quotations::hasVersions()) {
            $b->where('q.superseded_by', null); // count each quotation once, at its latest version
        }
        $rows = $b->get()->getResultArray();
        $people = [];
        $team   = ['n' => 0, 'by' => [], 'won' => 0, 'won_value' => 0.0, 'sent' => 0];
        foreach ($rows as $r) {
            $k = (int) $r['created_by'];
            $people[$k] ??= ['name' => $r['username'] ?: 'Unknown', 'n' => 0, 'by' => [], 'won' => 0, 'won_value' => 0.0, 'sent' => 0];
            foreach ([&$people[$k], &$team] as &$t) {
                $t['n'] += (int) $r['n'];
                $t['by'][$r['status']] = ($t['by'][$r['status']] ?? 0) + (int) $r['n'];
                if ($r['status'] !== 'draft') {
                    $t['sent'] += (int) $r['n'];
                }
                if (in_array($r['status'], ['accepted', 'converted'], true)) {
                    $t['won'] += (int) $r['n'];
                    $t['won_value'] += (float) $r['value'];
                }
            }
            unset($t);
        }
        uasort($people, static fn ($a, $b) => $b['n'] <=> $a['n']);

        return $this->render('quotations/team', ['people' => $people, 'team' => $team, 'from' => $from, 'to' => $to]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** Rate card, with the price adjustment blocked for non-admins when the admin asked for that. */
    private function effectiveConfig(): array
    {
        return QuickQuote::config();
    }

    private function input(): array
    {
        $in = (array) $this->request->getPost('qq');
        $in['subsidy'] = ! empty($in['subsidy']);
        if (QuickQuote::config()['adjust_admin_only'] && ! can('settings.manage')) {
            unset($in['adjust']);
        }

        return $in;
    }

    private function formData(array $head, array $spec, array $cfg): array
    {
        $db = db_connect();

        return [
            'q' => $head, 'spec' => $spec, 'cfg' => $cfg,
            'canAdjust' => ! $cfg['adjust_admin_only'] || can('settings.manage'),
            'customers' => empty($head['id']) ? $db->table('customers')->select('id, name, phone, email, address, city, state')->where('deleted_at', null)->orderBy('name')->get()->getResultArray() : [],
            'leads'     => empty($head['id']) ? $db->table('leads')->select('id, name, phone, email, address, city, required_kw, customer_id')->where('deleted_at', null)->whereNotIn('status', ['lost'])->orderBy('id', 'DESC')->limit(500)->get()->getResultArray() : [],
        ];
    }

    private function missingColumn()
    {
        if (! db_connect()->fieldExists('superseded_by', 'quotations')) {
            return redirect()->to('admin/quotations')->with('error', 'Quick quote needs a database update: open Administration → Database Updates and run it once.');
        }

        return null;
    }
}
