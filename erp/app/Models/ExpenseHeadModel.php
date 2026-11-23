<?php

namespace App\Models;

use CodeIgniter\Model;

class ExpenseHeadModel extends Model
{
    protected $table          = 'expense_heads';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];
}
