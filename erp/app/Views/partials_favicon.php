<?php /* Favicon: the company logo from Settings, else the Sygma Solar Suite logo. */ $logo = (string) setting('Solar.logoPath'); ?>
<?php if ($logo !== ''): ?>
    <link rel="icon" href="<?= site_url('branding/logo') ?>?v=<?= substr(md5($logo), 0, 8) ?>">
    <link rel="apple-touch-icon" href="<?= site_url('branding/logo') ?>?v=<?= substr(md5($logo), 0, 8) ?>">
<?php else: ?>
    <link rel="icon" type="image/png" sizes="64x64" href="<?= base_url('favicon.png') ?>">
    <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
    <link rel="apple-touch-icon" href="<?= base_url('apple-touch-icon.png') ?>">
<?php endif ?>
