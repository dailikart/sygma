<?php

namespace App\Models;

use CodeIgniter\Model;

class VoucherModel extends Model
{
    protected $table          = 'vouchers';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields  = [
        'voucher_type',
        'voucher_no',
        'voucher_date',
        'narration',
        'ref_type',
        'ref_id',
        'amount',
        'created_by',
    ];
}
