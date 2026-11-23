<?php

namespace App\Controllers;

use App\Libraries\IndiaMart;

/** Receives IndiaMART Push API calls: one enquiry per POST, JSON body. */
class IndiaMartPush extends BaseController
{
    public function receive(string $token)
    {
        helper('app');
        $expected = (string) setting('Solar.indiamartPushToken');
        if ($expected === '' || ! hash_equals($expected, $token) || ! setting('Solar.indiamartEnabled')) {
            return $this->response->setStatusCode(404)->setJSON(['code' => 404, 'status' => 'Not found']);
        }
        $json = json_decode((string) $this->request->getBody(), true);
        if (! is_array($json)) {
            $json = $this->request->getPost() ?: [];
        }
        $resp = $json['RESPONSE'] ?? $json;
        $rows = isset($resp['UNIQUE_QUERY_ID']) ? [$resp] : (array_is_list((array) $resp) ? (array) $resp : []);
        $stats = IndiaMart::import($rows);
        setting('Solar.indiamartLastResult', date('d-m-Y h:i A') . ' – Push: ' . $stats['added'] . ' new, ' . $stats['merged'] . ' added to existing, ' . $stats['skipped'] . ' already imported.');

        return $this->response->setJSON(['code' => 200, 'status' => 'Success']);
    }
}
