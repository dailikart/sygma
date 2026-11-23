<?php

namespace App\Controllers\Admin;

class BankAccounts extends MasterController
{
    protected string $table = 'accounts';
    protected string $title = 'Bank / Cash Accounts';
    protected string $uri = 'bank-accounts';
    protected string $viewPermission = 'accounts.view';
    protected string $managePermission = 'accounts.manage';
    protected array $listColumns = ['name', 'type', 'account_no', 'ifsc', 'opening_balance', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]', 'type' => 'required|in_list[bank,cash]'];
    protected ?string $ledgerRef = 'account';
    protected array $fields = [
        'name'            => ['Account name * (same as the Tally ledger, e.g. "HDFC Bank")', 'text'],
        'type'            => ['Type *', 'select', ['bank' => 'Bank', 'cash' => 'Cash']],
        'account_no'      => ['Account number', 'text'],
        'ifsc'            => ['IFSC', 'text'],
        'opening_balance' => ['Opening balance (₹)', 'number'],
        'is_active'       => ['Active', 'bool'],
    ];
}
