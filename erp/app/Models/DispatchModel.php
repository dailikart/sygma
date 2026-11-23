<?php

namespace App\Models;

use CodeIgniter\Model;

class DispatchModel extends Model
{
    protected $table          = 'dispatches';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'dispatch_no',
        'project_id',
        'location_id',
        'status',
        'vehicle_no',
        'driver_name',
        'dispatched_at',
        'dispatched_by',
        'delivered_at',
        'delivered_by',
        'delivery_note',
        'created_by',
    ];
}
