<?php

namespace App\Controllers\Admin;

class DocumentTypes extends MasterController
{
    protected string $table = 'document_types';
    protected string $title = 'Document Types';
    protected string $uri = 'document-types';
    protected string $viewPermission = 'customers.view';
    protected string $managePermission = 'settings.manage';
    protected array $listColumns = ['name', 'is_required', 'sort_order', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]'];
    protected array $fields = [
        'name'        => ['Document name *', 'text'],
        'is_required' => ['Required (needed before the PCR step)', 'bool'],
        'sort_order'  => ['Order in the list', 'number'],
        'is_active'   => ['Active', 'bool'],
    ];
}
