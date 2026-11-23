<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table          = 'customers';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'type',
        'name',
        'company',
        'gstin',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'eb_service_no',
        'lead_id',
        'notes',
        'created_by',
    ];
}
