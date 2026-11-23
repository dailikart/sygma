<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseReturnModel extends Model
{
    protected $table          = 'purchase_returns';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'return_no',
        'supplier_id',
        'location_id',
        'purchase_id',
        'return_date',
        'gst_type',
        'subtotal',
        'tax_total',
        'total',
        'reason',
        'created_by',
    ];
}
