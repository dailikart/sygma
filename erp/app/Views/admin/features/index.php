<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Modules &amp; Options<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$isOn  = static fn (string $k) => ! in_array($k, $saved, true); // switched on by you
$why   = static fn (string $k) => \App\Libraries\Features::offBecause($k);
$badge = static fn (string $k) => ($w = $why($k)) ? ' <span class="badge text-bg-warning fw-normal">off: ' . esc($w) . '</span>' : '';
?>
<p class="text-muted small" style="max-width:900px">Choose what this company has. A switched-off module disappears from the menu, the dashboard, Settings and
    Roles &amp; Access, and its pages cannot be opened. <strong>Data is kept</strong>: switching it back on brings everything back.
    Modules marked <span class="badge text-bg-secondary">Core</span> are always on; their options can still be switched off.</p>
<form method="post" action="<?= site_url('admin/features') ?>" style="max-width:900px">
<?= csrf_field() ?>
<?php foreach ($catalogue as $key => $m): ?>
    <div class="card mb-2">
        <div class="card-body py-2">
            <div class="form-check form-switch d-flex align-items-start gap-2">
                <?php if (! empty($m['core'])): ?>
                    <input class="form-check-input mt-1" type="checkbox" checked disabled>
                <?php else: ?>
                    <input class="form-check-input mt-1 mod" type="checkbox" name="on[<?= esc($key) ?>]" id="f-<?= esc($key) ?>" value="1" <?= $isOn($key) ? 'checked' : '' ?>>
                <?php endif ?>
                <label class="form-check-label" for="f-<?= esc($key) ?>">
                    <span class="fw-semibold"><?= esc($m['title']) ?></span>
                    <?php if (! empty($m['core'])): ?><span class="badge text-bg-secondary ms-1">Core</span><?php endif ?>
                    <?= $badge($key) ?>
                    <?php if (! empty($m['requires'])): ?><span class="small text-muted ms-1">needs <?= esc(implode(', ', array_map(\App\Libraries\Features::title(...), $m['requires']))) ?></span><?php endif ?>
                    <div class="small text-muted"><?= esc($m['description']) ?></div>
                </label>
            </div>
            <?php if (! empty($m['options'])): ?>
            <div class="ms-5 mt-2 border-start ps-3 opts" data-mod="<?= esc($key) ?>">
                <?php foreach ($m['options'] as $ok => $o): $full = $key . '.' . $ok; ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="on[<?= esc($full) ?>]" id="f-<?= esc($full) ?>" value="1" <?= $isOn($full) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="f-<?= esc($full) ?>"><?= esc($o['title']) ?><?= $isOn($full) && \App\Libraries\Features::on($key) ? $badge($full) : '' ?>
                            <?php if (! empty($o['requires'])): ?><span class="text-muted">· needs <?= esc(implode(', ', array_map(\App\Libraries\Features::title(...), $o['requires']))) ?></span><?php endif ?>
                            <span class="text-muted d-block"><?= esc($o['description']) ?></span></label>
                    </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>
    </div>
<?php endforeach ?>
<button class="btn btn-primary mt-2">Save modules</button>
</form>
<script>
// Options are only usable while their module is on.
document.querySelectorAll('.mod').forEach(cb => {
    const box = document.querySelector('.opts[data-mod="' + cb.name.slice(3, -1) + '"]');
    const sync = () => { if (box) box.style.opacity = cb.checked ? '' : '.45'; };
    cb.addEventListener('change', sync); sync();
});
</script>
<?= $this->endSection() ?>
