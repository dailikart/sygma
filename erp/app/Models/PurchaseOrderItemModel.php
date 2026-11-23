<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseOrderItemModel extends Model
{
    protected $table          = 'purchase_order_items';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'purchase_order_id',
        'item_id',
        'qty',
        'received_qty',
        'rate',
        'tax_percent',
        'tax_amount',
        'amount',
    ];
}
