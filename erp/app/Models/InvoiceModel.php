<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table          = 'invoices';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'invoice_no',
        'customer_id',
        'project_id',
        'invoice_date',
        'due_date',
        'subsidy_type',
        'capacity_kw',
        'installation_type_id',
        'gst_type',
        'subtotal',
        'tax_total',
        'total',
        'amount_paid',
        'status',
        'notes',
        'created_by',
    ];
}
