<?php

namespace App\Models;

use CodeIgniter\Model;

class AmcPlanModel extends Model
{
    protected $table          = 'amc_plans';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'duration_months',
        'interval_months',
        'is_free',
        'price',
        'description',
        'is_default',
        'is_active',
        'created_by',
    ];
}
