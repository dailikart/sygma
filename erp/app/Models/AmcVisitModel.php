<?php

namespace App\Models;

use CodeIgniter\Model;

class AmcVisitModel extends Model
{
    protected $table          = 'amc_visits';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'amc_contract_id',
        'visit_no',
        'due_date',
        'status',
        'assigned_to',
        'done_date',
        'done_by',
        'findings',
        'photo_path',
        'reminded_at',
        'created_by',
    ];
}
