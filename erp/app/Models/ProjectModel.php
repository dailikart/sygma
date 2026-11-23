<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    protected $table          = 'projects';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'project_no',
        'customer_id',
        'invoice_id',
        'customer_type',
        'subsidy_type',
        'installation_type_id',
        'commissioned_on',
        'capacity_kw',
        'kit_id',
        'site_address',
        'current_stage',
        'status',
        'completed_at',
        'created_by',
    ];
}
