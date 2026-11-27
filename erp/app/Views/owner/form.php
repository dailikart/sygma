<?= $this->extend('owner/layout') ?>
<?= $this->section('content') ?>
<?php
$new  = $c === null;
$v    = static fn (string $k, $d = '') => esc((string) old($k, $c[$k] ?? $d));
$offK = $new ? (array) old('off_keys', []) : $off;
$isOn = static fn (string $k) => $new ? (old('on') === null || isset(old('on')[$k])) : ! in_array($k, $offK, true);
$states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Andaman and Nicobar Islands','Chandigarh','Dadra and Nagar Haveli and Daman and Diu','Delhi','Jammu and Kashmir','Ladakh','Lakshadweep','Puducherry'];
$state = \App\Controllers\Owner::class;
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= site_url('owner') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h1 class="h4 mb-0 me-auto"><?= $new ? 'New company' : esc($c['name']) ?></h1>
    <?php if (! $new): $st = \App\Controllers\Owner::stateOf($c); ?>
        <span class="badge text-bg-<?= ['active' => 'success', 'trial' => 'info', 'suspended' => 'danger', 'expired' => 'secondary', 'setup' => 'warning'][$st] ?? 'light' ?> fs-6"><?= ucfirst($st) ?></span>
        <a href="<?= (service('request')->getUri()->getScheme()) . '://' . esc($c['domain']) . (($p = service('request')->getUri()->getPort()) && ! in_array($p, [80, 443], true) ? ':' . $p : '') . rtrim((string) parse_url(base_url(), PHP_URL_PATH), '/') . '/login' ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Open login page</a>
        <form method="post" action="<?= site_url('owner/companies/' . $c['id'] . '/support') ?>" target="_blank"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary" data-confirm="Open this company's ERP as its Super Admin? This is recorded in their user log."><i class="bi bi-person-badge"></i> Open as Super Admin</button></form>
    <?php endif ?>
