<?php

namespace App\Controllers\Admin;

class Expenses extends VoucherDocs
{
    protected string $table = 'expenses';
    protected string $dateField = 'expense_date';
    protected string $prefixKey = 'Solar.expensePrefix';
    protected string $uri = 'expenses';
    protected string $title = 'Expense';
    protected string $headTable = 'expense_heads';
    protected string $headField = 'expense_head_id';
    protected string $headLabel = 'Expense head';
    protected string $partyField = 'paid_to';
    protected string $postMethod = 'postExpense';
}
