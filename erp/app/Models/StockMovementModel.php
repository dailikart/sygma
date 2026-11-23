<?php

namespace App\Models;

use CodeIgniter\Model;

class StockMovementModel extends Model
{
    protected $table          = 'stock_movements';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields  = [
        'item_id',
        'location_id',
        'qty',
        'type',
        'ref_type',
        'ref_id',
        'note',
        'created_by',
    ];
}
