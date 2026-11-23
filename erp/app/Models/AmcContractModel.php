<?php

namespace App\Models;

use CodeIgniter\Model;

class AmcContractModel extends Model
{
    protected $table          = 'amc_contracts';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'contract_no',
        'project_id',
        'customer_id',
        'amc_plan_id',
        'start_date',
        'end_date',
        'amount',
        'status',
        'notes',
        'created_by',
    ];
}
