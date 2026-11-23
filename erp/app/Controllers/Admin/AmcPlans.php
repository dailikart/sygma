<?php

namespace App\Controllers\Admin;

class AmcPlans extends MasterController
{
    protected string $table = 'amc_plans';
    protected string $title = 'AMC Plans';
    protected string $uri = 'amc-plans';
    protected string $viewPermission = 'amc.view';
    protected string $managePermission = 'amc.manage';
    protected array $listColumns = ['name', 'duration_months', 'interval_months', 'is_free', 'price', 'is_default', 'is_active'];
    protected array $rules = ['name' => 'required|max_length[100]', 'duration_months' => 'required|is_natural_no_zero', 'interval_months' => 'required|is_natural_no_zero'];
    protected array $fields = [
        'name'            => ['Plan name * (e.g. Free 5-year AMC)', 'text'],
        'duration_months' => ['Duration in months * (5 years = 60)', 'number'],
        'interval_months' => ['Service visit every … months *', 'number'],
        'is_free'         => ['Free (included with installation)', 'bool'],
        'price'           => ['Price (₹) if paid', 'number'],
        'description'     => ['What is covered', 'textarea'],
        'is_default'      => ['Default plan for new projects', 'bool'],
        'is_active'       => ['Active', 'bool'],
    ];

    public function save($id = null)
    {
        $res = parent::save($id);
        // Only one default plan.
        if ($this->request->getPost('is_default')) {
            $db  = db_connect();
            $cur = $id ?: $db->table('amc_plans')->selectMax('id')->get()->getRow()->id;
            $db->table('amc_plans')->where('id !=', (int) $cur)->update(['is_default' => 0]);
        }

        return $res;
    }
}
