<?php

namespace App\Controllers\Admin;

use App\Libraries\TallyXml;

/**
 * Tally export from the accounts vouchers (same XML format as the school ERP).
 * Tally: Gateway of Tally → Import → Transactions → select the XML.
 */
class Tally extends Reports
{
    private const TYPES = ['Sales', 'Receipt', 'Purchase', 'Debit Note', 'Payment', 'Journal', 'Contra'];

    public function index()
    {
        $this->requirePermission('tally.export');
        [$from, $to] = fy_range();
        $type = $this->request->getGet('type');
        $db   = db_connect();
        $b    = $db->table('vouchers')->where('voucher_date >=', $from)->where('voucher_date <=', $to);
        if (in_array($type, self::TYPES, true)) {
            $b->where('voucher_type', $type);
        }
        $vouchers = $b->orderBy('voucher_date')->orderBy('id')->get()->getResultArray();
        $entries  = [];
        if ($vouchers) {
            foreach ($db->table('voucher_entries e')->select('e.*, l.name, l.group_name')->join('ledgers l', 'l.id = e.ledger_id')
                ->whereIn('e.voucher_id', array_column($vouchers, 'id'))->orderBy('e.voucher_id')->orderBy('e.debit', 'DESC')->get()->getResultArray() as $e) {
                $entries[$e['voucher_id']][] = $e;
            }
        }

        $export = $this->request->getGet('export');
        $label  = str_replace(' ', '', $type ?: 'All');
        if ($export === 'xml') {
            $xml   = new TallyXml();
            $bills = array_column(db_connect()->table('purchases')->select('id, bill_no, bill_date')->get()->getResultArray(), null, 'id');
            foreach ($vouchers as $v) {
                $lines = $entries[$v['id']] ?? [];
                $party = '';
                foreach ($lines as $e) {
                    if (in_array($e['group_name'], ['Sundry Debtors', 'Sundry Creditors'], true)) {
                        $party = $e['name'];
                        break;
                    }
                }
                $xml->voucher($v['voucher_type'], $v['voucher_date'], $v['voucher_no'], (string) $v['narration'],
                    array_map(static fn ($e) => [$e['name'], (float) ($e['debit'] > 0 ? $e['debit'] : $e['credit']), $e['debit'] > 0], $lines), $party,
                    $v['ref_type'] === 'purchase' ? (string) ($bills[$v['ref_id']]['bill_no'] ?? '') : '',
                    $v['ref_type'] === 'purchase' ? ($bills[$v['ref_id']]['bill_date'] ?? null) : null);
            }

            return $this->response->setHeader('Content-Type', 'application/xml; charset=utf-8')
                ->setHeader('Content-Disposition', 'attachment; filename="Tally_' . $label . '_' . $from . '_' . $to . '.xml"')
                ->setBody($xml->render());
        }
        if ($export === 'excel') {
            $rows = [];
            foreach ($vouchers as $v) {
                foreach ($entries[$v['id']] ?? [] as $e) {
                    $rows[] = [fdate($v['voucher_date']), $v['voucher_type'], $v['voucher_no'], $e['name'], $e['group_name'], (float) $e['debit'], (float) $e['credit'], (string) $v['narration']];
                }
            }

            return $this->excel('Tally_' . $label, ['Date', 'Voucher type', 'Voucher no', 'Ledger', 'Group', 'Debit', 'Credit', 'Narration'], $rows);
        }
        if ($export === 'ledgers') {
            return $this->ledgerMasters();
        }

        return $this->render('reports/tally', ['vouchers' => $vouchers, 'entries' => $entries, 'from' => $from, 'to' => $to, 'type' => $type, 'types' => self::TYPES]);
    }

    /** Ledger masters XML: import this first so every ledger exists in Tally. */
    private function ledgerMasters()
    {
        $body = '';
        foreach (db_connect()->table('ledgers')->where('is_active', 1)->orderBy('name')->get()->getResultArray() as $l) {
            $ob    = (float) $l['opening_balance'];
            $body .= '<TALLYMESSAGE xmlns:UDF="TallyUDF"><LEDGER NAME="' . TallyXml::esc($l['name']) . '" ACTION="Create">'
                . '<NAME.LIST><NAME>' . TallyXml::esc($l['name']) . '</NAME></NAME.LIST>'
                . '<PARENT>' . TallyXml::esc($l['group_name']) . '</PARENT>'
                . ($ob ? '<OPENINGBALANCE>' . number_format(-$ob, 2, '.', '') . '</OPENINGBALANCE>' : '')
                . '</LEDGER></TALLYMESSAGE>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<ENVELOPE><HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER><BODY><IMPORTDATA>"
            . '<REQUESTDESC><REPORTNAME>All Masters</REPORTNAME></REQUESTDESC><REQUESTDATA>' . $body . '</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>';

        return $this->response->setHeader('Content-Type', 'application/xml; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="Tally_Ledgers_' . date('Ymd') . '.xml"')->setBody($xml);
    }
}
