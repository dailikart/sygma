<?= $this->extend('owner/layout') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3">Owner logins</h1>
<div class="row g-3">
<div class="col-lg-6"><div class="card"><div class="card-body">
    <h2 class="h6">Change my password</h2>
    <form method="post" action="<?= site_url('owner/account') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="password">
        <input type="password" name="current" class="form-control mb-2" placeholder="Current password" required autocomplete="current-password">
        <input type="password" name="new" class="form-control mb-2" placeholder="New password (min 10)" required minlength="10" autocomplete="new-password">
        <input type="password" name="confirm" class="form-control mb-2" placeholder="New password again" required autocomplete="new-password">
        <button class="btn btn-primary btn-sm">Change password</button></form>
</div></div></div>
<div class="col-lg-6"><div class="card"><div class="card-body">
    <h2 class="h6">People who can open this panel</h2>
    <ul class="small"><?php foreach ($owners as $o): ?><li><?= esc($o['name']) ?> · <?= esc($o['email']) ?> <span class="text-muted">· last sign-in <?= $o['last_login_at'] ? date('d-m-Y H:i', strtotime($o['last_login_at'])) : 'never' ?></span></li><?php endforeach ?></ul>
    <form method="post" action="<?= site_url('owner/account') ?>" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="Name"></div>
        <div class="col-md-8"><input type="email" name="email" class="form-control form-control-sm" placeholder="Email" required></div>
        <div class="col-md-8"><input type="password" name="password" class="form-control form-control-sm" placeholder="Password (min 10)" required minlength="10" autocomplete="new-password"></div>
        <div class="col-md-4"><button class="btn btn-sm btn-outline-primary w-100">Add owner login</button></div></form>
</div></div></div>
</div>
<?= $this->endSection() ?>
