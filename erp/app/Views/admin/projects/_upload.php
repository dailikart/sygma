<?php /* Upload for $stage with $labels [label => title] */ ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url('admin/projects/' . $pid . '/upload') ?>" class="row g-2 align-items-end border rounded p-2 bg-light my-2">
    <?= csrf_field() ?><input type="hidden" name="stage" value="<?= esc($stage) ?>">
    <div class="col-sm-4"><label class="form-label small mb-0">What</label><select name="label" class="form-select form-select-sm"><?= select_options($labels) ?></select></div>
    <div class="col-sm-5"><label class="form-label small mb-0">Photo / file</label><input type="file" name="files[]" multiple accept="image/*,application/pdf" capture="environment" class="form-control form-control-sm"></div>
    <div class="col-sm-3"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-upload"></i> Upload</button></div>
</form>
