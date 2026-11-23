<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseModel extends Model
{
    protected $table          = 'purchases';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'purchase_no',
        'supplier_id',
        'location_id',
        'purchase_order_id',
        'bill_no',
        'bill_date',
        'gst_type',
        'subtotal',
        'tax_total',
        'total',
        'notes',
        'created_by',
    ];
}
