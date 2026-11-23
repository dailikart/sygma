<?php

namespace App\Controllers\Admin;

class Locations extends MasterController
{
    protected string $table = 'locations';
    protected string $title = 'Stock Locations';
    protected string $uri = 'locations';
    protected string $viewPermission = 'inventory.view';
    protected string $managePermission = 'inventory.manage';
    protected array $listColumns = ['name', 'address', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]'];
    protected array $fields = [
        'name'      => ['Store / warehouse name *', 'text'],
        'address'   => ['Address', 'textarea'],
        'is_active' => ['Active', 'bool'],
    ];
}
