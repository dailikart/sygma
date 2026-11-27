<?php

namespace App\Controllers\Admin;

use App\Libraries\Workflow;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Serves uploaded project files (photos, agreements) to logged-in users who
 * can see the project. Files live outside the public folder.
 */
class Files extends AdminController
{
    public function show($id)
    {
        $file = $this->findOr404('project_files', $id);
        $ok   = can('projects.view');
        if (! $ok) {
            foreach (Workflow::stages((int) $file['project_id']) as $s) {
                if (Workflow::canAct($s)) {
                    $ok = true;
                    break;
                }
            }
        }
        if (! $ok) {
            throw new \App\Exceptions\ForbiddenException();
        }

        $path = realpath(upload_path() . $file['file_path']);
        if ($path === false || ! str_starts_with($path, realpath(upload_path()))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path) ?: 'application/octet-stream')
            ->setHeader('Content-Disposition', 'inline; filename="' . basename($file['original_name'] ?: $path) . '"')
            ->setBody(file_get_contents($path));
    }

    /** Can the current user delete this file? Stage owner while the stage is open; managers always. */
    public static function canDelete(array $file, ?array $stageRow): bool
    {
        if (can('projects.manage')) {
            return true;
        }

        return $stageRow !== null && in_array($stageRow['status'], ['active', 'pending'], true) && Workflow::canAct($stageRow);
    }

    public function delete($id)
    {
        $file  = $this->findOr404('project_files', $id);
        $stage = Workflow::stage((int) $file['project_id'], (string) $file['stage']);
        if (! self::canDelete($file, $stage)) {
            return $this->back('This file belongs to a completed stage. Only a manager can delete it.');
        }
        $base = realpath(upload_path());
        $path = realpath(upload_path() . $file['file_path']);
        if ($path && str_starts_with($path, $base)) {
            @unlink($path);
        }
        db_connect()->table('project_files')->where('id', $file['id'])->delete();
        $label = ucfirst(str_replace('_', ' ', $file['label']));
        Workflow::log('project', (int) $file['project_id'], 'file_deleted', $label . ' (' . Workflow::stageTitle((string) $file['stage']) . ') deleted');

        return redirect()->to('admin/projects/' . $file['project_id'] . '#stage-' . $file['stage'])->with('message', $label . ' deleted.');
    }
}
