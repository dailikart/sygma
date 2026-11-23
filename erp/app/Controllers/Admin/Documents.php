<?php

namespace App\Controllers\Admin;

use App\Libraries\Workflow;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Customer documents (Aadhaar, EB bill, property tax…). Collected at
 * confirmation / after the invoice and used for the PCR on the EB portal.
 * Add rows with "+", give each a title and a file; the file is stored under
 * that title.
 */
class Documents extends AdminController
{
    public function upload($customerId)
    {
        $this->requirePermission('documents.upload', 'customers.manage');
        $customer = $this->findOr404('customers', $customerId);
        $titles   = (array) $this->request->getPost('titles');
        $files    = $this->request->getFileMultiple('files') ?? [];
        $project  = (int) $this->request->getPost('project_id') ?: null;
        $db       = db_connect();

        // Fixed document types: one file input per type (type_files[<id>]).
        $typeIds = [];
        $types   = array_column($db->table('document_types')->get()->getResultArray(), 'name', 'id');
        foreach ((array) ($this->request->getFiles()['type_files'] ?? []) as $typeId => $file) {
            if ($file && $file->isValid() && isset($types[$typeId])) {
                $files[]           = $file;
                $titles[]          = $types[$typeId];
                $typeIds[count($files) - 1] = (int) $typeId;
            }
        }

        $saved = 0;
        foreach ($files as $i => $file) {
            $title = trim((string) ($titles[$i] ?? ''));
            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                continue;
            }
            if ($title === '') {
                return $this->back('Give every document a title.');
            }
            $ext = strtolower($file->getClientExtension() ?: $file->guessExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf'], true)) {
                return $this->back('"' . $title . '": only images and PDF files are allowed.');
            }
            if ($file->getSizeByUnit('mb') > 10) {
                return $this->back('"' . $title . '" is larger than 10 MB.');
            }
            // Stored under the title, e.g. customers/12/Aadhaar-card-1699999999.pdf
            $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-') ?: 'document';
            $name = $slug . '-' . time() . $i . '.' . $ext;
            $file->move(WRITEPATH . 'uploads/customers/' . $customer['id'], $name);
            $db->table('customer_documents')->insert([
                'customer_id' => $customer['id'], 'project_id' => $project, 'title' => $title,
                'document_type_id' => $typeIds[$i] ?? null,
                'file_path' => 'customers/' . $customer['id'] . '/' . $name, 'original_name' => $file->getClientName(),
                'uploaded_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $saved++;
        }
        if (! $saved) {
            return $this->back('Choose at least one file.');
        }
        if ($project) {
            Workflow::log('project', $project, 'documents', $saved . ' customer document(s) uploaded');
        }

        return redirect()->back()->with('message', $saved . ' document(s) uploaded.');
    }

    public function show($id)
    {
        $doc = $this->findOr404('customer_documents', $id);
        if (! can('customers.view') && ! can('documents.upload') && ! can('eb.update')) {
            throw new \App\Exceptions\ForbiddenException();
        }
        $base = realpath(WRITEPATH . 'uploads');
        $path = realpath(WRITEPATH . 'uploads/' . $doc['file_path']);
        if ($path === false || ! str_starts_with($path, $base)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $download = $doc['title'] . '.' . pathinfo($path, PATHINFO_EXTENSION);
        $mode     = $this->request->getGet('download') ? 'attachment' : 'inline';

        return $this->response->setHeader('Content-Type', mime_content_type($path) ?: 'application/octet-stream')
            ->setHeader('Content-Disposition', $mode . '; filename="' . str_replace('"', '', $download) . '"')
            ->setBody(file_get_contents($path));
    }

    public function delete($id)
    {
        $this->requirePermission('documents.upload', 'customers.manage');
        $doc = $this->findOr404('customer_documents', $id);
        @unlink(WRITEPATH . 'uploads/' . $doc['file_path']);
        db_connect()->table('customer_documents')->where('id', $doc['id'])->delete();

        return redirect()->back()->with('message', 'Document deleted.');
    }

    /** Documents list + upload form, embedded on customer and project pages. */
    public static function panel(int $customerId, ?int $projectId = null): string
    {
        $db    = db_connect();
        $docs  = $db->table('customer_documents')->where('customer_id', $customerId)->orderBy('id', 'DESC')->get()->getResultArray();
        $types = $db->table('document_types')->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->get()->getResultArray();
        $byType = [];
        $others = [];
        foreach ($docs as $d) {
            if ($d['document_type_id']) {
                $byType[$d['document_type_id']][] = $d;
            } else {
                $others[] = $d;
            }
        }

        return view('admin/partials/documents', ['types' => $types, 'byType' => $byType, 'others' => $others, 'customerId' => $customerId, 'projectId' => $projectId]);
    }

    /** Required document types still missing for a customer. */
    public static function missingRequired(int $customerId): array
    {
        $db   = db_connect();
        $have = array_column($db->table('customer_documents')->select('document_type_id')->where('customer_id', $customerId)->where('document_type_id IS NOT NULL', null, false)->get()->getResultArray(), 'document_type_id');

        return array_column(array_filter(
            $db->table('document_types')->where(['is_active' => 1, 'is_required' => 1])->orderBy('sort_order')->get()->getResultArray(),
            static fn ($t) => ! in_array($t['id'], $have, false)
        ), 'name');
    }
}
