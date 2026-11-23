<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Users<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex mb-3"><a href="<?= site_url('admin/users/new') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New User</a></div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Username</th><th>Email</th><th>Roles</th><th>Status</th><th>Last active</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= esc($u['username']) ?></td>
            <td><?= esc($u['email']) ?></td>
            <td><?php foreach ($groups[$u['id']] ?? [] as $g): ?><span class="badge text-bg-light border me-1"><?= esc($g) ?></span><?php endforeach ?></td>
            <?php $isActive = $u['status'] !== 'banned'; ?>
            <td><?= $isActive ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Deactivated</span>' ?></td>
            <td><?= fdate($u['last_active'], true) ?></td>
            <td class="text-end text-nowrap">
                <a href="<?= site_url('admin/users/' . $u['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                <?php if ((int) $u['id'] !== (int) auth()->id()): ?>
                <form method="post" action="<?= site_url('admin/users/' . $u['id'] . '/toggle') ?>" class="d-inline"><?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-secondary" <?= $isActive ? 'data-confirm="Deactivate this user? They are logged out and cannot log in."' : '' ?>><?= $isActive ? 'Deactivate' : 'Activate' ?></button></form>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div></div>
<?= $this->endSection() ?>
