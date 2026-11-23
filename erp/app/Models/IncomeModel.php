<?php

namespace App\Models;

use CodeIgniter\Model;

class IncomeModel extends Model
{
    protected $table          = 'incomes';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'voucher_no',
        'income_head_id',
        'account_id',
        'income_date',
        'amount',
        'received_from',
        'reference',
        'notes',
        'created_by',
    ];
}
