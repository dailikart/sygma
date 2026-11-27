<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Lead: <?= esc($lead['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="row g-3">
<div class="col-lg-5">
    <div class="card"><div class="card-body">
        <div class="d-flex"><h2 class="h5"><?= esc($lead['name']) ?></h2><span class="ms-auto"><?= status_badge($lead['status']) ?></span></div>
        <p class="mb-1"><i class="bi bi-telephone"></i> <a href="tel:<?= esc($lead['phone']) ?>"><?= esc($lead['phone']) ?></a>
            <a class="ms-2 small" target="_blank" href="https://wa.me/91<?= esc(preg_replace('/\D/', '', substr($lead['phone'], -10))) ?>"><i class="bi bi-whatsapp text-success"></i> WhatsApp</a></p>
        <?php if ($lead['email']): ?><p class="mb-1"><i class="bi bi-envelope"></i> <?= esc($lead['email']) ?></p><?php endif ?>
        <p class="mb-1"><i class="bi bi-geo-alt"></i> <?= esc(trim(($lead['address'] ?? '') . ' ' . ($lead['city'] ?? ''))) ?: '—' ?></p>
        <table class="table table-sm mt-2 mb-0">
            <tr><th>Type</th><td><?= status_badge($lead['customer_type']) ?></td></tr>
            <tr><th>Required kW</th><td><?= qty($lead['required_kw']) ?></td></tr>
            <tr><th>Monthly bill</th><td><?= money($lead['monthly_bill']) ?></td></tr>
            <tr><th>Source</th><td><?= esc($lead['source'] ?? '—') ?></td></tr>
            <tr><th>Assigned</th><td><?= user_name($lead['assigned_to']) ?></td></tr>
            <tr><th>Next follow-up</th><td><?= fdate($lead['next_followup_date']) ?></td></tr>
        </table>
        <?php if ($lead['notes']): ?><p class="small text-muted mt-2"><?= nl2br(esc($lead['notes'])) ?></p><?php endif ?>
        <div class="d-flex gap-2 mt-3">
            <?php if (can('leads.manage')): ?><a href="<?= site_url('admin/leads/' . $lead['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Edit</a><?php endif ?>
            <?php if ($lead['customer_id']): ?><a href="<?= site_url('admin/customers/' . $lead['customer_id']) ?>" class="btn btn-sm btn-success">Open customer</a><?php endif ?>
            <?php if (can('leads.delete') && ! $lead['customer_id']): ?><form method="post" action="<?= site_url('admin/leads/' . $lead['id'] . '/delete') ?>" class="ms-auto"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" data-confirm="Delete lead <?= esc($lead['name']) ?>? Its follow-ups go with it."><i class="bi bi-trash"></i> Delete</button></form><?php endif ?>
        </div>
    </div></div>

    <?php if (can('designs.manage') && \App\Libraries\Features::on('design')): ?><a href="<?= site_url('admin/designs/new?lead=' . $lead['id']) ?>" class="btn btn-primary w-100 mt-3"><i class="bi bi-badge-3d"></i> Design roof in 3D</a><?php endif ?>
    <?php if (can('quotations.manage')): ?><a href="<?= site_url('admin/quotations/quick?lead=' . $lead['id']) ?>" class="btn btn-primary w-100 mt-3"><i class="bi bi-lightning-charge"></i> Quick Quote for this lead</a><a href="<?= site_url('admin/quotations/new?lead=' . $lead['id']) ?>" class="btn btn-outline-primary w-100 mt-2"><i class="bi bi-file-earmark-text"></i> Create quotation for this lead</a><?php endif ?>
    <?php if (! $lead['customer_id'] && can('leads.manage') && can('customers.manage') && $lead['status'] !== 'lost'): ?>
    <form method="post" action="<?= site_url('admin/leads/' . $lead['id'] . '/convert') ?>" class="card card-body mt-3 border-success border">
        <?= csrf_field() ?>
        <h3 class="h6"><i class="bi bi-check-circle text-success"></i> Customer confirmed? Convert to customer</h3>
        <div class="d-flex gap-2"><select name="type" class="form-select"><?= select_options(['b2c' => 'B2C customer', 'b2b' => 'B2B customer'], $lead['customer_type']) ?></select>
            <button class="btn btn-success text-nowrap">Convert</button></div>
    </form>
    <?php endif ?>
</div>
<div class="col-lg-7">
    <?php if (can('leads.manage') && ! $lead['customer_id']): ?>
    <form method="post" action="<?= site_url('admin/leads/' . $lead['id'] . '/followup') ?>" class="card card-body mb-3">
        <?= csrf_field() ?>
        <h3 class="h6">Add follow-up</h3>
        <textarea name="note" class="form-control mb-2" rows="2" placeholder="What was discussed?" required></textarea>
        <div class="row g-2">
            <div class="col-sm-5"><select name="status" class="form-select"><?= select_options(array_diff_key($statuses, ['confirmed' => 1]), $lead['status']) ?></select></div>
            <div class="col-sm-4"><input type="date" name="next_followup_date" class="form-control" value="<?= date('Y-m-d', strtotime('+2 days')) ?>"></div>
            <div class="col-sm-3"><button class="btn btn-primary w-100">Save</button></div>
        </div>
    </form>
    <?php endif ?>
    <div class="card"><div class="card-header bg-white fw-semibold">Follow-up history</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($followups as $f): ?>
            <li class="list-group-item"><div class="d-flex small text-muted"><span><?= user_name($f['created_by']) ?> · <?= fdate($f['created_at'], true) ?></span><span class="ms-auto"><?= status_badge($f['status_after']) ?></span></div>
                <?= nl2br(esc($f['note'])) ?><?php if ($f['next_followup_date']): ?><div class="small text-muted">Next: <?= fdate($f['next_followup_date']) ?></div><?php endif ?></li>
        <?php endforeach ?>
        <?php if (! $followups): ?><li class="list-group-item text-muted">No follow-ups yet.</li><?php endif ?>
    </ul></div>
</div>
</div>
<?= $this->endSection() ?>
