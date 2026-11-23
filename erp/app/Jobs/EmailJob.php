<?php

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;
use RuntimeException;

/**
 * Sends one email. Run the worker with: php spark queue:work emails
 */
class EmailJob extends BaseJob implements JobInterface
{
    protected int $tries      = 3;
    protected int $retryAfter = 120;

    public function process()
    {
        $r = \App\Libraries\Mailer::send($this->data['to'], $this->data['subject'], $this->data['body']);
        if (! $r['ok']) {
            throw new RuntimeException($r['error'] ?? 'Email failed');
        }
    }
}
