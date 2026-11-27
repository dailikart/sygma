<?php

namespace App\Filters;

use App\Libraries\Features;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocks every page of a module / option that is switched off for this company
 * (app/Config/Features.php). Admin pages go back to the dashboard with a note;
 * public links (quotation share link, status page, webhooks) answer 404.
 */
class FeatureGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (\App\Libraries\Saas::isOwnerRequest()) {
            return null; // owner control panel: no company modules
        }
        $path    = trim(service('uri')->getRoutePath(), '/');
        $blocked = Features::blockedBy($path);
        if ($blocked === null) {
            return null;
        }
        if (str_starts_with($path, 'admin')) {
            return redirect()->to('admin')->with('warning', $blocked . ' is not enabled for your company. Contact Sygma Solar Suite to add it.');
        }

        throw PageNotFoundException::forPageNotFound();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
