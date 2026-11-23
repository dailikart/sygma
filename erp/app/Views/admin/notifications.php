<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Notifications<?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/notifications/read-all') ?>" class="mb-3 text-end"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Mark all as read</button></form>
<div class="list-group">
<?php foreach ($rows as $n): ?>
    <a href="<?= site_url('admin/notifications/' . $n['id']) ?>" class="list-group-item list-group-item-action <?= $n['read_at'] ? '' : 'fw-semibold' ?>">
        <div class="d-flex justify-content-between"><span><?= $n['read_at'] ? '' : '<i class="bi bi-circle-fill text-primary small"></i> ' ?><?= esc($n['title']) ?></span><small class="text-muted"><?= fdate($n['created_at'], true) ?></small></div>
        <?php if ($n['message']): ?><div class="small text-muted fw-normal"><?= esc($n['message']) ?></div><?php endif ?>
    </a>
<?php endforeach ?>
<?php if (! $rows): ?><div class="text-muted">No notifications yet.</div><?php endif ?>
</div>
<?= $this->endSection() ?>
