<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectFileModel extends Model
{
    protected $table          = 'project_files';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields  = [
        'project_id',
        'stage',
        'label',
        'file_path',
        'original_name',
        'uploaded_by',
    ];
}
