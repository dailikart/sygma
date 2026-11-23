<?php

namespace App\Controllers\Admin;

class ItemCategories extends MasterController
{
    protected string $table = 'item_categories';
    protected string $title = 'Item Categories';
    protected string $uri = 'item-categories';
    protected string $viewPermission = 'inventory.view';
    protected string $managePermission = 'inventory.manage';
    protected ?array $back = ['admin/items', 'Back to Items'];
    protected array $listColumns = ['name', 'installation_type_id', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]'];

    protected function fieldDefs(): array
    {
        return [
            'installation_type_id' => ['Installation type (leave empty if used by all types)', 'select', options('installation_types')],
            'name'                 => ['Category * (e.g. Solar Panel, Inverter, Structure, Cable)', 'text'],
            'is_active'            => ['Active', 'bool'],
        ];
    }
}
