<?php

namespace App\Controllers;

use App\Controllers\Admin\Quotations;
use App\Libraries\Pdf;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The link sent to the customer (WhatsApp / email): opens the quotation PDF
 * without logging in. The long random token in the link is the only key.
 */
class PublicQuote extends BaseController
{
    public function show(string $token)
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $q = db_connect()->table('quotations')->where('share_token', $token)->get()->getRowArray();
        if (! $q) {
            throw PageNotFoundException::forPageNotFound();
        }
        helper('app');
        if (! $q['viewed_at']) {
            db_connect()->table('quotations')->where('id', $q['id'])->update(['viewed_at' => date('Y-m-d H:i:s')]);
        }
        $d = Quotations::dataFor($q);
        Pdf::stream(Quotations::pdfView($d), $d, str_replace('/', '-', $q['quote_no']), false);
    }
}
