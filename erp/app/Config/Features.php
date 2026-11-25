<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Modules and options that can be switched on / off per company.
 *
 * Level 1 = module (a whole area: menu, screens, permissions, settings).
 * Level 2 = option inside a module (one feature, e.g. bank-loan tracking).
 *
 * What is switched off is saved in the `settings` table (setting('Features.off')),
 * so each company database keeps its own choice. It is changed from
 * Administration → Modules & Options (only on installs with features.manage = true
 * in .env, i.e. by you, not by the customer) or with `php spark features`.
 *
 * Keys of each module:
 *   title, description
 *   core        true = cannot be switched off (its options still can)
 *   requires    modules / options that must be on for this one to work
 *   permissions permission prefixes ("quotations" → quotations.view, quotations.manage)
 *   routes      URL prefixes blocked when off; "*" = any one segment
 *   settings    Settings-screen sections hidden when off
 *   options     [key => [title, description, requires, permissions, routes, settings]]
 */
class Features extends BaseConfig
{
    /** Saved per company in the settings table: keys that are OFF ("amc", "invoices.loan"). */
    public array $off = [];

    public array $catalogue = [
        'leads' => [
            'title'       => 'Leads & Customers',
            'description' => 'Leads, follow-ups, customers and their documents.',
            'core'        => true,
            'options'     => [
                'indiamart' => ['title' => 'IndiaMART lead import', 'description' => 'Pull / Push API from IndiaMART Lead Manager.',
                    'routes' => ['indiamart', 'admin/indiamart'], 'settings' => ['IndiaMART leads']],
                'meta'      => ['title' => 'Facebook / Instagram Lead Ads', 'description' => 'Leads from Meta Lead Ads forms.',
                    'routes' => ['webhooks/meta'], 'settings' => ['Meta (Facebook / Instagram) leads']],
                'google'    => ['title' => 'Google Ads lead forms', 'description' => 'Leads from Google Ads lead-form webhooks.',
                    'routes' => ['webhooks/google'], 'settings' => ['Google Ads leads']],
            ],
        ],
        'quotations' => [
            'title'       => 'Quotations / Proposals',
            'description' => 'Quotations with savings, share link to the customer, convert to invoice.',
            'permissions' => ['quotations'],
            'routes'      => ['admin/quotations', 'quote'],
            'settings'    => ['Quotation'],
        ],
        'design' => [
            'title'       => '3D roof design',
            'description' => 'Draw the roof, place panels automatically, 3D view with sun and shadows, shading loss and generation; quotation from the design.',
            'permissions' => ['designs'],
            'routes'      => ['admin/designs'],
        ],
        'invoices' => [
            'title'       => 'Invoices & Payments',
            'description' => 'GST invoices, receipts and part payments.',
            'core'        => true,
            'options'     => [
                'loan' => ['title' => 'Bank-loan finance tracking', 'description' => 'Cash / loan payment mode, lender, loan disbursements.'],
            ],
        ],
        'projects' => [
            'title'       => 'Projects workflow',
            'description' => 'Survey → dispatch → delivery → installation, with tasks and stage photos.',
            'requires'    => ['inventory'],
            'permissions' => ['projects', 'survey', 'dispatch', 'delivery', 'installation', 'eb', 'subsidy'],
            'routes'      => ['admin/projects', 'admin/dispatches', 'admin/tasks', 'admin/reports/projects', 'admin/reports/project-profit', 'track'],
            'options'     => [
                'eb'          => ['title' => 'EB / net-meter stage', 'description' => 'EB application, net meter and PCR stage after installation.'],
                'subsidy'     => ['title' => 'PM Surya Ghar subsidy (DCR + subsidy stages)', 'description' => 'Subsidy customers, DCR submission and subsidy tracking.',
                    'requires' => ['projects.eb'], 'permissions' => ['subsidy'], 'routes' => ['admin/reports/subsidy']],
                'status_page' => ['title' => 'Customer status page', 'description' => 'Private no-login link for the customer to follow the project.',
                    'routes' => ['track', 'admin/projects/*/status-link']],
            ],
        ],
        'inventory' => [
            'title'       => 'Inventory / Store',
            'description' => 'Items, kits by kW, stock by location, adjustments.',
            'permissions' => ['inventory'],
            'routes'      => ['admin/stock', 'admin/items', 'admin/kits', 'admin/installation-types', 'admin/item-categories', 'admin/locations', 'admin/reports/usage'],
        ],
        'purchase' => [
            'title'       => 'Purchase & Suppliers',
            'description' => 'Suppliers, purchase orders, goods received, returns, supplier payments.',
            'requires'    => ['inventory'],
            'permissions' => ['suppliers', 'purchases'],
            'routes'      => ['admin/suppliers', 'admin/purchase-orders', 'admin/purchases', 'admin/purchase-returns', 'admin/supplier-payments', 'admin/reports/suppliers'],
        ],
        'accounts' => [
            'title'       => 'Accounts',
            'description' => 'Income, expenses, journal, ledgers, day book, trial balance, P&L.',
            'permissions' => ['accounts'],
            'routes'      => ['admin/incomes', 'admin/expenses', 'admin/accounts', 'admin/ledgers', 'admin/income-heads', 'admin/expense-heads', 'admin/reports/income-expense'],
        ],
        'tally' => [
            'title'       => 'Tally export',
            'description' => 'Ledger masters and vouchers as Tally XML.',
            'requires'    => ['accounts'],
            'permissions' => ['tally'],
            'routes'      => ['admin/tally'],
            'settings'    => ['Tally ledger names'],
        ],
        'amc' => [
            'title'       => 'AMC & Service',
            'description' => 'AMC plans and visits, service complaints, reminders.',
            'permissions' => ['amc'],
            'routes'      => ['admin/amc', 'admin/amc-plans', 'admin/service', 'track/*/complaint'],
            'settings'    => ['AMC'],
        ],
        'whatsapp' => [
            'title'       => 'WhatsApp messages',
            'description' => 'Automatic WhatsApp messages to customers (WeBase Cloud API).',
            'routes'      => ['admin/whatsapp-templates'],
            'settings'    => ['WhatsApp (WeBase Cloud API)'],
        ],
    ];
}
