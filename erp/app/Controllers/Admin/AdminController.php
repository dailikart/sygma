<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base controller for every screen in the admin area (all routes need login).
 */
abstract class AdminController extends BaseController
{
    protected $helpers = ['form', 'url', 'app', 'setting', 'auth'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Options switched off for this company (Modules & Options) cannot be chosen.
        if ($request->getMethod() === 'POST') {
            $post = $request->getPost();
            if (isset($post['subsidy_type']) && ! \App\Libraries\Features::on('projects.subsidy')) {
                $post['subsidy_type'] = 'non_subsidy';
            }
            if (($post['finance_mode'] ?? '') === 'loan' && ! \App\Libraries\Features::on('invoices.loan')) {
                $post['finance_mode'] = '';
            }
            $request->setGlobal('post', $post);
        }
    }

    protected function render(string $view, array $data = []): string
    {
        return view('admin/' . $view, $data);
    }

    /** Stops with a 403 page when the user lacks the permission. */
    protected function requirePermission(string ...$permissions): void
    {
        foreach ($permissions as $p) {
            if (can($p)) {
                return;
            }
        }

        throw new \App\Exceptions\ForbiddenException();
    }

    protected function findOr404(string $table, $id): array
    {
        $row = db_connect()->table($table)->where('id', (int) $id)->get()->getRowArray();
        if (! $row) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }

    protected function back(string $error): RedirectResponse
    {
        return redirect()->back()->withInput()->with('error', $error);
    }

    /** Posted repeating rows (items[n][field]) → list of arrays without empty rows. */
    protected function postedRows(string $key = 'items'): array
    {
        $rows = (array) $this->request->getPost($key);

        return array_values(array_filter($rows, static fn ($r) => is_array($r) && (! empty($r['item_id']) || trim((string) ($r['description'] ?? '')) !== '')));
    }

    /** Stores an uploaded file under writable/uploads/<folder>; returns relative path or null. */
    protected function storeUpload(string $field, string $folder): ?string
    {
        $file = $this->request->getFile($field);
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return null;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic'];
        if (! in_array(strtolower($file->getExtension() ?: $file->guessExtension()), $allowed, true)) {
            throw new \RuntimeException('Only images (JPG, PNG, WEBP, HEIC) and PDF files can be uploaded.');
        }
        if ($file->getSizeByUnit('mb') > 10) {
            throw new \RuntimeException('File is larger than 10 MB.');
        }
        $name = $file->getRandomName();
        $file->move(WRITEPATH . 'uploads/' . $folder, $name);

        return $folder . '/' . $name;
    }
}
