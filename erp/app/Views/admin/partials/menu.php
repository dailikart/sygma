<?php
/**
 * Sidebar menu. Each entry shows only when the user has one of its permissions.
 * [label, url, icon, permissions (any), children]
 */
$menu = [
    ['Dashboard', 'admin', 'bi-speedometer2', []],
    ['My Tasks', 'admin/tasks', 'bi-list-check', []],
    ['Marketing', null, 'bi-megaphone', ['leads.view', 'customers.view'], [
        ['Leads', 'admin/leads', ['leads.view']],
        ['Customers', 'admin/customers', ['customers.view']],
    ]],
    ['Sales', null, 'bi-receipt', ['invoices.view', 'payments.manage'], [
        ['Invoices', 'admin/invoices', ['invoices.view']],
        ['Payments', 'admin/payments', ['invoices.view', 'payments.manage']],
    ]],
    ['Projects', 'admin/projects', 'bi-kanban', ['projects.view', 'survey.update', 'dispatch.manage', 'delivery.update', 'installation.update', 'eb.update', 'subsidy.update']],
    ['Dispatch', 'admin/dispatches', 'bi-truck', ['dispatch.manage', 'delivery.update']],
    ['AMC / Service', null, 'bi-wrench-adjustable', ['amc.view', 'amc.manage'], [
        ['Service Visits', 'admin/amc', ['amc.view', 'amc.manage']],
        ['AMC Plans', 'admin/amc-plans', ['amc.manage']],
    ]],
    ['Inventory', null, 'bi-box-seam', ['inventory.view', 'inventory.manage'], [
        ['Stock Balance', 'admin/stock', ['inventory.view']],
        ['Items', 'admin/items', ['inventory.view']],
        ['Item Groups / Kits (kW)', 'admin/kits', ['inventory.manage']],
        ['Installation Types', 'admin/installation-types', ['inventory.manage']],
        ['Item Categories', 'admin/item-categories', ['inventory.manage']],
        ['Stock Adjustment', 'admin/stock/adjust', ['inventory.manage']],
        ['Locations', 'admin/locations', ['inventory.manage']],
    ]],
    ['Purchase', null, 'bi-cart', ['purchases.view', 'suppliers.manage'], [
        ['Suppliers', 'admin/suppliers', ['suppliers.manage']],
        ['Purchase Orders', 'admin/purchase-orders', ['purchases.view']],
        ['Purchases', 'admin/purchases', ['purchases.view']],
        ['Purchase Returns', 'admin/purchase-returns', ['purchases.view']],
    ]],
    ['Accounts', null, 'bi-cash-coin', ['accounts.view'], [
        ['Income', 'admin/incomes', ['accounts.view']],
        ['Expenses', 'admin/expenses', ['accounts.view']],
        ['Supplier Payments', 'admin/supplier-payments', ['accounts.view']],
        ['Journal / Contra', 'admin/accounts/journal', ['accounts.manage']],
        ['Day Book', 'admin/accounts/day-book', ['accounts.view']],
        ['Ledgers', 'admin/ledgers', ['accounts.view']],
        ['Trial Balance', 'admin/accounts/trial-balance', ['accounts.view']],
        ['Profit & Loss', 'admin/accounts/profit-loss', ['accounts.view']],
        ['Income Heads', 'admin/income-heads', ['accounts.manage']],
        ['Expense Heads', 'admin/expense-heads', ['accounts.manage']],
        ['Bank / Cash Accounts', 'admin/bank-accounts', ['accounts.manage']],
    ]],
    ['Reports', null, 'bi-bar-chart', ['reports.view', 'tally.export'], [
        ['Income vs Expense', 'admin/reports/income-expense', ['reports.view']],
        ['Inventory Usage (B2B/B2C)', 'admin/reports/usage', ['reports.view']],
        ['Supplier Summary', 'admin/reports/suppliers', ['reports.view']],
        ['Project Status', 'admin/reports/projects', ['reports.view']],
        ['Subsidy Status', 'admin/reports/subsidy', ['reports.view']],
        ['Customers: Cash / Loan', 'admin/reports/finance', ['reports.view']],
        ['Profit & Loss', 'admin/accounts/profit-loss', ['reports.view']],
        ['Tally Export', 'admin/tally', ['tally.export']],
    ]],
    ['Administration', null, 'bi-shield-lock', ['users.manage', 'roles.manage', 'settings.manage'], [
        ['Users', 'admin/users', ['users.manage']],
        ['User Log', 'admin/user-log', ['users.manage']],
        ['Roles & Access', 'admin/roles', ['roles.manage']],
        ['Settings', 'admin/settings', ['settings.manage']],
        ['Document Types', 'admin/document-types', ['settings.manage']],
        ['WhatsApp Templates', 'admin/whatsapp-templates', ['settings.manage']],
        ['Message Log', 'admin/message-log', ['settings.manage']],
        ['Database Updates', 'admin/maintenance', ['settings.manage']],
    ]],
    ['User Manual', 'admin/manual', 'bi-book', []],
];

$allowed = static function (array $perms): bool {
    if ($perms === []) {
        return true;
    }
    foreach ($perms as $p) {
        if (can($p)) {
            return true;
        }
    }

    return false;
};
$current = trim(uri_string(), '/');
$isActive = static fn (string $url) => $current === $url || ($url !== 'admin' && str_starts_with($current . '/', $url . '/'));
?>
<ul class="nav flex-column sidebar-nav">
<?php foreach ($menu as $i => $m): ?>
    <?php if (! $allowed($m[3])) continue; ?>
    <?php if (empty($m[4])): ?>
        <li class="nav-item">
            <a class="nav-link <?= $isActive($m[1]) ? 'active' : '' ?>" href="<?= site_url($m[1]) ?>"><i class="bi <?= $m[2] ?>"></i> <?= esc($m[0]) ?></a>
        </li>
    <?php else: ?>
        <?php
        $children = array_filter($m[4], static fn ($c) => $allowed($c[2]));
        if ($children === []) continue;
        $open = (bool) array_filter($children, static fn ($c) => $isActive($c[1]));
        ?>
        <li class="nav-item">
            <a class="nav-link <?= $open ? '' : 'collapsed' ?>" data-bs-toggle="collapse" href="#menu<?= $i ?>" role="button" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                <i class="bi <?= $m[2] ?>"></i> <?= esc($m[0]) ?> <i class="bi bi-chevron-down ms-auto small chev"></i>
            </a>
            <div class="collapse <?= $open ? 'show' : '' ?>" id="menu<?= $i ?>">
                <ul class="nav flex-column ms-4">
                    <?php foreach ($children as $c): ?>
                        <li class="nav-item"><a class="nav-link py-1 <?= $isActive($c[1]) ? 'active' : '' ?>" href="<?= site_url($c[1]) ?>"><?= esc($c[0]) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </div>
        </li>
    <?php endif ?>
<?php endforeach ?>
</ul>
