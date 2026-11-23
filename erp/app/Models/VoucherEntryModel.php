<?php

namespace App\Models;

use CodeIgniter\Model;

class VoucherEntryModel extends Model
{
    protected $table          = 'voucher_entries';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [
        'voucher_id',
        'ledger_id',
        'debit',
        'credit',
    ];
}
