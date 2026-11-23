<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Bulk Upload – <?= esc($def['title']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="mb-3"><a href="<?= site_url($def['back']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to <?= esc($def['title']) ?></a></div>

<?php if ($result): ?>
    <?php if ($result['ok']): ?>
        <div class="alert alert-success">
            <strong>Upload complete.</strong>
            <?= (int) $result['added'] ?> added, <?= (int) $result['updated'] ?> updated, <?= (int) $result['skipped'] ?> skipped (already existed).
            <?php if (! empty($result['created'])): ?>
                <div class="small mt-1">Also created: <?= esc(implode(', ', array_unique($result['created']))) ?></div>
            <?php endif ?>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <strong>Nothing was saved.</strong> Fix these <?= count($result['errors']) ?> problem(s) in your file and upload again:
            <ul class="mb-0 mt-2 small"><?php foreach ($result['errors'] as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul>
        </div>
    <?php endif ?>
<?php endif ?>

<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="<?= site_url('admin/import/' . $entity) ?>" enctype="multipart/form-data" class="card card-body">
            <?= csrf_field() ?>
            <h6 class="mb-3">Upload <?= esc($def['title']) ?></h6>
            <ol class="small text-muted ps-3">
                <li><a href="<?= site_url('admin/import/' . $entity . '?template=1') ?>"><i class="bi bi-file-earmark-excel"></i> Download the Excel template</a></li>
                <li>Fill one row per record (delete the sample row). Columns marked * are required.</li>
                <li>Save as .xlsx (or .csv) and upload below.</li>
            </ol>
            <div class="mb-3"><input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required></div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="update_existing" value="1" id="upd">
                <label class="form-check-label" for="upd">Update records that already exist (otherwise they are skipped)</label>
            </div>
            <div><button class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button></div>
            <div class="form-text mt-2">The whole file is checked first. If any row has a problem, nothing is saved and the problems are listed.</div>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Columns</div>
            <table class="table table-sm mb-0">
                <thead><tr><th>Heading</th><th>Required</th><th>Example</th></tr></thead>
                <tbody>
                <?php foreach ($def['cols'] as [$heading, $required, $example]): ?>
                    <tr><td><?= esc($heading) ?></td><td><?= $required ? '<span class="badge text-bg-danger">Yes</span>' : '' ?></td><td class="text-muted small"><?= esc($example) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="small text-muted mt-2">
            <?php if ($entity === 'items'): ?>Existing items are matched by item code (or by name when there is no code). New installation types and categories are created automatically; the opening stock location must already exist. Opening stock is added only for new items.
            <?php elseif ($entity === 'customers'): ?>Existing customers are matched by name + phone.
            <?php else: ?>Existing records are matched by name.<?php endif ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
