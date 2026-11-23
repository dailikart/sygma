<?php

namespace App\Models;

use CodeIgniter\Model;

class ExpenseModel extends Model
{
    protected $table          = 'expenses';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'voucher_no',
        'expense_head_id',
        'account_id',
        'project_id',
        'expense_date',
        'amount',
        'paid_to',
        'reference',
        'attachment',
        'notes',
        'created_by',
    ];
}
