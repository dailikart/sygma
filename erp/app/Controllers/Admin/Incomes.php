<?php

namespace App\Controllers\Admin;

/** Other income (scrap sale, service charges, interest…). Customer receipts are recorded on invoices. */
class Incomes extends VoucherDocs
{
    protected string $table = 'incomes';
    protected string $dateField = 'income_date';
    protected string $prefixKey = 'Solar.incomePrefix';
    protected string $uri = 'incomes';
    protected string $title = 'Income';
    protected string $headTable = 'income_heads';
    protected string $headField = 'income_head_id';
    protected string $headLabel = 'Income head';
    protected string $partyField = 'received_from';
    protected string $postMethod = 'postIncome';
}
