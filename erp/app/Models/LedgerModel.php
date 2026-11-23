<?php

namespace App\Models;

use CodeIgniter\Model;

class LedgerModel extends Model
{
    protected $table          = 'ledgers';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'group_name',
        'ref_type',
        'ref_id',
        'opening_balance',
        'is_active',
        'created_by',
    ];
}
