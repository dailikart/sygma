<?php

namespace App\Models;

use CodeIgniter\Model;

class DispatchItemModel extends Model
{
    protected $table          = 'dispatch_items';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'dispatch_id',
        'item_id',
        'qty',
    ];
}
