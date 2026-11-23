<?php /* Favicon: the company logo from Settings, else the default sun icon. */ $logo = (string) setting('Solar.logoPath'); ?>
<?php if ($logo !== ''): ?>
    <link rel="icon" href="<?= site_url('branding/logo') ?>?v=<?= substr(md5($logo), 0, 8) ?>">
    <link rel="apple-touch-icon" href="<?= site_url('branding/logo') ?>?v=<?= substr(md5($logo), 0, 8) ?>">
<?php else: ?>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.svg') ?>">
<?php endif ?>
