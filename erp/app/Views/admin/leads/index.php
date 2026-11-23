<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Leads<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <form class="d-flex gap-2" method="get">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" class="form-control form-control-sm" placeholder="Name, phone, city">
        <select name="status" class="form-select form-select-sm"><?= select_options($statuses, service('request')->getGet('status'), 'Open leads') ?></select>
        <select name="source" class="form-select form-select-sm"><?php $srcs = config('Workflow')->leadSources; ?><?= select_options(array_combine($srcs, $srcs), service('request')->getGet('source'), 'All sources') ?></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <a href="<?= site_url('admin/leads?due=1') ?>" class="btn btn-sm btn-outline-danger">Follow-ups due</a>
    <?php if (can('leads.manage')): ?><a href="<?= site_url('admin/leads/new') ?>" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New Lead</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Name</th><th>Phone</th><th>City</th><th>Type</th><th>kW</th><th>Source</th><th>Status</th><th>Next follow-up</th><th>Assigned</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $overdue = $r['next_followup_date'] && $r['next_followup_date'] <= date('Y-m-d'); ?>
        <tr>
            <td><a href="<?= site_url('admin/leads/' . $r['id']) ?>"><?= esc($r['name']) ?></a></td>
            <td><a href="tel:<?= esc($r['phone']) ?>"><?= esc($r['phone']) ?></a></td>
            <td><?= esc($r['city'] ?? '') ?></td>
            <td><?= status_badge($r['customer_type']) ?></td>
            <td><?= qty($r['required_kw']) ?></td>
            <td><?= esc($r['source'] ?? '') ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="<?= $overdue ? 'text-danger fw-semibold' : '' ?>"><?= fdate($r['next_followup_date']) ?></td>
            <td><?= user_name($r['assigned_to']) ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No leads found.</td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
<?= $this->endSection() ?>
