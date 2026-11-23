<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>AMC Service Visits<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="btn-group mb-3">
<?php foreach (['due' => 'Due in 30 days', 'all' => 'All scheduled', 'done' => 'Completed'] as $k => $l): ?><a href="<?= site_url('admin/amc?show=' . $k) ?>" class="btn btn-sm <?= $show === $k ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $l ?></a><?php endforeach ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Due</th><th>Visit</th><th>Customer</th><th>Project / plan</th><th>Technician</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): $over = $r['status'] === 'scheduled' && $r['due_date'] < date('Y-m-d'); ?>
        <tr><td class="<?= $over ? 'text-danger fw-semibold' : '' ?>"><?= fdate($r['due_date']) ?></td><td>#<?= (int) $r['visit_no'] ?> <?= status_badge($r['status'] === 'scheduled' ? 'pending' : 'done') ?></td>
            <td><?= esc($r['name']) ?><br><small class="text-muted"><?= esc(trim(($r['address'] ?? '') . ' ' . ($r['city'] ?? ''))) ?> <a href="tel:<?= esc((string) $r['phone']) ?>"><?= esc((string) $r['phone']) ?></a></small></td>
            <td><a href="<?= site_url('admin/amc/contracts/' . $r['amc_contract_id']) ?>"><?= esc($r['contract_no']) ?></a> · <a href="<?= site_url('admin/projects/' . $r['project_id']) ?>"><?= esc($r['project_no']) ?></a><br><small class="text-muted"><?= esc($r['plan']) ?></small></td>
            <td><?= esc(user_name($r['assigned_to'])) ?></td>
            <td class="text-end"><a href="<?= site_url('admin/amc/contracts/' . $r['amc_contract_id']) ?>" class="btn btn-sm btn-outline-primary">Open</a></td></tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No visits.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
