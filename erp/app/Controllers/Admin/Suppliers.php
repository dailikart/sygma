<?php

namespace App\Controllers\Admin;

class Suppliers extends MasterController
{
    protected ?string $import = 'suppliers';
    protected string $table = 'suppliers';
    protected string $title = 'Suppliers';
    protected string $uri = 'suppliers';
    protected string $viewPermission = 'purchases.view';
    protected string $managePermission = 'suppliers.manage';
    protected array $listColumns = ['name', 'contact_person', 'phone', 'gstin', 'state', 'opening_balance', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[191]', 'email' => 'permit_empty|valid_email'];
    protected ?string $ledgerRef = 'supplier';
    protected array $fields = [
        'name'            => ['Supplier name * (same as the Tally ledger)', 'text'],
        'contact_person'  => ['Contact person', 'text'],
        'phone'           => ['Phone', 'text'],
        'email'           => ['Email', 'email'],
        'gstin'           => ['GSTIN', 'text'],
        'state'           => ['State (for CGST/SGST or IGST)', 'text'],
        'address'         => ['Address', 'textarea'],
        'opening_balance' => ['Opening balance we owe (₹)', 'number'],
        'is_active'       => ['Active', 'bool'],
    ];
}
