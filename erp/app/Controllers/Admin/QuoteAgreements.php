<?php

namespace App\Controllers\Admin;

use App\Libraries\Agreement;
use App\Libraries\Pdf;
use App\Libraries\QuickQuote;
use App\Libraries\Workflow;

/**
 * Consumer–vendor agreement for a quotation: details the quotation does not
 * have (father's name, Aadhaar, agreement date), payment stages for this
 * customer, then a PDF filled from the quotation.
 */
class QuoteAgreements extends AdminController
{
    public function form($id)
    {
        $this->requirePermission('quotations.manage');
        [$q, $spec, $cfg] = $this->load((int) $id);
        if (! is_array($q)) {
            return $q;
        }

        return $this->render('quotations/agreement', ['q' => $q, 'a' => Agreement::data($q, $spec, $cfg), 'cfg' => $cfg]);
    }

    public function save($id)
    {
        $this->requirePermission('quotations.manage');
        [$q, $spec, $cfg] = $this->load((int) $id);
        if (! is_array($q)) {
            return $q;
        }
        $p      = $this->request->getPost();
        $aadhar = preg_replace('/\D/', '', (string) ($p['aadhaar'] ?? ''));
        if ($aadhar !== '' && strlen($aadhar) !== 12) {
            return redirect()->back()->withInput()->with('error', 'Aadhaar number must have 12 digits.');
        }
        if (! empty($p['date']) && ! strtotime((string) $p['date'])) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid agreement date.');
        }
        $stages = [];
        foreach ((array) ($p['stages'] ?? []) as $s) {
            $name = trim(strip_tags((string) ($s['name'] ?? '')));
            $pct  = round((float) ($s['pct'] ?? 0), 2);
            if ($name !== '' && $pct > 0) {
                $stages[] = ['name' => $name, 'pct' => $pct, 'text' => trim(strip_tags((string) ($s['text'] ?? ''))), 'amount' => round((float) $q['total'] * $pct / 100)];
            }
        }
        $sum = array_sum(array_column($stages, 'pct'));
        if ($stages && abs($sum - 100) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'Payment stages add up to ' . QuickQuote::fmt($sum) . '%. They must add up to 100%.');
        }
        $text = trim(str_replace("\r\n", "\n", (string) ($p['text'] ?? '')));
        $a    = [
            'date' => (string) ($p['date'] ?? '') ?: date('Y-m-d'), 'father_name' => trim(strip_tags((string) ($p['father_name'] ?? ''))), 'aadhaar' => $aadhar,
            'address' => trim(strip_tags((string) ($p['address'] ?? ''))), 'pincode' => preg_replace('/\D/', '', (string) ($p['pincode'] ?? '')),
            'eb_number' => trim(strip_tags((string) ($p['eb_number'] ?? ''))), 'stamp' => ! empty($p['stamp']), 'stages' => $stages,
            'text' => ! empty($p['reset_text']) || $text === '' ? $cfg['agreement_text'] : $text, 'saved' => true,
        ];
        db_connect()->table('quotations')->where('id', $q['id'])->update(['agreement' => json_encode($a, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]);
        Workflow::log('quotation', (int) $q['id'], 'agreement', 'Agreement details saved');

        return redirect()->to('admin/quotations/' . $q['id'] . '/agreement')->with('message', 'Agreement saved. Use "Open agreement PDF" to print it.')
            ->with('open_pdf', ! empty($p['open']));
    }

    public function pdf($id)
    {
        $this->requirePermission('quotations.view', 'quotations.manage');
        [$q, $spec, $cfg] = $this->load((int) $id, false);
        if (! is_array($q)) {
            return $q;
        }
        $a    = Agreement::data($q, $spec, $cfg);
        $vars = Agreement::vars($q, $spec, $a, $cfg);
        $html = Agreement::render($a['text'], $vars, $a['stages'], (float) $q['total'], [
            'customer' => $q['party_name'], 'company' => $vars['company_name'],
            'signature' => brand_img('signature', 'max-height:14mm;max-width:45mm'),
        ]);
        Pdf::stream('pdf/agreement', ['q' => $q, 'a' => $a, 'body' => $html], 'Agreement-' . str_replace('/', '-', $q['quote_no']), (bool) $this->request->getGet('download'));
    }

    /** [quotation, quick spec, rate card] or a redirect. */
    private function load(int $id, bool $forEdit = true): array
    {
        if (! db_connect()->fieldExists('agreement', 'quotations')) {
            return [redirect()->to('admin/quotations')->with('error', 'The agreement needs a database update: open Administration → Database Updates and run it once.'), null, null];
        }
        $q = $this->findOr404('quotations', $id);
        if ($forEdit && $q['superseded_by']) {
            return [redirect()->to('admin/quotations/' . $q['id'])->with('error', 'This is an older version. Make the agreement from the latest version.'), null, null];
        }
        $spec = json_decode((string) ($q['quick_spec'] ?? ''), true);

        return [$q, is_array($spec) ? $spec : null, QuickQuote::config()];
    }
}
