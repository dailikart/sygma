<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadModel extends Model
{
    protected $table          = 'leads';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'phone',
        'email',
        'address',
        'city',
        'source',
        'customer_type',
        'required_kw',
        'monthly_bill',
        'status',
        'assigned_to',
        'next_followup_date',
        'customer_id',
        'lost_reason',
        'notes',
        'created_by',
    ];
}
