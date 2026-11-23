<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table          = 'items';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'sku',
        'name',
        'installation_type_id',
        'category_id',
        'unit',
        'hsn',
        'tax_percent',
        'purchase_price',
        'sale_price',
        'min_stock',
        'is_active',
        'created_by',
    ];
}
