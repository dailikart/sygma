<?php

namespace App\Jobs;

use App\Libraries\Messenger;
use App\Libraries\WhatsApp;
use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;
use RuntimeException;

/**
 * Sends one WhatsApp template message. Worker: php spark queue:work whatsapp
 */
class WhatsAppJob extends BaseJob implements JobInterface
{
    protected int $tries      = 3;
    protected int $retryAfter = 120;

    public function process()
    {
        $d      = $this->data;
        $result = WhatsApp::sendTemplate($d['phone'], $d['template'], $d['params'] ?? [], $d['language'] ?? 'en');
        Messenger::log('whatsapp', $d['phone'], $d['event'] ?? '', $d, $result);

        if (empty($result['ok'])) {
            throw new RuntimeException('WhatsApp failed: ' . json_encode($result));
        }
    }
}
