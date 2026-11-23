<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Settings<?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/settings') ?>" style="max-width:900px" autocomplete="off" enctype="multipart/form-data">
<?= csrf_field() ?>
<?php foreach ($fields as $group => $items): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold"><?= esc($group) ?></div><div class="card-body row g-3">
        <?php foreach ($items as $key => $def): [$label, $type] = is_array($def) ? $def : [$def, 'text']; $val = setting('Solar.' . $key); ?>
            <?php if ($type === 'select'): ?>
                <div class="col-md-6"><label class="form-label small"><?= esc($label) ?></label>
                    <select name="<?= $key ?>" class="form-select"><?= select_options($def[2], (string) $val) ?></select></div>
                <?php if ($key === 'theme'): ?><div class="col-md-6 d-flex align-items-end gap-1 flex-wrap">
                    <?php foreach (['' => '#1f2937', 'ocean' => '#0c4a6e', 'forest' => '#14532d', 'sunset' => '#ea580c', 'purple' => '#7c3aed', 'crimson' => '#e11d48', 'teal' => '#0d9488', 'solar' => '#d97706'] as $t => $c): ?>
                        <span title="<?= esc($def[2][$t]) ?>" style="width:26px;height:26px;border-radius:50%;background:<?= $c ?>;display:inline-block;border:2px solid #fff;box-shadow:0 0 0 1px #ccc"></span>
                    <?php endforeach ?></div><?php endif ?>
            <?php elseif ($type === 'textarea'): ?>
                <div class="col-12"><label class="form-label small"><?= esc($label) ?></label>
                    <textarea name="<?= $key ?>" class="form-control" rows="4"><?= esc((string) $val) ?></textarea></div>
            <?php elseif ($type === 'bool'): ?>
                <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="<?= $key ?>" value="0">
                    <input class="form-check-input" type="checkbox" name="<?= $key ?>" value="1" id="s_<?= $key ?>" <?= $val ? 'checked' : '' ?>>
                    <label class="form-check-label" for="s_<?= $key ?>"><?= esc($label) ?></label></div></div>
            <?php else: ?>
                <div class="col-md-6"><label class="form-label small"><?= esc($label) ?></label>
                    <input type="<?= $type ?>" name="<?= $key ?>" class="form-control" value="<?= $type === 'password' ? '' : esc((string) $val) ?>"
                        <?= $type === 'password' ? 'placeholder="' . ($val ? '•••••• saved – leave empty to keep' : 'Not set') . '"' : '' ?>></div>
            <?php endif ?>
        <?php endforeach ?>
        <?php if ($group === 'Appearance'): ?>
            <?php foreach (['logo' => ['logoPath', 'Company logo (PNG/JPG) – shown on the menu, login page, invoices and receipts'], 'signature' => ['signaturePath', 'Authorised signature (PNG with transparent background works best) – printed on receipts, vouchers and invoices'], 'seal' => ['sealPath', 'Company seal / stamp (PNG) – printed next to the signature on payment receipts']] as $f => [$k, $lbl]): ?>
                <div class="col-md-6"><label class="form-label small"><?= esc($lbl) ?></label>
                    <?php if (setting('Solar.' . $k)): ?><div class="mb-1"><img src="<?= site_url('branding/' . $f) ?>" style="max-height:60px;max-width:220px" class="border rounded p-1 bg-white">
                        <label class="small ms-2"><input type="checkbox" name="remove_<?= $f ?>" value="1" class="form-check-input"> remove</label></div><?php endif ?>
                    <input type="file" name="<?= $f ?>" accept="image/png,image/jpeg" class="form-control"></div>
            <?php endforeach ?>
        <?php elseif ($group === 'Tally ledger names'): ?>
            <div class="col-12 small text-muted">These names must match the ledgers in your Tally company exactly. Customer, supplier, bank/cash, income-head and expense-head ledgers use the names entered in those masters.</div>
        <?php elseif (str_starts_with($group, 'Email')): ?>
            <div class="col-12 small text-muted" id="email">Save the settings first, then send a test email. Invoice and receipt emails go only when you tick "Email to customer" on the invoice / payment.</div>
        <?php elseif (str_starts_with($group, 'WhatsApp')): ?>
            <div class="col-12 small text-muted">Same provider as the school ERP. Map approved templates to events in <a href="<?= site_url('admin/whatsapp-templates') ?>">WhatsApp Templates</a>. Messages are sent by the queue worker: <code>php spark queue:work whatsapp</code>.</div>
        <?php endif ?>
    </div></div>
