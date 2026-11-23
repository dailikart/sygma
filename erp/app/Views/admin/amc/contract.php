<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>AMC <?= esc($c['contract_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card card-body mb-3"><div class="row small">
    <div class="col-md-4"><strong><?= esc($customer['name']) ?></strong><br><?= esc((string) $project['site_address']) ?><br><?= esc((string) $customer['phone']) ?></div>
    <div class="col-md-4">Plan: <strong><?= esc($plan['name']) ?></strong> <?= $plan['is_free'] ? '(free)' : money($c['amount']) ?><br>Visit every <?= (int) $plan['interval_months'] ?> months</div>
    <div class="col-md-4">Project <a href="<?= site_url('admin/projects/' . $project['id']) ?>"><?= esc($project['project_no']) ?></a> (<?= qty($project['capacity_kw']) ?> kW)<br><?= fdate($c['start_date']) ?> – <?= fdate($c['end_date']) ?> <?= status_badge($c['status']) ?></div>
</div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>#</th><th>Due</th><th>Status</th><th>Technician</th><th style="min-width:340px">Visit details</th></tr></thead><tbody>
    <?php foreach ($visits as $v): ?>
    <tr><td><?= (int) $v['visit_no'] ?></td><td><?= fdate($v['due_date']) ?></td><td><?= status_badge($v['status'] === 'scheduled' ? 'pending' : 'done') ?></td>
        <td><?php if ($v['status'] === 'scheduled' && can('amc.manage')): ?>
            <form method="post" action="<?= site_url('admin/amc/visits/' . $v['id'] . '/assign') ?>" class="d-flex gap-1"><?= csrf_field() ?>
                <select name="user_id" class="form-select form-select-sm"><?= select_options($staff, $v['assigned_to'], '—') ?></select>
                <input type="date" name="due_date" value="<?= esc($v['due_date']) ?>" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-secondary">Save</button></form>
            <?php else: ?><?= esc(user_name($v['assigned_to'] ?: $v['done_by'])) ?><?php endif ?></td>
        <td><?php if ($v['status'] === 'done'): ?><small><?= fdate($v['done_date']) ?> – <?= esc((string) $v['findings']) ?></small><?php if ($v['photo_path']): ?> <a href="<?= site_url('admin/amc/visits/' . $v['id'] . '/photo') ?>" target="_blank"><i class="bi bi-image"></i></a><?php endif ?>
            <?php elseif (can('amc.manage') || (int) $v['assigned_to'] === (int) auth()->id()): ?>
            <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/amc/visits/' . $v['id'] . '/complete') ?>" class="d-flex flex-wrap gap-1"><?= csrf_field() ?>
                <input type="date" name="done_date" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm" style="max-width:140px">
                <input name="findings" class="form-control form-control-sm" placeholder="Cleaning done, generation OK…" style="min-width:160px;flex:1">
                <input type="file" name="photo" accept="image/*" capture="environment" class="form-control form-control-sm" style="max-width:200px">
                <button class="btn btn-sm btn-success">Done</button></form>
            <?php endif ?></td></tr>
    <?php endforeach ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
