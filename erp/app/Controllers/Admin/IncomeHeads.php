<?php

namespace App\Controllers\Admin;

class IncomeHeads extends MasterController
{
    protected ?string $import = 'income-heads';
    protected string $table = 'income_heads';
    protected string $title = 'Income Heads';
    protected string $uri = 'income-heads';
    protected string $viewPermission = 'accounts.view';
    protected string $managePermission = 'accounts.manage';
    protected array $listColumns = ['name', 'description', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]'];
    protected ?string $ledgerRef = 'income_head';
    protected array $fields = [
        'name'        => ['Income head * (same as the Tally ledger)', 'text'],
        'description' => ['Description', 'text'],
        'is_active'   => ['Active', 'bool'],
    ];
}
