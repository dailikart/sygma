<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table          = 'notifications';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields  = [
        'user_id',
        'title',
        'message',
        'link',
        'read_at',
    ];
}