</div>
<?php if (! empty($error)): ?><div class="alert alert-danger">Could not read this company's data: <?= esc($error) ?></div><?php endif ?>
<div class="row g-3">
<div class="col-lg-8">
<form method="post" action="<?= site_url($new ? 'owner/companies' : 'owner/companies/' . $c['id']) ?>" id="companyForm"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-3">Company</h2>
    <div class="row g-2">
        <div class="col-md-8"><label class="form-label small">Company name *</label><input name="name" class="form-control" required value="<?= $v('name') ?>"></div>
        <div class="col-md-4"><label class="form-label small">Company code *</label>
            <?php if ($new): ?><input name="code" id="code" class="form-control" required pattern="[a-z][a-z0-9]{1,11}" maxlength="12" value="<?= $v('code') ?>" placeholder="e.g. sunpower"><div class="form-text">2–12 small letters / digits. Cannot change later.</div>
            <?php else: ?><input class="form-control" value="<?= esc($c['code']) ?>" disabled><?php endif ?></div>
        <div class="col-md-4"><label class="form-label small">Contact person</label><input name="contact_name" class="form-control" value="<?= $v('contact_name') ?>"></div>
        <div class="col-md-4"><label class="form-label small">Phone</label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
        <div class="col-md-4"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
        <div class="col-md-4"><label class="form-label small">GSTIN</label><input name="gstin" class="form-control text-uppercase" maxlength="15" value="<?= $v('gstin') ?>"></div>
        <div class="col-md-4"><label class="form-label small">State</label><select name="state" class="form-select"><option value="">—</option><?php foreach ($states as $s): ?><option <?= old('state', $c['state'] ?? 'Tamil Nadu') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach ?></select></div>
    </div>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-3">Web address</h2>
    <div class="row g-2">
        <div class="col-md-6"><label class="form-label small">Address *</label><input name="domain" id="domain" class="form-control" required value="<?= $v('domain') ?>" placeholder="<?= esc($base ? 'code.' . $base : 'erp.client.com') ?>">
            <div class="form-text">Without https://. In Hostinger, add this sub-domain pointing to the same ERP folder.</div></div>
        <div class="col-md-6"><label class="form-label small">Second address (optional)</label><input name="alt_domain" class="form-control" value="<?= $v('alt_domain') ?>" placeholder="erp.theirowndomain.com"><div class="form-text">e.g. the client's own domain.</div></div>
    </div>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-3">Plan</h2>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label small">Status</label><select name="status" class="form-select">
            <?php foreach (['active' => 'Active', 'trial' => 'Trial'] + ($new ? [] : ['suspended' => 'Suspended (on hold)']) as $k => $l): ?><option value="<?= $k ?>" <?= old('status', $c['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
        <div class="col-md-4"><label class="form-label small">Plan name</label><input name="plan" class="form-control" value="<?= $v('plan') ?>" placeholder="e.g. Standard"></div>
        <div class="col-md-4"><label class="form-label small">Paid until</label><input type="date" name="expires_on" class="form-control" value="<?= $v('expires_on') ?>"><div class="form-text">Blank = no end date. After it, the company sees "Subscription ended".</div></div>
        <div class="col-md-4"><label class="form-label small">Max active users</label><input type="number" min="1" name="max_users" class="form-control" value="<?= $v('max_users') ?>" placeholder="no limit"></div>
        <?php if (! $new): ?><div class="col-md-8"><label class="form-label small">Message shown when suspended</label><input name="suspend_note" class="form-control" value="<?= $v('suspend_note') ?>" placeholder="This account is on hold. Please contact Sygma Solar Suite."></div><?php endif ?>
        <div class="col-12"><label class="form-label small">Notes (only you see these)</label><textarea name="notes" rows="2" class="form-control"><?= $v('notes') ?></textarea></div>
    </div>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-1">Modules &amp; options</h2>
    <p class="small text-muted">Switched-off modules disappear from the company's menu, dashboard and Roles &amp; Access. Their data is kept.</p>
    <?php foreach ($catalogue as $key => $m): ?>
        <div class="border-bottom py-2">
            <div class="form-check form-switch">
                <?php if (! empty($m['core'])): ?><input class="form-check-input" type="checkbox" checked disabled><?php else: ?><input class="form-check-input" type="checkbox" name="on[<?= esc($key) ?>]" value="1" id="m-<?= esc($key) ?>" <?= $isOn($key) ? 'checked' : '' ?>><?php endif ?>
                <label class="form-check-label" for="m-<?= esc($key) ?>"><b><?= esc($m['title']) ?></b><?= ! empty($m['core']) ? ' <span class="badge text-bg-secondary">Core</span>' : '' ?><?= ! empty($m['requires']) ? ' <span class="small text-muted">needs ' . esc(implode(', ', array_map(\App\Libraries\Features::title(...), $m['requires']))) . '</span>' : '' ?>
                    <span class="d-block small text-muted"><?= esc($m['description']) ?></span></label>
            </div>
            <?php foreach ($m['options'] ?? [] as $ok => $o): $full = $key . '.' . $ok; ?>
                <div class="form-check form-switch ms-4"><input class="form-check-input" type="checkbox" name="on[<?= esc($full) ?>]" value="1" id="m-<?= esc($full) ?>" <?= $isOn($full) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="m-<?= esc($full) ?>"><?= esc($o['title']) ?> <span class="text-muted">· <?= esc($o['description']) ?></span></label></div>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
</div></div>
<?php if ($new): ?>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-3">First Super Admin login (for the client)</h2>
    <div class="row g-2">
        <div class="col-md-6"><label class="form-label small">Email *</label><input type="email" name="admin_email" class="form-control" required value="<?= esc((string) old('admin_email')) ?>"></div>
        <div class="col-md-6"><label class="form-label small">Password * (min 8)</label><div class="input-group"><input name="admin_password" id="pw" class="form-control font-monospace" required minlength="8" value="<?= esc((string) old('admin_password')) ?>"><button type="button" class="btn btn-outline-secondary" onclick="genPw()">Generate</button></div><div class="form-text">Give it to the client; they can change it in their profile.</div></div>
    </div>
</div></div>
<?php endif ?>
<button class="btn btn-primary"><?= $new ? '<i class="bi bi-rocket-takeoff"></i> Create company' : 'Save changes' ?></button>
<?php if ($new): ?><span class="small text-muted ms-2">Creates all the company's tables; takes a few seconds.</span><?php endif ?>
</form>
</div>
<?php if (! $new): ?>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6">Usage</h2>
        <table class="table table-sm mb-0 small">
            <?php foreach (['users' => 'Active users', 'leads' => 'Leads', 'customers' => 'Customers', 'quotations' => 'Quotations', 'invoices' => 'Invoices', 'projects' => 'Projects'] as $k => $l): ?>
                <tr><td><?= $l ?></td><td class="text-end"><?= isset($usage[$k]) ? number_format((int) $usage[$k]) : '—' ?><?= $k === 'users' && $c['max_users'] ? ' / ' . (int) $c['max_users'] : '' ?></td></tr>
            <?php endforeach ?>
            <tr><td>Sales (invoiced)</td><td class="text-end">₹<?= isset($usage['sales']) ? number_format((float) $usage['sales']) : '—' ?></td></tr>
            <tr><td>Last used</td><td class="text-end"><?= $c['last_seen_at'] ? date('d-m-Y H:i', strtotime($c['last_seen_at'])) : 'never' ?></td></tr>
            <tr><td>Created</td><td class="text-end"><?= date('d-m-Y', strtotime($c['created_at'])) ?></td></tr>
            <tr><td>Tables</td><td class="text-end"><code><?= esc($c['db_prefix']) ?>*</code></td></tr>
        </table>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6">Database</h2>
        <?php if ($pending): ?><p class="small text-warning-emphasis mb-2"><?= (int) $pending ?> update(s) waiting.</p><?php elseif ($pending === 0): ?><p class="small text-success mb-2"><i class="bi bi-check-circle"></i> Up to date.</p><?php endif ?>
        <form method="post" action="<?= site_url('owner/companies/' . $c['id'] . '/migrate') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Run database updates</button></form>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6">Super Admin login</h2>
        <p class="small text-muted mb-2">Now: <b><?= esc((string) ($admin ?? '—')) ?></b>. Set a new email / password if the client is locked out.</p>
        <form method="post" action="<?= site_url('owner/companies/' . $c['id'] . '/admin') ?>"><?= csrf_field() ?>
            <input type="email" name="admin_email" class="form-control form-control-sm mb-2" required value="<?= esc((string) ($admin ?? '')) ?>">
            <div class="input-group input-group-sm mb-2"><input name="admin_password" id="pw" class="form-control font-monospace" required minlength="8" placeholder="New password"><button type="button" class="btn btn-outline-secondary" onclick="genPw()">Generate</button></div>
            <button class="btn btn-sm btn-outline-primary" data-confirm="Change this company's Super Admin login?">Set login</button>
        </form>
    </div></div>
    <div class="card mb-3 border border-danger-subtle"><div class="card-body">
        <h2 class="h6 text-danger">Delete company</h2>
        <p class="small text-muted mb-2">Deletes all its data and files for ever. Suspend it first; type the code <b><?= esc($c['code']) ?></b> to confirm.</p>
        <form method="post" action="<?= site_url('owner/companies/' . $c['id'] . '/delete') ?>" class="d-flex gap-2"><?= csrf_field() ?>
            <input name="confirm" class="form-control form-control-sm" placeholder="Type the code" autocomplete="off"><button class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= esc($c['name']) ?> and ALL its data? This cannot be undone.">Delete</button></form>
    </div></div>
    <div class="card"><div class="card-body">
        <h2 class="h6">History</h2>
        <ul class="list-unstyled small mb-0">
            <?php foreach ($log as $l): ?><li class="mb-1"><span class="text-muted"><?= date('d-m-Y H:i', strtotime($l['created_at'])) ?></span> <?= esc(str_replace('_', ' ', $l['action'])) ?><?= $l['action'] === 'support_login' ? '' : ($l['detail'] ? ': ' . esc($l['detail']) : '') ?><?= $l['owner'] ? ' <span class="text-muted">· ' . esc($l['owner']) . '</span>' : '' ?></li><?php endforeach ?>
            <?php if (! $log): ?><li class="text-muted">Nothing yet.</li><?php endif ?>
        </ul>
    </div></div>
</div>
<?php endif ?>
</div>
<script>
function genPw() { const c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789#@%'; let p = ''; const r = crypto.getRandomValues(new Uint32Array(12)); r.forEach(x => p += c[x % c.length]); document.getElementById('pw').value = p; }
<?php if ($new && $base): ?>
const code = document.getElementById('code'), dom = document.getElementById('domain'); let touched = dom.value !== '';
dom.addEventListener('input', () => touched = true);
code.addEventListener('input', () => { code.value = code.value.toLowerCase().replace(/[^a-z0-9]/g, ''); if (! touched) dom.value = code.value ? code.value + '.<?= esc($base, 'js') ?>' : ''; });
<?php endif ?>
</script>
<?= $this->endSection() ?>
