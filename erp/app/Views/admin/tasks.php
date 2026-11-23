<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>My Tasks<?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-muted">Project stages waiting for you: assigned to you, or unassigned in a stage your role handles.</p>
<div class="card"><?= view('admin/partials/task_table', ['tasks' => $tasks]) ?></div>
<?= $this->endSection() ?>
