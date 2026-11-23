<?php

namespace App\Controllers\Admin;

use App\Libraries\IndiaMart;

/** "Fetch now" button for the IndiaMART Pull API. */
class IndiaMartAdmin extends AdminController
{
    public function pull()
    {
        $this->requirePermission('settings.manage', 'leads.manage');
        try {
            $r = IndiaMart::pull();
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with($r['ok'] ? 'message' : 'error', 'IndiaMART: ' . $r['message']);
    }
}
