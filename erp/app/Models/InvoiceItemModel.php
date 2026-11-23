<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceItemModel extends Model
{
    protected $table          = 'invoice_items';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'invoice_id',
        'item_id',
        'description',
        'hsn',
        'qty',
        'rate',
        'tax_percent',
        'tax_amount',
        'amount',
    ];
}
