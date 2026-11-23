<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseReturnItemModel extends Model
{
    protected $table          = 'purchase_return_items';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'purchase_return_id',
        'item_id',
        'qty',
        'rate',
        'tax_percent',
        'tax_amount',
        'amount',
    ];
}