<?php endforeach ?>
<button class="btn btn-primary">Save settings</button>
</form>
<?php if (\App\Libraries\Features::on('leads.indiamart')): ?>
<div class="card card-body mt-3" style="max-width:900px" id="indiamart">
    <label class="form-label fw-semibold"><i class="bi bi-cloud-download"></i> IndiaMART leads</label>
    <p class="small mb-2">Two ways to bring IndiaMART enquiries into <strong>Leads</strong> (use either or both; duplicates are skipped):</p>
    <ol class="small mb-2">
        <li><strong>Pull (CRM key):</strong> paste the key above and switch on import, then click <em>Fetch now</em>, or let the server do it automatically with this cron job (every 10 minutes):<br><code>/usr/bin/php <?= esc(ROOTPATH) ?>spark indiamart:pull</code></li>
        <li><strong>Push (instant):</strong> in IndiaMART Lead Manager → CRM integration → Push API, give this address:<br>
            <div class="input-group input-group-sm mt-1" style="max-width:700px"><input class="form-control" readonly value="<?= esc(\App\Libraries\IndiaMart::pushUrl()) ?>" id="imPush" onclick="this.select()"><button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('imPush').value)">Copy</button></div></li>
    </ol>
    <div class="small text-muted mb-2">Last result: <?= esc((string) setting('Solar.indiamartLastResult') ?: 'nothing imported yet') ?></div>
    <form method="post" action="<?= site_url('admin/indiamart/pull') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Fetch now</button></form>
</div>
<?php endif ?>
<?php $copy = static fn (string $id, string $val) => '<div class="input-group input-group-sm mt-1" style="max-width:700px"><input class="form-control" readonly value="' . esc($val) . '" id="' . $id . '" onclick="this.select()"><button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById(\'' . $id . '\').value)">Copy</button></div>'; ?>
<?php if (\App\Libraries\Features::on('leads.meta') || \App\Libraries\Features::on('leads.google')): ?>
<div class="card card-body mt-3" style="max-width:900px" id="meta-google">
<?php if (\App\Libraries\Features::on('leads.meta')): ?>
    <label class="form-label fw-semibold"><i class="bi bi-facebook"></i> Facebook / Instagram Lead Ads</label>
    <ol class="small mb-2">
        <li>In <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">Meta for Developers</a> create an app (type Business), add <em>Webhooks</em> → <em>Page</em> → subscribe to <strong>leadgen</strong> with:
            <div class="mt-1">Callback URL</div><?= $copy('metaUrl', site_url('webhooks/meta')) ?>
            <div class="mt-1">Verify token</div><?= $copy('metaVerify', \App\Libraries\LeadInbox::secret('metaVerifyToken')) ?></li>
        <li>Connect your Facebook Page to the app and generate a <strong>long-lived Page access token</strong> with <code>leads_retrieval</code> and <code>pages_manage_metadata</code> permissions; paste it above with the app secret, tick <em>Import</em>, Save.</li>
        <li>Test with Meta's <a href="https://developers.facebook.com/tools/lead-ads-testing" target="_blank" rel="noopener">Lead Ads Testing Tool</a>: the test lead appears in Leads (source Facebook / Instagram).</li>
    </ol>
    <div class="small text-muted mb-3">Last result: <?= esc((string) setting('Solar.metaLastResult') ?: 'nothing received yet') ?></div>
<?php endif ?>
<?php if (\App\Libraries\Features::on('leads.google')): ?>
    <label class="form-label fw-semibold"><i class="bi bi-google"></i> Google Ads lead forms</label>
    <ol class="small mb-2">
        <li>Tick <em>Import Google Ads lead-form leads</em> above and Save.</li>
        <li>In Google Ads → your lead form asset → <em>Lead delivery</em> → <em>Webhook integration</em>, paste:
            <div class="mt-1">Webhook URL</div><?= $copy('gUrl', site_url('webhooks/google')) ?>
            <div class="mt-1">Key</div><?= $copy('gKey', \App\Libraries\LeadInbox::secret('googleKey')) ?></li>
        <li>Click <em>Send test data</em> in Google Ads: a "(Google test lead)" appears in Leads (source Google Ads).</li>
    </ol>
    <div class="small text-muted">Last result: <?= esc((string) setting('Solar.googleLastResult') ?: 'nothing received yet') ?></div>
<?php endif ?>
</div>
<?php endif ?>
<form method="post" action="<?= site_url('admin/settings/test-email') ?>" class="card card-body mt-3" style="max-width:900px"><?= csrf_field() ?>
    <label class="form-label fw-semibold">Send a test email</label>
    <div class="d-flex gap-2"><input type="email" name="test_to" class="form-control" placeholder="you@example.com" required value="<?= esc((string) auth()->user()->email) ?>">
        <button class="btn btn-outline-primary text-nowrap"><i class="bi bi-send"></i> Send test</button></div>
</form>
<script>
// Pick the matching encryption when a standard SMTP port is typed.
(function () {
    const port = document.querySelector('[name="smtpPort"]'), crypto = document.querySelector('[name="smtpCrypto"]');
    if (!port || !crypto) return;
    port.addEventListener('input', function () { if (this.value === '465') crypto.value = 'ssl'; if (this.value === '587') crypto.value = 'tls'; });
})();
</script>
<?= $this->endSection() ?>
