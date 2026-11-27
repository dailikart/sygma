<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc($title ?? 'Owner panel') ?> | Sygma Solar Suite</title>
    <link rel="icon" href="<?= base_url('favicon.png') ?>">
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <style>
        :root { --sy-dark: #1c1917; --sy-amber: #d97706; }
        body { background: #f7f5f2; }
        .navbar { background: var(--sy-dark); }
        .navbar .nav-link { color: #e7e5e4; } .navbar .nav-link.active, .navbar .nav-link:hover { color: #fbbf24; }
        .btn-primary { --bs-btn-bg: var(--sy-amber); --bs-btn-border-color: var(--sy-amber); --bs-btn-hover-bg: #b45309; --bs-btn-hover-border-color: #b45309; --bs-btn-active-bg: #92400e; }
        a { color: #b45309; } .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .stat .num { font-size: 1.5rem; font-weight: 600; }
        .table > :not(caption) > * > * { vertical-align: middle; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark mb-4">
    <div class="container-xl">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= site_url('owner') ?>"><img src="<?= base_url('logo-sm.png') ?>" alt="" style="height:30px"><img src="<?= base_url('wordmark-light.png') ?>" alt="Sygma Solar Suite" style="height:22px"><span class="badge text-bg-warning ms-1">Owner</span></a>
        <?php if (! empty($me)): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <?php $u = trim(service('uri')->getRoutePath(), '/'); ?>
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= $u === 'owner' || str_starts_with($u, 'owner/companies') ? 'active' : '' ?>" href="<?= site_url('owner') ?>">Companies</a></li>
                <li class="nav-item"><a class="nav-link <?= $u === 'owner/log' ? 'active' : '' ?>" href="<?= site_url('owner/log') ?>">Activity</a></li>
                <li class="nav-item"><a class="nav-link <?= $u === 'owner/account' ? 'active' : '' ?>" href="<?= site_url('owner/account') ?>">Owner logins</a></li>
            </ul>
            <span class="navbar-text small me-3"><?= esc($me['email']) ?></span>
            <a class="btn btn-sm btn-outline-light" href="<?= site_url('owner/logout') ?>">Sign out</a>
        </div>
        <?php endif ?>
    </div>
</nav>
<main class="container-xl pb-5">
    <?php foreach (['message' => 'success', 'error' => 'danger'] as $k => $cls): if ($m = session()->getFlashdata($k)): ?>
        <div class="alert alert-<?= $cls ?> alert-dismissible fade show"><?= esc($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; endforeach ?>
    <?= $this->renderSection('content') ?>
</main>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script>
document.addEventListener('submit', e => { const b = e.target.querySelector('[data-confirm]'); if (b && ! confirm(b.dataset.confirm)) e.preventDefault(); });
</script>
</body>
</html>
