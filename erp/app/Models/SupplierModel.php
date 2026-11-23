<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierModel extends Model
{
    protected $table          = 'suppliers';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'contact_person',
        'phone',
        'email',
        'gstin',
        'state',
        'address',
        'opening_balance',
        'is_active',
        'created_by',
    ];
}
