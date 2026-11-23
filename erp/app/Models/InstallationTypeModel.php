<?php

namespace App\Models;

use CodeIgniter\Model;

class InstallationTypeModel extends Model
{
    protected $table          = 'installation_types';
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
