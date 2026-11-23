<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountModel extends Model
{
    protected $table          = 'accounts';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'type',
        'account_no',
        'ifsc',
        'opening_balance',
        'is_active',
        'created_by',
    ];
}
