<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

/**
 * Roles (groups) and module permissions for the Solar CRM.
 *
 * These are the DEFAULTS. Admins change the role → permission matrix from
 * Admin → Roles & Access; that is saved in the `settings` table and overrides
 * this file (Shield reads it through setting('AuthGroups.matrix')).
 *
 * One user can belong to several roles, for staff who do 2–3 jobs.
 * Check access in code with: auth()->user()->can('invoices.manage')
 */
class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'staff';

    public array $groups = [
        'superadmin' => ['title' => 'Super Admin',          'description' => 'Full control, including users, roles and settings.'],
        'admin'      => ['title' => 'Admin / Manager',      'description' => 'Sees all modules and assigns project stages.'],
        'marketing'  => ['title' => 'Marketing / Sales',    'description' => 'Leads, follow-ups, customers and invoices.'],
        'accounts'   => ['title' => 'Accounts',             'description' => 'Payments, income, expenses, supplier payments, Tally export.'],
        'surveyor'   => ['title' => 'Site Surveyor',        'description' => 'Inspects the site and approves or rejects it.'],
        'store'      => ['title' => 'Store In-charge',      'description' => 'Stock, purchases, dispatch lists and material dispatch.'],
        'delivery'   => ['title' => 'Delivery',             'description' => 'Marks material delivered at the site with a photo.'],
        'installer'  => ['title' => 'Installation Team',    'description' => 'Installation photos, serial numbers and agreements.'],
        'liaison'    => ['title' => 'EB / Subsidy Liaison', 'description' => 'EB / net-meter process, PCR and subsidy tracking.'],
        'service'    => ['title' => 'Service / AMC',        'description' => 'AMC service visits after commissioning.'],
        'staff'      => ['title' => 'Staff',                'description' => 'Login only. Sees tasks assigned to them.'],
    ];

    /**
     * Grouped by module. The "Roles & Access" screen shows them in this order.
     */
    public array $permissions = [
        // Administration
        'users.manage'        => 'Users: create, edit, assign roles',
        'roles.manage'        => 'Roles & Access: change module permissions',
        'settings.manage'     => 'Settings: company, GST and Tally ledgers',
        'entries.edit'        => 'Edit saved entries: invoices (lines, amounts, dates) and payments',
        'entries.delete'      => 'Delete entries: customers, invoices, payments, projects, purchases and items',
        // Marketing
        'leads.view'          => 'Leads: view',
        'leads.manage'        => 'Leads: add, edit, follow-up, convert',
        'leads.delete'        => 'Leads: delete (not leads already converted to a customer)',
        'customers.view'      => 'Customers: view',
        'customers.manage'    => 'Customers: add and edit',
        'documents.upload'    => 'Customer documents: upload (Aadhaar, EB bill… for PCR)',
        // Billing
        'quotations.view'     => 'Quotations: view',
        'quotations.manage'   => 'Quotations: create, edit, send, convert to invoice',
        'quotations.delete'   => 'Quotations: delete (not quotations already converted to an invoice)',
        'designs.view'        => '3D roof designs: view',
        'designs.manage'      => '3D roof designs: create, edit, delete, make a quotation',
        'invoices.view'       => 'Invoices: view',
        'invoices.manage'     => 'Invoices: create and cancel',
        'payments.manage'     => 'Customer payments: record receipts',
        // Projects / workflow
        'projects.view'       => 'Projects: view all projects',
        'projects.manage'     => 'Projects: assign stages, cancel, reopen',
        'survey.update'       => 'Stage: site survey & approval',
        'dispatch.manage'     => 'Stage: prepare and dispatch material',
        'delivery.update'     => 'Stage: mark delivered with photo',
        'installation.update' => 'Stage: installation details',
        'eb.update'           => 'Stage: EB / net-meter process',
        'subsidy.update'      => 'Stage: subsidy status',
        // Inventory & purchase
        'inventory.view'      => 'Inventory: view stock',
        'inventory.manage'    => 'Inventory: items, kits, locations, adjustments',
        'suppliers.manage'    => 'Suppliers: add and edit',
        'purchases.view'      => 'Purchases: view POs, purchases, returns',
        'purchases.manage'    => 'Purchases: create POs, purchases, returns',
        // Accounts
        'accounts.view'       => 'Accounts: view income and expenses',
        'accounts.manage'     => 'Accounts: income, expenses, supplier payments, journal, heads, bank accounts',
        // AMC / service
        'amc.view'            => 'AMC: view contracts and visits',
        'amc.manage'          => 'AMC: plans, complete visits, assign technicians',
        // Reports
        'reports.view'        => 'Reports: view',
        'tally.export'        => 'Tally: export XML / Excel',
    ];

    public array $matrix = [
        'superadmin' => [
            'users.*', 'roles.*', 'settings.*', 'entries.*', 'leads.*', 'customers.*', 'quotations.*', 'designs.*', 'invoices.*', 'payments.*',
            'projects.*', 'survey.*', 'dispatch.*', 'delivery.*', 'installation.*', 'eb.*', 'subsidy.*',
            'inventory.*', 'suppliers.*', 'purchases.*', 'accounts.*', 'reports.*', 'tally.*',
            'documents.*', 'amc.*',
        ],
        'admin' => [
            'users.manage', 'entries.edit', 'entries.delete', 'leads.*', 'customers.*', 'quotations.*', 'designs.*', 'invoices.*', 'payments.*', 'projects.*',
            'survey.*', 'dispatch.*', 'delivery.*', 'installation.*', 'eb.*', 'subsidy.*',
            'inventory.view', 'purchases.view', 'accounts.view', 'reports.*', 'documents.*', 'amc.*',
        ],
        'marketing' => ['leads.view', 'leads.manage', 'customers.*', 'quotations.view', 'quotations.manage', 'designs.view', 'designs.manage', 'documents.upload', 'invoices.*', 'projects.view'],
        'accounts'  => [
            'customers.view', 'quotations.view', 'invoices.view', 'payments.manage', 'projects.view', 'purchases.view',
            'suppliers.manage', 'accounts.*', 'reports.view', 'tally.export',
        ],
        'surveyor'  => ['survey.update', 'designs.view', 'designs.manage'],
        'store'     => ['dispatch.manage', 'inventory.*', 'suppliers.manage', 'purchases.*', 'projects.view'],
        'delivery'  => ['delivery.update'],
        'installer' => ['installation.update'],
        'liaison'   => ['eb.update', 'subsidy.update', 'projects.view', 'customers.view', 'documents.upload'],
        'service'   => ['amc.*', 'customers.view'],
        'staff'     => [],
    ];
}
