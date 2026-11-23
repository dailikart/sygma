<?php /* $list (project_files rows), optional $label filter, optional $stageRow (for delete rights) */
$list = array_values(array_filter($list ?? [], static fn ($f) => empty($label) || $f['label'] === $label));
$stageRow ??= null; ?>
<?php if ($list): ?><div class="d-flex flex-wrap gap-2 my-2">
<?php foreach ($list as $f): $isImg = preg_match('/\.(jpe?g|png|webp)$/i', $f['file_path']); $cap = ucfirst(str_replace('_', ' ', $f['label'])); ?>
    <div class="text-center position-relative" style="width:100px">
        <a href="<?= site_url('admin/files/' . $f['id']) ?>" target="_blank" class="text-decoration-none" title="<?= esc($cap) ?> – <?= fdate($f['created_at'], true) ?> – <?= esc(user_name($f['uploaded_by'])) ?>">
            <?php if ($isImg): ?><img src="<?= site_url('admin/files/' . $f['id']) ?>" class="photo-thumb" alt="" loading="lazy"><?php else: ?><span class="photo-thumb d-flex align-items-center justify-content-center bg-light"><i class="bi bi-file-earmark-pdf fs-2 text-danger"></i></span><?php endif ?>
            <div class="small text-muted text-truncate"><?= esc($cap) ?></div>
        </a>
        <?php if (\App\Controllers\Admin\Files::canDelete($f, $stageRow)): ?>
        <form method="post" action="<?= site_url('admin/files/' . $f['id'] . '/delete') ?>" class="position-absolute top-0 end-0" style="margin:2px"><?= csrf_field() ?>
            <button class="btn btn-sm btn-light border py-0 px-1 text-danger" title="Delete" data-confirm="Delete this <?= esc(strtolower($cap)) ?>?"><i class="bi bi-trash"></i></button></form>
        <?php endif ?>
    </div>
<?php endforeach ?></div><?php endif ?>
