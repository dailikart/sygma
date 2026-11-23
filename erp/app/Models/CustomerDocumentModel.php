<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerDocumentModel extends Model
{
    protected $table          = 'customer_documents';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields  = [
        'customer_id',
        'project_id',
        'title',
        'file_path',
        'original_name',
        'uploaded_by',
    ];
}
