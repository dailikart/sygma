<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= strip_tags($this->renderSection('title', true)) ?: 'Sygma Solar Suite' ?> | <?= esc(setting('Solar.companyName')) ?></title>
    <?= view('partials_favicon') ?>
    <?= view('partials_pwa') ?>
    <link href="<?= base_url("assets/vendor/bootstrap/css/bootstrap.min.css") ?>" rel="stylesheet">
    <link href="<?= base_url("assets/vendor/bootstrap-icons/bootstrap-icons.min.css") ?>" rel="stylesheet">
    <link href="<?= asset('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body data-theme="<?= esc((string) setting('Solar.theme')) ?>">
<div class="d-flex">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
        <div class="sidebar-brand d-flex align-items-center justify-content-between">
            <a href="<?= site_url('admin') ?>" class="text-white text-decoration-none fw-bold d-flex align-items-center gap-2">
                <?php if (setting('Solar.logoPath')): ?><img src="<?= site_url('branding/logo') ?>" alt=""><?php else: ?><img src="<?= asset('logo-sm.png') ?>" alt="" class="brand-mark" style="background:none;padding:0;width:34px;height:34px"><?php endif ?>
                <?php if (setting('Solar.logoPath')): ?><span class="text-truncate" style="max-width:160px"><?= esc((string) setting('Solar.companyName')) ?></span><?php else: ?><img src="<?= asset('wordmark-light.png') ?>" alt="Sygma Solar Suite" class="brand-wordmark" style="background:none;padding:0;height:30px;width:auto;max-width:150px"><?php endif ?></a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
        </div>
        <?= view('admin/partials/menu') ?>
    </aside>

    <div class="main flex-grow-1">
        <header class="topbar d-flex align-items-center px-3 gap-2">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-list"></i></button>
            <h1 class="h6 mb-0 text-truncate"><?= $this->renderSection('title', true) ?: 'Dashboard' ?></h1>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="<?= site_url('admin/manual/' . \App\Controllers\Admin\Manual::topicFor(uri_string())) ?>" class="btn btn-sm btn-light" title="Help for this screen (User Manual)"><i class="bi bi-question-circle"></i> <span class="d-none d-sm-inline">Help</span></a>
                <?php $unread = unread_notifications() ?>
                <a href="<?= site_url('admin/notifications') ?>" class="btn btn-sm btn-light position-relative" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unread ?></span><?php endif ?>
                </a>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?= esc(auth()->user()->username) ?></span></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted"><?= esc(implode(', ', auth()->user()->getGroups())) ?></span></li>
                        <li><a class="dropdown-item" href="<?= site_url('admin/profile') ?>">Change password</a></li>
                        <li><a class="dropdown-item" href="#" data-install-app><i class="bi bi-phone"></i> Install app on this device</a></li>
                        <li><a class="dropdown-item" href="<?= url_to('logout') ?>">Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="p-3 p-md-4">
            <?php if (auth()->user()->inGroup('superadmin') && uri_string() !== 'admin/maintenance' && ($pend = \App\Controllers\Admin\Maintenance::pending())): ?>
                <div class="alert alert-warning"><i class="bi bi-database-exclamation"></i> <?= count($pend) ?> database update(s) are waiting. <a href="<?= site_url('admin/maintenance') ?>" class="alert-link">Run them now</a>.</div>
            <?php endif ?>
            <?php $sc = \App\Libraries\Saas::current(); if ($sc && $sc['expires_on'] && can('settings.manage') && ($dl = (int) floor((strtotime($sc['expires_on']) - strtotime(date('Y-m-d'))) / 86400)) <= 10): ?>
                <div class="alert alert-warning py-2 small"><i class="bi bi-calendar-x"></i> Your Sygma Solar Suite subscription ends <?= $dl <= 0 ? 'today' : 'in ' . $dl . ' day' . ($dl === 1 ? '' : 's') ?> (<?= date('d-m-Y', strtotime($sc['expires_on'])) ?>). Please renew to keep using it without a break.</div>
            <?php endif ?>
            <?php if (\App\Filters\DemoGuard::enabled()): ?>
                <div class="alert alert-info py-2 small d-flex flex-wrap align-items-center gap-2"><i class="bi bi-stars"></i> <span><b>Demo company</b> with sample data. Users, roles and settings are view-only.</span></div>
            <?php endif ?>
            <?php foreach (['message' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $key => $cls): ?>
                <?php if (session()->getFlashdata($key)): ?>
                    <div class="alert alert-<?= $cls ?> alert-dismissible fade show"><?= esc(session()->getFlashdata($key)) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif ?>
            <?php endforeach ?>
            <?php if (session('errors')): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ((array) session('errors') as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
            <?php endif ?>

            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>
<script src="<?= base_url("assets/vendor/bootstrap/js/bootstrap.bundle.min.js") ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script src="<?= base_url('assets/js/searchable-select.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
