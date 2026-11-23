<?php

namespace App\Models;

use CodeIgniter\Model;

class IncomeHeadModel extends Model
{
    protected $table          = 'income_heads';
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
