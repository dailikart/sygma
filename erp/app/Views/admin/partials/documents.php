<?php
$canUpload = can('documents.upload') || can('customers.manage');
$fileLinks = static function (array $d) use ($canUpload): string {
    $isPdf = str_ends_with(strtolower($d['file_path']), '.pdf');
    $h  = '<a href="' . site_url('admin/documents/' . $d['id']) . '" target="_blank" class="btn btn-sm btn-outline-primary py-0" title="View"><i class="bi bi-eye"></i> View</a> ';
    $h .= '<a href="' . site_url('admin/documents/' . $d['id'] . '?download=1') . '" class="btn btn-sm btn-outline-secondary py-0" title="Download"><i class="bi bi-download"></i></a> ';
    if ($canUpload) {
        $h .= '<form method="post" action="' . site_url('admin/documents/' . $d['id'] . '/delete') . '" class="d-inline">' . csrf_field()
            . '<button class="btn btn-sm btn-link text-danger p-0 ms-1" data-confirm="Delete this document?" title="Delete"><i class="bi bi-trash"></i></button></form>';
    }

    return '<i class="bi ' . ($isPdf ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary') . '"></i> <small class="text-muted me-2">' . fdate($d['created_at']) . ' · ' . esc(user_name($d['uploaded_by'])) . '</small>' . $h;
};
$missing = array_filter($types, static fn ($t) => $t['is_required'] && empty($byType[$t['id']]));
?>
<div class="card mb-3" id="documents">
    <div class="card-header bg-white fw-semibold d-flex flex-wrap gap-2 align-items-center">
        <span><i class="bi bi-folder2-open"></i> Customer documents <small class="text-muted fw-normal">(used for the PCR)</small></span>
        <span class="ms-auto"><?= $missing ? '<span class="badge text-bg-warning">' . count($missing) . ' required missing</span>' : '<span class="badge text-bg-success">All required uploaded</span>' ?></span>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/customers/' . $customerId . '/documents') ?>">
    <?= csrf_field() ?>
    <?php if ($projectId): ?><input type="hidden" name="project_id" value="<?= $projectId ?>"><?php endif ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Document</th><th>Uploaded file</th><?php if ($canUpload): ?><th style="width:32%">Upload / replace</th><?php endif ?></tr></thead>
        <tbody>
        <?php foreach ($types as $t): $files = $byType[$t['id']] ?? []; ?>
            <tr>
                <td><?= esc($t['name']) ?> <?= $t['is_required'] ? '<span class="text-danger" title="Required">*</span>' : '' ?></td>
                <td><?php if ($files): foreach ($files as $d): ?><div class="mb-1"><?= $fileLinks($d) ?></div><?php endforeach; else: ?><span class="text-muted small"><?= $t['is_required'] ? 'Not uploaded' : '—' ?></span><?php endif ?></td>
                <?php if ($canUpload): ?><td><input type="file" name="type_files[<?= $t['id'] ?>]" accept="image/*,application/pdf" class="form-control form-control-sm"></td><?php endif ?>
            </tr>
        <?php endforeach ?>
        <?php foreach ($others as $d): ?>
            <tr><td><?= esc($d['title']) ?> <small class="text-muted">(other)</small></td><td><?= $fileLinks($d) ?></td><?php if ($canUpload): ?><td></td><?php endif ?></tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
    <?php if ($canUpload): ?>
    <div class="card-body border-top">
        <div class="small fw-semibold mb-1">Other documents</div>
        <div class="table-responsive"><table class="table table-sm mb-2 line-items" id="doc-rows" data-template="#doc-tpl"><tbody></tbody></table></div>
        <template id="doc-tpl"><tr><td><input name="titles[]" class="form-control form-control-sm" placeholder="Title (e.g. Ownership proof)"></td>
            <td><input type="file" name="files[]" accept="image/*,application/pdf" class="form-control form-control-sm"></td>
            <td style="width:40px"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x"></i></button></td></tr></template>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#doc-rows"><i class="bi bi-plus-lg"></i> Add other document</button>
            <button class="btn btn-sm btn-primary ms-auto"><i class="bi bi-upload"></i> Upload selected files</button>
        </div>
    </div>
    <?php endif ?>
    </form>
</div>
