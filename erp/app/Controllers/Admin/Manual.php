<?php

namespace App\Controllers\Admin;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * User Manual: a step-by-step chapter for every module, inside the app.
 * Chapter texts are in app/Views/admin/manual/<slug>.php.
 */
class Manual extends AdminController
{
    /**
     * slug => [title, icon, group, who uses it, screens (URL prefixes) that open this chapter from the Help button]
     */
    public const TOPICS = [
        'getting-started' => ['Getting started', 'bi-play-circle', 'Basics', 'Everyone', ['admin', 'admin/tasks', 'admin/notifications', 'admin/profile']],
        'whats-new'       => ["What's new", 'bi-stars', 'Basics', 'Everyone', []],
        'setup'           => ['First-time setup', 'bi-gear-wide-connected', 'Basics', 'Super Admin', []],
        'leads'           => ['Leads & follow-ups', 'bi-megaphone', 'Sales', 'Marketing / Sales', ['admin/leads']],
        'customers'       => ['Customers & documents', 'bi-people', 'Sales', 'Marketing / Sales', ['admin/customers', 'admin/documents', 'admin/files']],
        'roof-design'     => ['3D roof design', 'bi-badge-3d', 'Sales', 'Marketing / Sales, Surveyor', ['admin/designs']],
        'quotations'      => ['Quotations / proposals', 'bi-file-earmark-text', 'Sales', 'Marketing / Sales', ['admin/quotations']],
        'invoices'        => ['Invoices', 'bi-receipt', 'Sales', 'Marketing / Sales, Accounts', ['admin/invoices', 'admin/einvoice']],
        'payments'        => ['Payments, receipts & bank loans', 'bi-cash', 'Sales', 'Accounts', ['admin/payments']],
        'projects'        => ['Projects & stages', 'bi-kanban', 'Projects', 'Admin / Manager, all stage teams', ['admin/projects']],
        'status-page'     => ['Customer status page', 'bi-phone', 'Projects', 'Admin, Sales, Accounts', []],
        'survey'          => ['Site survey', 'bi-clipboard-check', 'Projects', 'Site Surveyor', []],
        'dispatch'        => ['Material dispatch & delivery note', 'bi-box-seam', 'Projects', 'Store In-charge', ['admin/dispatches']],
        'delivery'        => ['Delivery at site', 'bi-truck', 'Projects', 'Delivery person', []],
        'installation'    => ['Installation', 'bi-tools', 'Projects', 'Installation team', []],
        'dcr'             => ['DCR & installation submission', 'bi-patch-check', 'Projects', 'EB / Subsidy Liaison', []],
        'eb'              => ['EB / net meter', 'bi-lightning-charge', 'Projects', 'EB / Subsidy Liaison', []],
        'subsidy'         => ['Subsidy', 'bi-bank', 'Projects', 'EB / Subsidy Liaison', []],
        'complaints'      => ['Service complaints', 'bi-tools', 'Service', 'Service / AMC team, office', ['admin/service']],
        'amc'             => ['AMC & service visits', 'bi-wrench-adjustable', 'Service', 'Service / AMC', ['admin/amc', 'admin/amc-plans']],
        'inventory'       => ['Inventory: items, kits & stock', 'bi-boxes', 'Store', 'Store In-charge', ['admin/stock', 'admin/items', 'admin/kits', 'admin/installation-types', 'admin/item-categories', 'admin/locations']],
        'purchase'        => ['Purchase: PO, purchase & return', 'bi-cart', 'Store', 'Store In-charge, Accounts', ['admin/suppliers', 'admin/purchase-orders', 'admin/purchases', 'admin/purchase-returns']],
        'bulk-upload'     => ['Bulk upload (Excel)', 'bi-upload', 'Store', 'Admin, Store, Accounts', ['admin/import']],
        'payroll'         => ['Payroll: employees, attendance & salaries', 'bi-person-vcard', 'Accounts', 'Accounts / HR', ['admin/employees', 'admin/attendance', 'admin/payroll', 'admin/my-payslips']],
        'accounts'        => ['Accounts: income, expenses & ledgers', 'bi-cash-coin', 'Accounts', 'Accounts', ['admin/incomes', 'admin/expenses', 'admin/supplier-payments', 'admin/accounts', 'admin/ledgers', 'admin/income-heads', 'admin/expense-heads', 'admin/bank-accounts', 'admin/vouchers']],
        'reports'         => ['Reports', 'bi-bar-chart', 'Accounts', 'Admin, Accounts', ['admin/reports']],
        'tally'           => ['Tally export', 'bi-arrow-left-right', 'Accounts', 'Accounts', ['admin/tally']],
        'administration'  => ['Users, roles & settings', 'bi-shield-lock', 'Administration', 'Super Admin', ['admin/users', 'admin/roles', 'admin/settings', 'admin/user-log', 'admin/document-types', 'admin/whatsapp-templates', 'admin/message-log', 'admin/maintenance']],
        'email-whatsapp'  => ['Email & WhatsApp setup', 'bi-envelope-at', 'Administration', 'Super Admin', []],
        'troubleshooting' => ['Problems & solutions', 'bi-life-preserver', 'Help', 'Everyone', []],
    ];

    public function index($slug = 'getting-started')
    {
        if (! isset(self::TOPICS[$slug]) || ! is_file(APPPATH . 'Views/admin/manual/' . $slug . '.php')) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->render('manual/index', ['slug' => $slug, 'topics' => self::TOPICS]);
    }

    /** The chapter for the screen the user is on (used by the Help button). */
    public static function topicFor(string $uri): string
    {
        $uri  = trim($uri, '/');
        $best = 'getting-started';
        $len  = 0;
        foreach (self::TOPICS as $slug => $t) {
            foreach ($t[4] as $prefix) {
                if (($uri === $prefix || str_starts_with($uri . '/', $prefix . '/')) && strlen($prefix) > $len) {
                    [$best, $len] = [$slug, strlen($prefix)];
                }
            }
        }

        return $best;
    }
}
