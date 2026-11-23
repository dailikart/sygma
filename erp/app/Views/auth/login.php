<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Sign in<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php $company = (string) setting('Solar.companyName'); ?>
<div class="auth-card">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="icon-tile"><i class="bi bi-brightness-high"></i></div>
        <div><h2>Welcome Back</h2><div class="sub">Sign in to <?= esc($company) ?></div></div>
    </div>

    <?php if (session('error') !== null) : ?>
        <div class="alert alert-danger py-2" role="alert"><?= esc(session('error')) ?></div>
    <?php elseif (session('errors') !== null) : ?>
        <div class="alert alert-danger py-2" role="alert"><?= is_array(session('errors')) ? implode('<br>', array_map('esc', session('errors'))) : esc(session('errors')) ?></div>
    <?php endif ?>
    <?php if (session('message') !== null) : ?>
        <div class="alert alert-success py-2" role="alert"><?= esc(session('message')) ?></div>
    <?php endif ?>

    <?php if (\App\Filters\DemoGuard::enabled()) : ?>
        <div class="alert alert-warning py-2 small" role="note">
            <b><i class="bi bi-stars"></i> Live demo</b> · sample data, reset nightly<br>
            Email <code>demo@sygmasolar.com</code> · Password <code>Demo@12345</code>
            <button type="button" class="btn btn-sm btn-dark w-100 mt-2" id="demo-fill">Use demo login</button>
        </div>
    <?php endif ?>

    <form action="<?= url_to('login') ?>" method="post" id="login-form">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <div class="auth-input"><i class="bi bi-person-fill"></i>
                <input type="email" class="form-control" id="email" name="email" inputmode="email" autocomplete="email" placeholder="you@company.com" value="<?= old('email') ?>" required autofocus></div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <div class="auth-input"><i class="bi bi-lock-fill"></i>
                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" placeholder="Password" required>
                <button type="button" class="toggle-pw" aria-label="Show password" onclick="const p=document.getElementById('password'),s=p.type==='password';p.type=s?'text':'password';this.firstElementChild.className=s?'bi bi-eye-slash-fill':'bi bi-eye-fill';"><i class="bi bi-eye-fill"></i></button></div>
        </div>
        <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
            <div class="form-check mb-3">
                <input type="checkbox" name="remember" class="form-check-input" id="remember" <?= old('remember') ? 'checked' : '' ?>>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
        <?php endif ?>
        <button type="submit" class="btn btn-sun w-100">Sign In</button>
        <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
            <p class="text-center small mt-3 mb-0">Forgot your password? <a href="<?= url_to('magic-link') ?>">Get a login link by email</a></p>
        <?php endif ?>
    </form>

    <div class="text-center small mt-4" style="color:#5b7c80"><i class="bi bi-shield-lock-fill text-danger"></i> Secure internal access</div>
    <div class="auth-foot">© <?= date('Y') ?> <?= esc($company) ?> · Sygma Solar Suite</div>
</div>
<?= $this->endSection() ?>

<?php if (\App\Filters\DemoGuard::enabled()) : ?>
<?= $this->section('pageScripts') ?>
<script>
(function () {
    function fill(submit) {
        document.getElementById('email').value = 'demo@sygmasolar.com';
        document.getElementById('password').value = 'Demo@12345';
        if (submit) document.getElementById('login-form').submit();
    }
    document.getElementById('demo-fill').addEventListener('click', function () { fill(true); });
    // Arriving from the website's "Live demo" button (…/login?demo=1): fill the form.
    if (new URLSearchParams(location.search).has('demo')) fill(false);
})();
</script>
<?= $this->endSection() ?>
<?php endif ?>
