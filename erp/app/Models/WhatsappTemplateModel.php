<?php

namespace App\Models;

use CodeIgniter\Model;

class WhatsappTemplateModel extends Model
{
    protected $table          = 'whatsapp_templates';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'event',
        'template_name',
        'language',
        'params',
        'is_active',
        'created_by',
    ];
}
