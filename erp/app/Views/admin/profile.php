<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Change Password<?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/profile') ?>" class="card card-body" style="max-width:420px">
    <?= csrf_field() ?>
    <p class="mb-3"><strong><?= esc($user->username) ?></strong><br><span class="text-muted"><?= esc($user->email) ?></span></p>
    <label class="form-label">Current password</label><input type="password" name="current" class="form-control mb-2" required>
    <label class="form-label">New password</label><input type="password" name="password" class="form-control mb-2" minlength="8" required>
    <label class="form-label">Confirm new password</label><input type="password" name="confirm" class="form-control mb-3" required>
    <button class="btn btn-primary">Change password</button>
</form>
<?= $this->endSection() ?>
