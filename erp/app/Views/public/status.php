<?php
$company = (string) setting('Solar.companyName');
$phone   = (string) setting('Solar.companyPhone');
$wa      = preg_replace('/\D/', '', $phone);
$wa      = strlen($wa) === 10 ? '91' . $wa : $wa;
$cfg     = config('Workflow');
$done    = count(array_filter($stages, static fn ($s) => $s['status'] === 'done'));
$pct     = $stages ? (int) round($done / count($stages) * 100) : 0;
$current = null;
foreach ($stages as $s) { if ($s['status'] === 'active') { $current = $s; break; } }
$friendly = [
    'survey'       => 'Our engineer visits your site and checks the roof.',
    'dispatch'     => 'Your panels, inverter and structure are being prepared and sent.',
    'delivery'     => 'Material is delivered at your site.',
    'installation' => 'Our team installs the solar system.',
    'dcr'          => 'Installation details are submitted on the subsidy portal.',
    'eb_process'   => 'EB application, inspection and net meter installation.',
    'subsidy'      => 'The government subsidy is credited to your bank account.',
];
$due = $invoice ? round((float) $invoice['total'] - (float) $invoice['amount_paid'], 2) : 0;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Your solar project <?= esc($p['project_no']) ?> | <?= esc($company) ?></title>
    <?= view('partials_favicon') ?>
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <style>
        :root { --teal: #0d5c66; --teal2: #12808c; --amber: #c98a06; }
        body { background: #fdeaa6 linear-gradient(170deg, #fff8dc 0%, #fdeaa6 400px, #fbe08a 100%); color: #134e55; min-height: 100vh; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 1rem .9rem 3rem; }
        .brand { display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; }
        .brand img { height: 46px; width: auto; border-radius: 6px; background: #fff; }
        .brand .logo-fallback { width: 46px; height: 46px; border-radius: 12px; background: linear-gradient(145deg, #fde68a, #f59e0b); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; }
        .brand h1 { font-size: 1.15rem; margin: 0; font-weight: 800; color: var(--teal); }
        .cardx { background: rgba(255, 255, 255, .95); border-radius: 20px; box-shadow: 0 12px 34px rgba(120, 80, 0, .12); padding: 1.1rem 1.2rem; margin-bottom: 1rem; }
        .cardx h2 { font-size: 1rem; font-weight: 700; color: var(--teal); margin-bottom: .7rem; }
        .hero h3 { font-weight: 800; color: var(--teal); font-size: 1.35rem; margin: 0; }
        .progress { height: 10px; border-radius: 99px; background: #f1ead2; }
        .progress-bar { background: linear-gradient(90deg, #f59e0b, var(--teal2)); }
        .tl { list-style: none; padding: 0; margin: 0; position: relative; }
        .tl li { position: relative; padding: 0 0 1rem 2.6rem; }
        .tl li::before { content: ""; position: absolute; left: .95rem; top: 1.9rem; bottom: 0; width: 2px; background: #e8dfc0; }
        .tl li:last-child::before { display: none; }
        .tl .dot { position: absolute; left: 0; top: 0; width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .95rem; background: #f1ead2; color: #a08a4a; }
        .tl li.done .dot { background: #16a34a; color: #fff; }
        .tl li.active .dot { background: #f59e0b; color: #fff; box-shadow: 0 0 0 5px rgba(245, 158, 11, .25); }
        .tl li.done::before { background: #86d8a3; }
        .tl .t { font-weight: 700; } .tl li.pending .t { color: #8a8a7a; }
        .tl .d { font-size: .85rem; color: #5b7c80; }
        .kv { display: flex; justify-content: space-between; padding: .35rem 0; border-bottom: 1px dashed #eee3c2; font-size: .95rem; }
        .kv:last-child { border-bottom: 0; }
        .btn-teal { background: var(--teal); color: #fff; border-radius: 12px; } .btn-teal:hover { color: #fff; background: var(--teal2); }
        .small-note { font-size: .8rem; color: #6b7d80; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <?php if (setting('Solar.logoPath')): ?><img src="<?= site_url('branding/logo') ?>" alt=""><?php else: ?><div class="logo-fallback"><i class="bi bi-sun-fill"></i></div><?php endif ?>
        <div><h1><?= esc($company) ?></h1><div class="small-note">Your solar project status</div></div>
    </div>

    <div class="cardx hero">
        <div class="small-note">Hello <?= esc(explode(' ', trim((string) $customer['name']))[0]) ?>,</div>
        <h3><?= qty($p['capacity_kw']) ?> kW <?= esc($type) ?> solar system</h3>
        <div class="small-note mb-2"><?= esc($p['project_no']) ?><?= $customer['city'] ? ' · ' . esc($customer['city']) : '' ?></div>
        <?php if ($p['status'] === 'completed'): ?>
            <div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle-fill"></i> Your project is complete<?= $p['commissioned_on'] ? ' – commissioned on ' . fdate($p['commissioned_on']) : '' ?>. Thank you!</div>
        <?php elseif ($p['status'] === 'on_hold'): ?>
            <div class="alert alert-warning py-2 mb-2"><i class="bi bi-pause-circle"></i> Your project is on hold. Please contact us for details.</div>
        <?php elseif ($p['status'] === 'cancelled'): ?>
            <div class="alert alert-secondary py-2 mb-2">This project was cancelled.</div>
        <?php elseif ($current): ?>
            <div class="mb-2">Now: <strong><?= esc($cfg->stages[$current['stage']]['title'] ?? $current['stage']) ?></strong></div>
        <?php endif ?>
        <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
        <div class="small-note mt-1"><?= $done ?> of <?= count($stages) ?> steps done</div>
    </div>

    <div class="cardx">
        <h2><i class="bi bi-signpost-split"></i> Progress</h2>
        <ul class="tl">
        <?php foreach ($stages as $s): $st = $s['status'] === 'done' ? 'done' : ($s['status'] === 'active' ? 'active' : 'pending'); $c = $cfg->stages[$s['stage']] ?? ['title' => $s['stage'], 'icon' => 'bi-circle']; ?>
            <li class="<?= $st ?>"><span class="dot"><i class="bi <?= $st === 'done' ? 'bi-check-lg' : esc($c['icon']) ?>"></i></span>
                <div class="t"><?= esc($c['title']) ?></div>
                <div class="d"><?= $st === 'done' ? 'Completed ' . fdate($s['completed_at']) : ($st === 'active' ? 'In progress' . ($s['planned_date'] ? ' · planned ' . fdate($s['planned_date']) : '') : esc($friendly[$s['stage']] ?? '')) ?></div>
                <?php if ($s['stage'] === 'dispatch' && $dispatches): ?><div class="d"><?php foreach ($dispatches as $d): ?><?= esc($d['dispatch_no']) ?>: dispatched <?= fdate($d['dispatched_at']) ?><?= $d['delivered_at'] ? ', delivered ' . fdate($d['delivered_at']) : '' ?><br><?php endforeach ?></div><?php endif ?>
            </li>
        <?php endforeach ?>
        </ul>
    </div>

    <?php if ($invoice): ?>
    <div class="cardx">
        <h2><i class="bi bi-receipt"></i> Payments</h2>
        <div class="kv"><span>Invoice <?= esc($invoice['invoice_no']) ?></span><strong><?= money($invoice['total']) ?></strong></div>
        <?php foreach ($payments as $pay): ?><div class="kv"><span class="small-note"><?= fdate($pay['payment_date']) ?> · <?= esc($pay['receipt_no']) ?> · <?= esc(strtoupper($pay['mode'] === 'loan' ? 'bank loan' : $pay['mode'])) ?></span><span class="text-success"><?= money($pay['amount']) ?></span></div><?php endforeach ?>
        <div class="kv"><span>Paid</span><strong class="text-success"><?= money($invoice['amount_paid']) ?></strong></div>
        <div class="kv"><span><?= $due > 0 ? 'Balance to pay' : 'Balance' ?></span><strong class="<?= $due > 0 ? 'text-danger' : 'text-success' ?>"><?= $due > 0 ? money($due) : 'Fully paid' ?></strong></div>
        <?php if (eb_charges_extra($invoice) > 0): ?><div class="kv"><span>EB / meter charges (paid separately)</span><span><?= money(eb_charges_extra($invoice)) ?></span></div><?php endif ?>
        <?php if ($tranches): ?>
            <div class="small-note mt-2 mb-1">Bank loan<?= $invoice['loan_bank'] ? ' – ' . esc($invoice['loan_bank']) : '' ?>:</div>
            <?php foreach ($tranches as $t): ?><div class="kv"><span class="small-note">Disbursement <?= (int) $t['seq'] ?><?= $t['expected_date'] ? ' · expected ' . fdate($t['expected_date']) : '' ?></span><span><?= money($t['amount']) ?> <?= $t['status'] === 'received' ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="badge text-bg-light border">Pending</span>' ?></span></div><?php endforeach ?>
        <?php endif ?>
    </div>
    <?php endif ?>

    <?php if ($docs && $p['status'] !== 'completed'): $missing = array_filter($docs, static fn ($d) => ! $d['ok']); ?>
    <div class="cardx">
        <h2><i class="bi bi-folder-check"></i> Documents</h2>
        <?php if ($missing): ?><div class="alert alert-warning py-2 small">Please share the documents marked <i class="bi bi-circle"></i> with us – they are needed for the EB / subsidy application.</div><?php else: ?><div class="small-note mb-2">We have received all the documents we need. Thank you.</div><?php endif ?>
        <?php foreach ($docs as $d): ?><div class="kv"><span><?= esc($d['name']) ?></span><?= $d['ok'] ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Received</span>' : '<span class="text-warning"><i class="bi bi-circle"></i> Needed</span>' ?></div><?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if ($subsidy): $sd = $subsidy['data']; ?>
    <div class="cardx">
        <h2><i class="bi bi-bank"></i> Subsidy (PM Surya Ghar)</h2>
        <div class="kv"><span>Status</span><strong><?= esc($cfg->subsidyStatuses[$sd['status'] ?? 'not_applied'] ?? 'Not applied') ?></strong></div>
        <?php if (! empty($sd['application_no'])): ?><div class="kv"><span>Application no.</span><span><?= esc($sd['application_no']) ?></span></div><?php endif ?>
        <?php if (! empty($sd['subsidy_amount'])): ?><div class="kv"><span>Amount</span><span><?= money($sd['subsidy_amount']) ?></span></div><?php endif ?>
        <?php if (! empty($sd['received_date'])): ?><div class="kv"><span>Received on</span><span><?= fdate($sd['received_date']) ?></span></div><?php endif ?>
    </div>
    <?php endif ?>

    <?php if ($contract): ?>
    <div class="cardx">
        <h2><i class="bi bi-wrench-adjustable"></i> Maintenance (AMC)</h2>
        <div class="kv"><span>Covered until</span><strong><?= fdate($contract['end_date']) ?></strong></div>
        <?php foreach ($visits as $v): ?><div class="kv"><span>Visit <?= (int) $v['visit_no'] ?> · <?= fdate($v['due_date']) ?></span><?= $v['status'] === 'done' ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Done ' . fdate($v['done_date']) . '</span>' : ($v['due_date'] < date('Y-m-d') ? '<span class="text-warning">Due</span>' : '<span class="small-note">Scheduled</span>') ?></div><?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if (in_array($p['status'], ['active', 'completed', 'on_hold'], true) && ($p['commissioned_on'] || $tickets || ($contract))): $S = \App\Libraries\ServiceDesk::STATUSES; $C = \App\Libraries\ServiceDesk::CATEGORIES; ?>
    <div class="cardx" id="service">
        <h2><i class="bi bi-tools"></i> Service &amp; complaints</h2>
        <?php if (session('complaint_ok')): ?><div class="alert alert-success py-2 small"><?= esc(session('complaint_ok')) ?></div><?php endif ?>
        <?php if (session('complaint_error')): ?><div class="alert alert-danger py-2 small"><?= esc(session('complaint_error')) ?></div><?php endif ?>
        <?php foreach ($tickets as $tk): ?><div class="kv"><span><?= esc($tk['ticket_no']) ?> · <?= esc($C[$tk['category']] ?? '') ?><br><span class="small-note"><?= fdate($tk['created_at']) ?><?= $tk['resolution'] ? ' · ' . esc($tk['resolution']) : '' ?></span></span>
            <span><?= in_array($tk['status'], ['resolved', 'closed'], true) ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> ' . esc($S[$tk['status']]) . '</span>' : '<span class="text-warning">' . esc($S[$tk['status']]) . '</span>' ?></span></div><?php endforeach ?>
        <details class="mt-2" <?= session('complaint_error') ? 'open' : '' ?>><summary class="btn btn-sm btn-outline-danger" style="border-radius:10px">Report a problem</summary>
            <form method="post" action="<?= site_url('track/' . $p['status_token'] . '/complaint') ?>" class="mt-2"><?= csrf_field() ?>
                <select name="category" class="form-select mb-2"><?= select_options(array_diff_key($C, ['other' => 1]) + ['other' => 'Other'], 'no_generation') ?></select>
                <textarea name="description" class="form-control mb-2" rows="3" required minlength="5" maxlength="1000" placeholder="What is the problem? Since when? Any error shown on the inverter?"></textarea>
                <input name="phone" class="form-control mb-2" placeholder="Phone number to call you (optional)">
                <button class="btn btn-teal w-100">Send complaint</button>
            </form></details>
    </div>
    <?php endif ?>

    <div class="cardx text-center">
        <h2 class="mb-2">Questions? We are here to help.</h2>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <?php if ($phone): ?><a class="btn btn-teal" href="tel:<?= esc(preg_replace('/[^\d+]/', '', $phone)) ?>"><i class="bi bi-telephone-fill"></i> Call</a>
                <a class="btn btn-success" style="border-radius:12px" target="_blank" href="https://wa.me/<?= esc($wa) ?>?text=<?= rawurlencode('Hello, I have a question about my solar project ' . $p['project_no'] . '.') ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a><?php endif ?>
            <?php if (setting('Solar.companyEmail')): ?><a class="btn btn-outline-secondary" style="border-radius:12px" href="mailto:<?= esc((string) setting('Solar.companyEmail')) ?>?subject=<?= rawurlencode('Project ' . $p['project_no']) ?>"><i class="bi bi-envelope"></i> Email</a><?php endif ?>
        </div>
        <div class="small-note mt-2"><?= esc($company) ?><?= setting('Solar.companyAddress') ? ' · ' . esc((string) setting('Solar.companyAddress')) : '' ?></div>
    </div>
    <div class="text-center small-note">This page is private to you. Updated <?= date('d-m-Y h:i A') ?>.</div>
</div>
</body>
</html>
