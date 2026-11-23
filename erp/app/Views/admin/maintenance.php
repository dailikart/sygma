<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Database Updates<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card card-body" style="max-width:760px">
    <?php if ($pending): ?>
        <p><strong><?= count($pending) ?> database update(s)</strong> are waiting. They add the tables and columns needed by the latest version. Take a database backup in phpMyAdmin (Export) first.</p>
        <ul class="small"><?php foreach ($pending as $p): ?><li><code><?= esc($p) ?></code></li><?php endforeach ?></ul>
        <form method="post" action="<?= site_url('admin/maintenance/migrate') ?>"><?= csrf_field() ?>
            <button class="btn btn-primary" data-confirm="Run the database updates now?"><i class="bi bi-database-up"></i> Run database updates</button></form>
    <?php else: ?>
        <p class="mb-0 text-success"><i class="bi bi-check-circle"></i> The database is up to date.</p>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
