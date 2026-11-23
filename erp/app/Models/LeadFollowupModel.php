<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadFollowupModel extends Model
{
    protected $table          = 'lead_followups';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields  = [
        'lead_id',
        'note',
        'status_after',
        'next_followup_date',
        'created_by',
    ];
}
