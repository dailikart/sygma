<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>My Tasks<?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-muted">Project stages waiting for you: assigned to you, or unassigned in a stage your role handles.</p>
<div class="card"><?= view('admin/partials/task_table', ['tasks' => $tasks]) ?></div>
<?php $tickets = \App\Libraries\ServiceDesk::forUser((int) auth()->id()); if ($tickets): ?>
<h2 class="h6 mt-4">Service complaints assigned to you</h2>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
    <thead><tr><th>Complaint</th><th>Customer</th><th>Problem</th><th>Priority</th><th>Visit</th><th></th></tr></thead><tbody>
    <?php foreach ($tickets as $t): ?><tr><td><?= esc($t['ticket_no']) ?></td><td><?= esc($t['customer']) ?><div class="small text-muted"><?= esc((string) $t['phone']) ?> <?= esc((string) $t['city']) ?></div></td>
        <td><?= esc(\App\Libraries\ServiceDesk::CATEGORIES[$t['category']] ?? '') ?></td><td><?= esc(\App\Libraries\ServiceDesk::PRIORITIES[$t['priority']] ?? '') ?></td><td><?= fdate($t['scheduled_date']) ?></td>
        <td class="text-end"><a href="<?= site_url('admin/service/' . $t['id']) ?>" class="btn btn-sm btn-primary">Open</a></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?php endif ?>
<?= $this->endSection() ?>
