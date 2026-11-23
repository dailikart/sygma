<?php

namespace App\Controllers\Admin;

/**
 * List / add / edit for simple master tables. A child class only defines the
 * table, permission, title and fields.
 *
 * Field definition: name => [label, type, options]
 *   type: text | number | email | textarea | select | bool
 */
abstract class MasterController extends AdminController
{
    protected string $table;
    protected string $title;
    protected string $uri;
    protected string $viewPermission;
    protected string $managePermission;
    /** @var array<string, array> */
    protected array $fields = [];
    /** Columns shown in the list. */
    protected array $listColumns = [];
    protected array $rules = [];
    protected string $searchColumn = 'name';
    /** Ledger ref type when this master has an accounts ledger (supplier, account, income_head, expense_head). */
    protected ?string $ledgerRef = null;
    /** Optional "Back" link on the list page: [uri, label]. */
    protected ?array $back = null;
    /** Import entity (admin/import/<name>) when the list should show a Bulk upload button. */
    protected ?string $import = null;

    /** Extra form-only fields shown when adding a new record (not table columns). */
    protected function extraFields(): array
    {
        return [];
    }

    /** Field definitions; override to build dropdown options from the database. */
    protected function fieldDefs(): array
    {
        return $this->fields;
    }

    public function index()
    {
        $this->requirePermission($this->viewPermission, $this->managePermission);
        $db = db_connect();
        $b  = $db->table($this->table);
        if ($db->fieldExists('deleted_at', $this->table)) {
            $b->where('deleted_at', null);
        }
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $b->like($this->searchColumn, $q);
        }

        return $this->render('master/index', [
            'title' => $this->title, 'uri' => $this->uri, 'fields' => $this->fieldDefs(),
            'columns' => $this->listColumns, 'rows' => $b->orderBy('id', 'DESC')->get()->getResultArray(),
            'canManage' => can($this->managePermission), 'q' => $q, 'back' => $this->back, 'import' => $this->import,
        ]);
    }

    public function form($id = null)
    {
        $this->requirePermission($this->managePermission);

        return $this->render('master/form', [
            'title' => $this->title, 'uri' => $this->uri,
            'fields' => $this->fieldDefs() + ($id ? [] : $this->extraFields()),
            'row' => $id ? $this->findOr404($this->table, $id) : [],
        ]);
    }

    public function save($id = null)
    {
        $this->requirePermission($this->managePermission);
        if ($this->rules && ! $this->validate($this->rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [];
        foreach ($this->fieldDefs() as $name => $def) {
            $v = $this->request->getPost($name);
            if (($def[1] ?? 'text') === 'bool') {
                $v = $v ? 1 : 0;
            } elseif (($def[1] ?? '') === 'number') {
                $v = (float) $v;
            } elseif (($def[1] ?? '') === 'select' && ! array_is_list($def[2] ?? []) && ($v === '' || $v === null)) {
                $v = null;
            } else {
                $v = is_string($v) ? trim($v) : $v;
                if ($v === '') {
                    $v = null;
                }
            }
            $data[$name] = $v;
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $this->findOr404($this->table, $id);
            $db->table($this->table)->where('id', (int) $id)->update($data + ['updated_at' => $now]);
            if ($this->ledgerRef && isset($data['name'])) {
                \App\Libraries\Ledger::syncName($this->ledgerRef, (int) $id, $data['name']);
            }
        } else {
            $db->table($this->table)->insert($data + ['created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now]);
        }

        return redirect()->to('admin/' . $this->uri)->with('message', $this->title . ' saved' . (isset($data['name']) ? ': ' . $data['name'] : '') . '.');
    }
}
