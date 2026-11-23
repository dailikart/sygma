<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->addRedirect('/', 'admin');

service('auth')->routes($routes);
$routes->get('branding/(:segment)', 'Admin\Settings::branding/$1');
// Install on phone (PWA): manifest and home-screen icons.
$routes->get('manifest.webmanifest', 'AppManifest::manifest');
$routes->get('app-icon/(:segment)', 'AppManifest::icon/$1');
// Quotation link sent to the customer (no login; long random token).
$routes->get('quote/(:segment)', 'PublicQuote::show/$1');
// Customer's project status page (no login; long random token).
$routes->get('track/(:segment)', 'PublicStatus::show/$1');
$routes->post('track/(:segment)/complaint', 'PublicStatus::complaint/$1');
// IndiaMART Push API (IndiaMART posts each new enquiry here; secret token in the address).
$routes->post('indiamart/push/(:segment)', 'IndiaMartPush::receive/$1');
// Meta Lead Ads and Google Ads lead-form webhooks.
$routes->get('webhooks/meta', 'LeadWebhooks::metaVerify');
$routes->post('webhooks/meta', 'LeadWebhooks::meta');
$routes->post('webhooks/google', 'LeadWebhooks::google');

// Admin area: every route here requires login. Module permissions are checked
// inside each controller (see AdminController::requirePermission).
$routes->group('admin', ['filter' => 'session', 'namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('/', 'Dashboard::index', ['as' => 'admin.dashboard']);
    $routes->get('tasks', 'Tasks::index');
    $routes->get('notifications', 'Notifications::index');
    $routes->get('notifications/(:num)', 'Notifications::open/$1');
    $routes->post('notifications/read-all', 'Notifications::readAll');
    $routes->get('profile', 'Profile::index');
    $routes->post('profile', 'Profile::update');
    $routes->get('files/(:num)', 'Files::show/$1');
    $routes->post('files/(:num)/delete', 'Files::delete/$1');

    // Administration
    $routes->get('users', 'Users::index');
    $routes->get('users/new', 'Users::form');
    $routes->get('users/(:num)/edit', 'Users::form/$1');
    $routes->post('users/save', 'Users::save');
    $routes->post('users/save/(:num)', 'Users::save/$1');
    $routes->post('users/(:num)/toggle', 'Users::toggle/$1');
    $routes->get('roles', 'Roles::index');
    $routes->post('roles', 'Roles::save');
    $routes->post('roles/reset', 'Roles::reset');
    $routes->get('settings', 'Settings::index');
    $routes->post('settings', 'Settings::save');
    $routes->post('settings/test-email', 'Settings::testEmail');
    $routes->get('message-log', 'MessageLog::index');
    $routes->get('user-log', 'UserLog::index');
    $routes->get('maintenance', 'Maintenance::index');
    $routes->post('maintenance/migrate', 'Maintenance::migrate');
    $routes->post('demo-data/reset', 'Maintenance::resetDemo');
    $routes->get('features', 'Features::index');
    $routes->post('features', 'Features::save');
    $routes->post('indiamart/pull', 'IndiaMartAdmin::pull');

    // User manual (every logged-in user).
    $routes->get('manual', 'Manual::index');
    $routes->get('manual/(:segment)', 'Manual::index/$1');

    // Bulk upload (Excel / CSV) for items, customers, suppliers, income / expense heads.
    $routes->get('import/(:segment)', 'Import::form/$1');
    $routes->post('import/(:segment)', 'Import::upload/$1');

    // Simple masters (list / add / edit) share one controller pattern.
    foreach (['items' => 'Items', 'locations' => 'Locations', 'suppliers' => 'Suppliers',
        'installation-types' => 'InstallationTypes', 'item-categories' => 'ItemCategories',
        'amc-plans' => 'AmcPlans', 'whatsapp-templates' => 'WhatsappTemplates', 'document-types' => 'DocumentTypes',
        'income-heads' => 'IncomeHeads', 'expense-heads' => 'ExpenseHeads', 'bank-accounts' => 'BankAccounts'] as $uri => $ctl) {
        $routes->get($uri, "{$ctl}::index");
        $routes->get("{$uri}/new", "{$ctl}::form");
        $routes->get("{$uri}/(:num)/edit", "{$ctl}::form/$1");
        $routes->post("{$uri}/save", "{$ctl}::save");
        $routes->post("{$uri}/save/(:num)", "{$ctl}::save/$1");
    }

    // Marketing
    $routes->get('leads', 'Leads::index');
    $routes->get('leads/new', 'Leads::form');
    $routes->get('leads/(:num)/edit', 'Leads::form/$1');
    $routes->post('leads/save', 'Leads::save');
    $routes->post('leads/save/(:num)', 'Leads::save/$1');
    $routes->get('leads/(:num)', 'Leads::show/$1');
    $routes->post('leads/(:num)/followup', 'Leads::followup/$1');
    $routes->post('leads/(:num)/convert', 'Leads::convert/$1');
    $routes->get('customers', 'Customers::index');
    $routes->get('customers/new', 'Customers::form');
    $routes->get('customers/(:num)/edit', 'Customers::form/$1');
    $routes->post('customers/save', 'Customers::save');
    $routes->post('customers/save/(:num)', 'Customers::save/$1');
    $routes->get('customers/(:num)', 'Customers::show/$1');
    $routes->post('customers/(:num)/documents', 'Documents::upload/$1');
    $routes->get('documents/(:num)', 'Documents::show/$1');
    $routes->post('documents/(:num)/delete', 'Documents::delete/$1');

    // Sales
    $routes->get('quotations', 'Quotations::index');
    $routes->get('quotations/new', 'Quotations::create');
    $routes->post('quotations', 'Quotations::store');
    $routes->get('quotations/(:num)', 'Quotations::show/$1');
    $routes->get('quotations/(:num)/edit', 'Quotations::edit/$1');
    $routes->post('quotations/(:num)/update', 'Quotations::update/$1');
    $routes->post('quotations/(:num)/delete', 'Quotations::delete/$1');
    $routes->post('quotations/(:num)/status', 'Quotations::status/$1');
    $routes->get('quotations/(:num)/pdf', 'Quotations::pdf/$1');
    $routes->get('quotations/(:num)/design', 'Quotations::design/$1');
    $routes->post('quotations/(:num)/email', 'Quotations::email/$1');
    $routes->get('quotations/(:num)/whatsapp', 'Quotations::whatsapp/$1');
    $routes->post('quotations/(:num)/convert', 'Quotations::convert/$1');
    $routes->get('invoices', 'Invoices::index');
    $routes->get('invoices/new', 'Invoices::create');
    $routes->post('invoices', 'Invoices::store');
    $routes->get('invoices/(:num)', 'Invoices::show/$1');
    $routes->get('invoices/(:num)/pdf', 'Invoices::pdf/$1');
    $routes->post('invoices/(:num)/email', 'Invoices::email/$1');
    $routes->post('invoices/(:num)/cancel', 'Invoices::cancel/$1');
    $routes->get('invoices/(:num)/edit', 'Invoices::edit/$1');
    $routes->post('invoices/(:num)/update', 'Invoices::update/$1');
    $routes->post('invoices/(:num)/delete', 'Invoices::delete/$1');
    $routes->get('payments/(:num)/edit', 'Payments::edit/$1');
    $routes->post('payments/(:num)/update', 'Payments::update/$1');
    $routes->post('payments/(:num)/delete', 'Payments::delete/$1');
    $routes->post('customers/(:num)/delete', 'Customers::delete/$1');
    $routes->post('items/(:num)/delete', 'Items::delete/$1');
    $routes->post('projects/(:num)/delete', 'Projects::delete/$1');
    $routes->get('projects/(:num)/status-link/whatsapp', 'Projects::statusWhatsapp/$1');
    $routes->post('projects/(:num)/status-link/reset', 'Projects::statusReset/$1');
    $routes->post('invoices/(:num)/finance', 'Invoices::finance/$1');
    $routes->post('invoices/(:num)/eb-charges', 'Invoices::ebCharges/$1');
    $routes->post('invoices/(:num)/tranches', 'Invoices::tranches/$1');
    $routes->get('payments', 'Payments::index');
    $routes->post('invoices/(:num)/payments', 'Payments::store/$1');
    $routes->get('payments/(:num)/receipt', 'Payments::receipt/$1');

    // Projects & workflow
    $routes->get('projects', 'Projects::index');
    $routes->get('projects/(:num)', 'Projects::show/$1');
    $routes->post('projects/(:num)/assign', 'Projects::assign/$1');
    $routes->post('projects/(:num)/stage/(:segment)', 'Projects::stage/$1/$2');
    $routes->post('projects/(:num)/upload', 'Projects::upload/$1');
    $routes->post('projects/(:num)/survey/reopen', 'Projects::reopenSurvey/$1');
    $routes->post('projects/(:num)/status', 'Projects::status/$1');

    // Dispatch
    $routes->get('dispatches', 'Dispatches::index');
    $routes->get('projects/(:num)/dispatch', 'Dispatches::create/$1');
    $routes->post('projects/(:num)/dispatch', 'Dispatches::store/$1');
    $routes->get('dispatches/(:num)', 'Dispatches::show/$1');
    $routes->get('dispatches/(:num)/print', 'Dispatches::printList/$1');
    $routes->get('dispatches/(:num)/delivery-note', 'Dispatches::deliveryNote/$1');
    $routes->post('dispatches/(:num)/dispatch', 'Dispatches::dispatch/$1');
    $routes->post('dispatches/(:num)/deliver', 'Dispatches::deliver/$1');
    $routes->post('dispatches/(:num)/delete', 'Dispatches::delete/$1');
    $routes->post('dispatches/(:num)/serials', 'Dispatches::serials/$1');

    // AMC
    $routes->get('amc', 'Amc::index');
    $routes->get('amc/contracts/(:num)', 'Amc::contract/$1');
    $routes->post('amc/visits/(:num)/assign', 'Amc::assign/$1');
    $routes->post('amc/visits/(:num)/complete', 'Amc::complete/$1');
    $routes->get('service', 'ServiceTickets::index');
    $routes->get('service/new', 'ServiceTickets::create');
    $routes->post('service', 'ServiceTickets::store');
    $routes->get('service/(:num)', 'ServiceTickets::show/$1');
    $routes->post('service/(:num)/assign', 'ServiceTickets::assign/$1');
    $routes->post('service/(:num)/update', 'ServiceTickets::update/$1');
    $routes->post('service/(:num)/resolve', 'ServiceTickets::resolve/$1');
    $routes->post('service/(:num)/close', 'ServiceTickets::close/$1');
    $routes->post('service/(:num)/delete', 'ServiceTickets::delete/$1');
    $routes->get('service/photo/(:num)', 'ServiceTickets::photo/$1');
    $routes->get('amc/visits/(:num)/photo', 'Amc::photo/$1');

    // Inventory
    $routes->get('stock', 'Stock::index');
    $routes->get('stock/item/(:num)', 'Stock::ledger/$1');
    $routes->post('stock/movements/(:num)/delete', 'Stock::deleteMovement/$1');
    $routes->get('stock/adjust', 'Stock::adjust');
    $routes->post('stock/adjust', 'Stock::saveAdjust');
    $routes->get('kits', 'Kits::index');
    $routes->get('kits/new', 'Kits::form');
    $routes->get('kits/(:num)/edit', 'Kits::form/$1');
    $routes->post('kits/save', 'Kits::save');
    $routes->post('kits/save/(:num)', 'Kits::save/$1');

    // Purchase
    foreach (['purchase-orders' => 'PurchaseOrders', 'purchases' => 'Purchases', 'purchase-returns' => 'PurchaseReturns'] as $uri => $ctl) {
        $routes->get($uri, "{$ctl}::index");
        $routes->get("{$uri}/new", "{$ctl}::create");
        $routes->post($uri, "{$ctl}::store");
        $routes->get("{$uri}/(:num)", "{$ctl}::show/$1");
        $routes->get("{$uri}/(:num)/pdf", "{$ctl}::pdf/$1");
        $routes->post("{$uri}/(:num)/delete", "{$ctl}::delete/$1");
    }
    $routes->post('purchase-orders/(:num)/cancel', 'PurchaseOrders::cancel/$1');

    // Accounts vouchers
    foreach (['incomes' => 'Incomes', 'expenses' => 'Expenses', 'supplier-payments' => 'SupplierPayments'] as $uri => $ctl) {
        $routes->get($uri, "{$ctl}::index");
        $routes->get("{$uri}/new", "{$ctl}::form");
        $routes->post($uri, "{$ctl}::store");
        $routes->post("{$uri}/(:num)/delete", "{$ctl}::delete/$1");
    }

    // Voucher printing (Tally-style)
    $routes->get('vouchers/(:num)/print', 'VoucherPrint::show/$1');
    $routes->get('vouchers/print/(:segment)/(:num)', 'VoucherPrint::byRef/$1/$2');

    // Ledger accounts
    $routes->get('ledgers', 'Ledgers::index');
    $routes->post('ledgers', 'Ledgers::store');
    $routes->get('ledgers/(:num)', 'Ledgers::show/$1');
    $routes->get('accounts/day-book', 'Ledgers::dayBook');
    $routes->get('accounts/trial-balance', 'Ledgers::trialBalance');
    $routes->get('accounts/journal', 'Ledgers::journal');
    $routes->post('accounts/journal', 'Ledgers::storeJournal');
    $routes->post('accounts/journal/(:num)/delete', 'Ledgers::deleteJournal/$1');

    // Reports & Tally
    $routes->get('reports/income-expense', 'Reports::incomeExpense');
    $routes->get('reports/usage', 'Reports::usage');
    $routes->get('reports/suppliers', 'Reports::suppliers');
    $routes->get('reports/suppliers/(:num)', 'Reports::supplierLedger/$1');
    $routes->get('reports/projects', 'Reports::projects');
    $routes->get('reports/project-profit', 'Reports::projectProfit');
    $routes->get('reports/subsidy', 'Reports::subsidy');
    $routes->get('reports/finance', 'Reports::finance');
    $routes->get('accounts/profit-loss', 'Reports::profitLoss');
    $routes->get('tally', 'Tally::index');
});
