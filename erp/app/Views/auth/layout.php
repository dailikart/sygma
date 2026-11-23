<?php
$company = (string) setting('Solar.companyName');
$tagline = (string) (setting('Solar.companyTagline') ?: 'Powering homes with the sun');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->renderSection('title') ?> | <?= esc($company) ?></title>
    <?= view('partials_favicon') ?>
    <?= view('partials_pwa') ?>
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <style>
        :root { --sun-teal: #0d5c66; --sun-teal-2: #12808c; --sun-amber: #c98a06; --sun-ink: #134e55; }
        html, body { min-height: 100%; }
        body {
            margin: 0; color: var(--sun-ink); overflow-x: hidden; position: relative; min-height: 100vh;
            background:
                radial-gradient(circle at 80% 18%, rgba(255, 255, 255, .95) 0, rgba(255, 244, 196, .9) 9%, rgba(253, 224, 120, .55) 22%, transparent 42%),
                linear-gradient(160deg, #fff8dc 0%, #fdeaa6 45%, #f9d56e 100%);
            background-color: #fdeaa6;
        }
        /* Soft sun rays */
        .sun-rays { position: fixed; top: -30vmax; right: -30vmax; width: 100vmax; height: 100vmax; pointer-events: none; opacity: .35;
            background: repeating-conic-gradient(from 0deg, rgba(255, 255, 255, .55) 0deg 4deg, transparent 4deg 14deg);
            -webkit-mask: radial-gradient(circle, #000 0, transparent 62%); mask: radial-gradient(circle, #000 0, transparent 62%);
            animation: spin 120s linear infinite; }
        .sun-disc { position: fixed; top: 6vh; right: 8vw; width: 22vmin; height: 22vmin; border-radius: 50%; pointer-events: none;
            background: radial-gradient(circle, #fffef6 0, #fff3b0 45%, rgba(255, 214, 90, .0) 72%); filter: blur(2px); }
        @keyframes spin { to { transform: rotate(360deg); } }
        /* Solar panel field in perspective along the bottom */
        .panels { position: fixed; left: -15%; right: -15%; bottom: -2px; height: 34vh; pointer-events: none;
            transform: perspective(520px) rotateX(58deg); transform-origin: bottom center;
            background:
                repeating-linear-gradient(90deg, rgba(255, 246, 214, .85) 0 3px, transparent 3px 16.6%),
                repeating-linear-gradient(0deg, rgba(255, 246, 214, .55) 0 2px, transparent 2px 25%),
                linear-gradient(180deg, #4f8f97 0%, #2f6f78 60%, #245a62 100%);
            box-shadow: 0 -12px 40px rgba(255, 255, 255, .35) inset; }
        .panels::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(253, 234, 166, .9) 0, transparent 35%); }
        .status-pill { position: fixed; top: 18px; right: 22px; z-index: 3; background: rgba(255, 255, 255, .75); backdrop-filter: blur(6px);
            border-radius: 999px; padding: .35rem .9rem; font-weight: 600; font-size: .85rem; box-shadow: 0 4px 14px rgba(0, 0, 0, .06); }
        .status-pill i { color: #22c55e; font-size: .6rem; vertical-align: middle; }

        .auth-wrap { position: relative; z-index: 2; min-height: 100vh; display: flex; align-items: center; padding: 3rem 1rem 22vh; }
        .brand { text-align: center; }
        .brand-logo { width: min(300px, 70vw); aspect-ratio: 1; margin: 0 auto 1.2rem; border-radius: 6px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(145deg, #fde68a, #f59e0b); box-shadow: 0 18px 40px rgba(180, 110, 0, .25); overflow: hidden; }
        .brand-logo img { width: 100%; height: 100%; object-fit: contain; background: #fff; }
        .brand-logo .bi { font-size: 7rem; color: #fff; text-shadow: 0 4px 18px rgba(170, 90, 0, .45); }
        .brand h1 { font-weight: 800; font-size: clamp(1.8rem, 3.4vw, 2.6rem); color: var(--sun-teal); margin: 0; }
        .brand .tag { text-transform: uppercase; letter-spacing: .22em; font-weight: 700; color: var(--sun-amber); font-size: clamp(.75rem, 1.2vw, .95rem); margin: .5rem 0 1rem; }
        .brand p { font-size: 1.05rem; color: var(--sun-ink); max-width: 420px; margin: 0 auto; }

        .auth-card { background: rgba(255, 255, 255, .94); border: 0; border-radius: 28px; box-shadow: 0 30px 70px rgba(120, 80, 0, .18); padding: 2.2rem 2.2rem 1.4rem; max-width: 460px; margin: 0 auto; }
        .auth-card .icon-tile { width: 56px; height: 56px; border-radius: 16px; background: #fff3c4; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: #f59e0b; flex: none; }
        .auth-card h2 { font-weight: 800; color: var(--sun-teal); margin: 0; font-size: 1.9rem; }
        .auth-card .sub { color: var(--sun-teal-2); }
        .auth-card label.form-label { font-weight: 700; color: var(--sun-teal); margin-bottom: .35rem; }
        .auth-input { position: relative; }
        .auth-input > .bi:first-child { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--sun-teal-2); }
        .auth-input .form-control { border-radius: 14px; padding: .8rem 2.8rem; border-color: #dbe7e8; background: #fbfdfd; }
        .auth-input .form-control:focus { border-color: var(--sun-teal-2); box-shadow: 0 0 0 .2rem rgba(18, 128, 140, .15); }
        .auth-input .toggle-pw { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--sun-teal-2); padding: .3rem .5rem; }
        .btn-sun { background: linear-gradient(90deg, var(--sun-teal), var(--sun-teal-2)); color: #fff; border: 0; border-radius: 14px; padding: .8rem; font-weight: 700; font-size: 1.1rem; box-shadow: 0 10px 22px rgba(13, 92, 102, .25); }
        .btn-sun:hover, .btn-sun:focus { color: #fff; filter: brightness(1.07); }
        .auth-foot { border-top: 1px solid #eef2f2; margin-top: 1.4rem; padding-top: .9rem; font-size: .85rem; color: #5b7c80; text-align: center; }
        .auth-card a { color: var(--sun-teal-2); }
        @media (max-width: 991.98px) { .brand p { display: none; } .brand-logo { width: 140px; } .brand-logo .bi { font-size: 4rem; } .auth-wrap { padding-top: 4rem; } }
        @media (max-width: 575.98px) { .auth-wrap { padding: 3.5rem .75rem 8vh; } .brand-logo { width: 96px; margin-bottom: .6rem; } .brand-logo .bi { font-size: 3rem; }
            .brand h1 { font-size: 1.5rem; } .brand .tag { letter-spacing: .12em; font-size: .7rem; margin-bottom: 0; }
            .auth-card { padding: 1.5rem 1.25rem 1rem; border-radius: 22px; } .auth-card h2 { font-size: 1.45rem; } .auth-card .icon-tile { width: 46px; height: 46px; font-size: 1.4rem; }
            .panels { height: 18vh; } .status-pill { top: 10px; right: 10px; font-size: .75rem; } }
        @media (prefers-reduced-motion: reduce) { .sun-rays { animation: none; } }
    </style>
    <?= $this->renderSection('pageStyles') ?>
</head>
<body>
    <div class="sun-rays"></div><div class="sun-disc"></div><div class="panels"></div>
    <div class="status-pill"><i class="bi bi-circle-fill"></i> ERP online</div>
    <div class="auth-wrap">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6 brand">
                    <div class="brand-logo"><?php if (setting('Solar.logoPath')): ?><img src="<?= site_url('branding/logo') ?>" alt="<?= esc($company) ?>"><?php else: ?><i class="bi bi-sun-fill"></i><?php endif ?></div>
                    <h1><?= esc($company) ?></h1>
                    <div class="tag"><?= esc($tagline) ?></div>
                    <p>Complete solar project management, from enquiry to subsidy.</p>
                </div>
                <div class="col-lg-6"><?= $this->renderSection('main') ?></div>
            </div>
        </div>
    </div>
    <?= $this->renderSection('pageScripts') ?>
</body>
</html>
