<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseOrderModel extends Model
{
    protected $table          = 'purchase_orders';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'po_no',
        'supplier_id',
        'po_date',
        'expected_date',
        'gst_type',
        'subtotal',
        'tax_total',
        'total',
        'status',
        'notes',
        'created_by',
    ];
}
