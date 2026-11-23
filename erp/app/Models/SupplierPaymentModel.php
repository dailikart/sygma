<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierPaymentModel extends Model
{
    protected $table          = 'supplier_payments';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'voucher_no',
        'supplier_id',
        'account_id',
        'payment_date',
        'amount',
        'mode',
        'reference',
        'notes',
        'created_by',
    ];
}
