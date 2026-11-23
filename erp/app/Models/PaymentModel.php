<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table          = 'payments';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'receipt_no',
        'invoice_id',
        'customer_id',
        'account_id',
        'payment_date',
        'amount',
        'mode',
        'reference',
        'notes',
        'created_by',
    ];
}
