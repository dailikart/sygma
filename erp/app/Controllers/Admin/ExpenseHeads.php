<?php

namespace App\Controllers\Admin;

class ExpenseHeads extends MasterController
{
    protected ?string $import = 'expense-heads';
    protected string $table = 'expense_heads';
    protected string $title = 'Expense Heads';
    protected string $uri = 'expense-heads';
    protected string $viewPermission = 'accounts.view';
    protected string $managePermission = 'accounts.manage';
    protected array $listColumns = ['name', 'description', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]'];
    protected ?string $ledgerRef = 'expense_head';
    protected array $fields = [
        'name'        => ['Expense head * (same as the Tally ledger)', 'text'],
        'description' => ['Description', 'text'],
        'is_active'   => ['Active', 'bool'],
    ];
}
