<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= $user ? 'Edit User' : 'New User' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/users/save' . ($user ? '/' . $user->id : '')) ?>" class="card card-body" style="max-width:760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Username *</label>
            <input name="username" class="form-control" required value="<?= esc(old('username', $user->username ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required value="<?= esc(old('email', $user->email ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Password <?= $user ? '(leave blank to keep)' : '*' ?></label>
            <input type="password" name="password" class="form-control" minlength="8" <?= $user ? '' : 'required' ?> autocomplete="new-password"></div>
        <div class="col-12">
            <label class="form-label">Roles * <small class="text-muted">(tick more than one if this person does several jobs)</small></label>
            <div class="row g-2">
            <?php $selected = old('roles', $userRoles) ?: []; ?>
            <?php foreach ($roles as $key => $r): ?>
                <div class="col-md-6"><div class="form-check border rounded p-2 ps-5">
                    <input class="form-check-input" type="checkbox" name="roles[]" value="<?= esc($key) ?>" id="r_<?= esc($key) ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="r_<?= esc($key) ?>"><strong><?= esc($r['title']) ?></strong><br><small class="text-muted"><?= esc($r['description']) ?></small></label>
                </div></div>
            <?php endforeach ?>
            </div>
        </div>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save</button> <a href="<?= site_url('admin/users') ?>" class="btn btn-light">Cancel</a></div>
</form>
<?= $this->endSection() ?>
