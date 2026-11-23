<?php

namespace App\Models;

use CodeIgniter\Model;

class MessageLogModel extends Model
{
    protected $table          = 'message_log';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields  = [
        'channel',
        'recipient',
        'event',
        'payload',
        'status',
        'response',
    ];
}
