<?php

namespace App\Controllers\Admin;

class SupplierPayments extends VoucherDocs
{
    protected string $table = 'supplier_payments';
    protected string $dateField = 'payment_date';
    protected string $prefixKey = 'Solar.supplierPayPrefix';
    protected string $uri = 'supplier-payments';
    protected string $title = 'Supplier Payment';
    protected string $headTable = 'suppliers';
    protected string $headField = 'supplier_id';
    protected string $headLabel = 'Supplier';
    protected string $partyField = '';
    protected string $postMethod = 'postSupplierPayment';
}
