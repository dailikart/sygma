<?php

namespace App\Controllers\Admin;

class InstallationTypes extends MasterController
{
    protected string $table = 'installation_types';
    protected string $title = 'Installation Types';
    protected string $uri = 'installation-types';
    protected string $viewPermission = 'inventory.view';
    protected string $managePermission = 'inventory.manage';
    protected ?array $back = ['admin/items', 'Back to Items'];
    protected array $listColumns = ['name', 'description', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]'];
    protected array $fields = [
        'name'        => ['Installation type * (e.g. On-grid, Off-grid, Hybrid, Solar water pump)', 'text'],
        'description' => ['Description', 'text'],
        'is_active'   => ['Active', 'bool'],
    ];
}
