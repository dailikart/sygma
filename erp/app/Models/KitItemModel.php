<?php

namespace App\Models;

use CodeIgniter\Model;

class KitItemModel extends Model
{
    protected $table          = 'kit_items';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'kit_id',
        'item_id',
        'qty',
    ];
}
