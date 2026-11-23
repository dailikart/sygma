<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Roles &amp; Access<?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-muted">Tick which modules each role can use. A user with several roles gets all of their permissions. Super Admin always has full access.</p>
<form method="post" action="<?= site_url('admin/roles') ?>">
<?= csrf_field() ?>
<div class="card"><div class="table-responsive" style="max-height:70vh">
<table class="table table-sm table-bordered mb-0 align-middle">
    <thead class="sticky-top"><tr><th style="min-width:280px">Permission</th>
        <?php foreach ($groups as $key => $g): ?><th class="text-center small" title="<?= esc($g['description']) ?>"><?= esc($g['title']) ?></th><?php endforeach ?>
    </tr></thead>
    <tbody>
    <?php $lastModule = null; foreach ($permissions as $perm => $label): ?>
        <?php $module = explode(':', $label)[0]; if ($module !== $lastModule): $lastModule = $module; ?>
            <tr class="table-light"><td colspan="<?= count($groups) + 1 ?>" class="fw-semibold small text-uppercase"><?= esc($module) ?></td></tr>
        <?php endif ?>
        <tr>
            <td><?= esc($label) ?> <code class="small text-muted"><?= esc($perm) ?></code></td>
            <?php foreach ($groups as $key => $g): ?>
                <td class="text-center">
                    <?php if ($key === 'superadmin'): ?><i class="bi bi-check-lg text-success"></i>
                    <?php else: ?><input type="checkbox" class="form-check-input" name="perm[<?= esc($key) ?>][<?= esc($perm) ?>]" value="1" <?= ! empty($matrix[$key][$perm]) ? 'checked' : '' ?>>
                    <?php endif ?>
                </td>
            <?php endforeach ?>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div></div>
<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary">Save access rights</button>
</div>
</form>
<form method="post" action="<?= site_url('admin/roles/reset') ?>" class="mt-2"><?= csrf_field() ?>
    <button class="btn btn-sm btn-outline-danger" data-confirm="Reset all roles to the default access rights?">Reset to defaults</button>
</form>
<?= $this->endSection() ?>
