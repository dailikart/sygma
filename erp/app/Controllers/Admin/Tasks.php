<?php

namespace App\Controllers\Admin;

class Tasks extends AdminController
{
    /**
     * Active stages the current user should act on: assigned to them, or
     * unassigned and in a stage their role covers.
     */
    public static function myTasks(): array
    {
        $user = auth()->user();
        $rows = db_connect()->table('project_stages s')
            ->select('s.*, p.project_no, p.capacity_kw, p.subsidy_type, p.customer_type, c.name AS customer, c.city, c.phone')
            ->join('projects p', 'p.id = s.project_id')
            ->join('customers c', 'c.id = p.customer_id')
            ->where('s.status', 'active')->where('p.status', 'active')
            ->orderBy('s.planned_date IS NULL', '', false)->orderBy('s.planned_date')->orderBy('s.started_at')
            ->get()->getResultArray();

        $stages = config('Workflow')->stages;

        return array_values(array_filter($rows, static function ($r) use ($user, $stages) {
            if ((int) $r['assigned_to'] === (int) $user->id) {
                return true;
            }

            return ! $r['assigned_to'] && $user->can($stages[$r['stage']]['permission'] ?? '-');
        }));
    }

    public function index()
    {
        return $this->render('tasks', ['tasks' => self::myTasks()]);
    }
}
