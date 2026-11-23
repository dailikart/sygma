<?php

namespace App\Controllers\Admin;

use App\Libraries\Messenger;

/**
 * Maps business events to Meta-approved WhatsApp templates (WeBase).
 * "Params" picks the event variables in order → field_1, field_2…
 */
class WhatsappTemplates extends MasterController
{
    protected string $table = 'whatsapp_templates';
    protected string $title = 'WhatsApp Templates';
    protected string $uri = 'whatsapp-templates';
    protected string $viewPermission = 'settings.manage';
    protected string $managePermission = 'settings.manage';
    protected array $listColumns = ['event', 'template_name', 'language', 'params', 'is_active'];
    protected array $rules = ['event' => 'required', 'template_name' => 'required|max_length[100]'];
    protected string $searchColumn = 'template_name';

    protected function fieldDefs(): array
    {
        $events = [];
        foreach (Messenger::EVENTS as $e => $vars) {
            $events[$e] = ucfirst(str_replace('_', ' ', $e)) . ' — variables: ' . $vars;
        }

        return [
            'event'         => ['Event *', 'select', $events],
            'template_name' => ['Approved template name * (as in WeBase / Meta)', 'text'],
            'language'      => ['Language code (en, ta…)', 'text'],
            'params'        => ['Params in order, comma separated (e.g. customer_name,amount,invoice_no)', 'text'],
            'is_active'     => ['Active', 'bool'],
        ];
    }

    public function save($id = null)
    {
        $dupe = db_connect()->table('whatsapp_templates')->where('event', $this->request->getPost('event'));
        if ($id) {
            $dupe->where('id !=', (int) $id);
        }
        if ($dupe->countAllResults()) {
            return $this->back('A template is already set for this event. Edit that one instead.');
        }

        return parent::save($id);
    }
}
