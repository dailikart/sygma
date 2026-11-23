<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * User log: records every successful change (POST) in the admin area —
 * who, when, which screen/action, the record, the result message and IP.
 * Failed attempts (validation errors) are not logged.
 */
class AuditLog implements FilterInterface
{
    /** URIs that are not worth logging. */
    private const SKIP = ['admin/notifications/read-all'];

    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            if (strtoupper($request->getMethod()) !== 'POST' || ! auth()->loggedIn()) {
                return;
            }
            $uri = trim($request->getUri()->getPath(), '/');
            if (in_array($uri, self::SKIP, true) || ! str_starts_with($uri, 'admin')) {
                return;
            }
            $session = session();
            if ($session->getFlashdata('error') || $session->getFlashdata('errors')) {
                return; // the action did not go through
            }

            $router = service('router');
            $ctl    = class_basename((string) $router->controllerName());
            $method = (string) $router->methodName();
            $params = $router->params();
            $target = $response instanceof RedirectResponse ? trim(parse_url((string) $response->getHeaderLine('Location'), PHP_URL_PATH) ?? '', '/') : '';
            // Record id: from the URL (/save/5, /12/cancel) or the redirect (/invoices/7).
            $id = null;
            foreach ($params as $p) {
                if (ctype_digit((string) $p)) {
                    $id = (int) $p;
                    break;
                }
            }
            if ($id === null && preg_match('#/(\d+)(?:/[a-z-]+)?$#', $target, $m)) {
                $id = (int) $m[1];
            }

            $verb = match (true) {
                in_array($ctl, ['Settings', 'Roles'], true) && $method === 'save' => 'Updated',
                $method === 'save'                       => $id && ! empty($params) ? 'Updated' : 'Created',
                in_array($method, ['store', 'upload'], true) => 'Created',
                $method === 'delete'                     => 'Deleted',
                default                                  => ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $method))),
            };
            $module  = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $ctl));
            $message = preg_replace('/\s+/', ' ', (string) ($session->getFlashdata('message') ?? $session->getFlashdata('warning') ?? ''));

            db_connect()->table('activity_log')->insert([
                'user_id'     => auth()->id(),
                'entity'      => 'audit',
                'entity_id'   => $id,
                'action'      => $ctl . '::' . $method,
                'description' => mb_substr(sprintf('%s – %s%s%s | %s | IP %s', $module, $verb, $id ? ' #' . $id : '', $message !== '' ? ': ' . $message : '', $target ?: $uri, $request->getIPAddress()), 0, 1000),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Audit log failed: ' . $e->getMessage());
        }
    }
}
