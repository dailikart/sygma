<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectStageModel extends Model
{
    protected $table          = 'project_stages';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'project_id',
        'stage',
        'sequence',
        'status',
        'assigned_to',
        'planned_date',
        'data',
        'notes',
        'started_at',
        'completed_at',
        'completed_by',
    ];
}
