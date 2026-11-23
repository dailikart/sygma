<?php /* Install-on-phone support: manifest, icons, theme colour, service worker. Included in the admin and login layouts. */ ?>
<link rel="manifest" href="<?= site_url('manifest.webmanifest') ?>">
<meta name="theme-color" content="<?= esc(['' => '#1f2937', 'ocean' => '#0c4a6e', 'forest' => '#14532d', 'sunset' => '#ea580c', 'purple' => '#7c3aed', 'crimson' => '#e11d48', 'teal' => '#0d9488', 'solar' => '#d97706'][(string) setting('Solar.theme')] ?? '#1f2937') ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= esc(mb_substr((string) setting('Solar.companyName'), 0, 20)) ?>">
<link rel="apple-touch-icon" href="<?= site_url('app-icon/192.png') ?>">
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(() => {}));
}
// "Install app": Android / desktop Chrome give us the install prompt; iPhone needs Share → Add to Home Screen.
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.__installPrompt = e;
    document.querySelectorAll('[data-install-app]').forEach((el) => el.classList.remove('d-none'));
});
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-install-app]');
    if (!btn) return;
    e.preventDefault();
    if (window.__installPrompt) { window.__installPrompt.prompt(); window.__installPrompt = null; return; }
    alert(/iphone|ipad|ipod/i.test(navigator.userAgent)
        ? 'On iPhone / iPad (Safari): tap the Share button, then "Add to Home Screen".'
        : 'Open this site in Chrome on your phone, then use the menu (⋮) → "Install app" or "Add to Home screen".');
});
window.addEventListener('appinstalled', () => document.querySelectorAll('[data-install-app]').forEach((el) => el.classList.add('d-none')));
</script>
