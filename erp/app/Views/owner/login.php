<?= $this->extend('owner/layout') ?>
<?= $this->section('content') ?>
<div class="card mx-auto" style="max-width:380px"><div class="card-body p-4">
    <h1 class="h5 mb-3">Owner sign in</h1>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= esc($error) ?></div><?php endif ?>
    <form method="post" action="<?= site_url('owner/login') ?>"><?= csrf_field() ?>
        <label class="form-label small">Email</label><input type="email" name="email" class="form-control mb-2" required autofocus autocomplete="username">
        <label class="form-label small">Password</label><input type="password" name="password" class="form-control mb-3" required autocomplete="current-password">
        <button class="btn btn-primary w-100">Sign in</button>
    </form>
    <p class="small text-muted mt-3 mb-0">This is the Sygma Solar Suite control panel for the software owner. Companies sign in at their own address.</p>
</div></div>
<?= $this->endSection() ?>
