<?php

namespace App\Models;

use CodeIgniter\Model;

class KitModel extends Model
{
    protected $table          = 'kits';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'installation_type_id',
        'capacity_kw',
        'description',
        'is_active',
        'created_by',
    ];
}
