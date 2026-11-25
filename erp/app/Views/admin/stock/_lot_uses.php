<?php /** Where a lot's stock went. @var list<array> $used */ ?>
<?php foreach ($used as $u): ?>
    <div class="small"><?= qty($u['qty']) ?> →
    <?php if ($u['ref_type'] === 'dispatch' && $u['dispatch_no']): ?>
        <a href="<?= site_url('admin/dispatches/' . $u['ref_id']) ?>"><?= esc($u['dispatch_no']) ?></a><?= $u['project_id'] ? ' · <a href="' . site_url('admin/projects/' . $u['project_id']) . '">' . esc((string) $u['project_no']) . '</a> ' . esc((string) $u['customer']) : '' ?>
    <?php elseif ($u['ref_type'] === 'purchase_return' && $u['return_no']): ?>
        returned <a href="<?= site_url('admin/purchase-returns/' . $u['ref_id']) ?>"><?= esc($u['return_no']) ?></a>
    <?php else: ?>
        adjustment<?= $u['note'] ? ': ' . esc($u['note']) : '' ?>
    <?php endif ?>
    <span class="text-muted text-nowrap"><?= fdate($u['created_at']) ?></span></div>
<?php endforeach ?>
