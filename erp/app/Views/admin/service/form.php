<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>New Complaint<?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/service') ?>" class="card card-body" style="max-width:900px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Customer *</label><select name="customer_id" id="customer_id" class="form-select" required><option value="">— Select customer —</option>
            <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" data-phone="<?= esc((string) $c['phone']) ?>" <?= (int) old('customer_id', $customerId) === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?> (<?= esc((string) $c['phone']) ?><?= $c['city'] ? ', ' . esc($c['city']) : '' ?>)</option><?php endforeach ?></select></div>
        <div class="col-md-6"><label class="form-label">Project / installation</label><select name="project_id" id="project_id" class="form-select"><option value="">— Not linked —</option>
            <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>" data-customer="<?= (int) $p['customer_id'] ?>" <?= (int) old('project_id', $projectId) === (int) $p['id'] ? 'selected' : '' ?>><?= esc($p['project_no']) ?> – <?= qty($p['capacity_kw']) ?> kW</option><?php endforeach ?></select>
            <div class="form-text">Linking the project shows whether it is under AMC.</div></div>
        <div class="col-md-5"><label class="form-label">Problem *</label><select name="category" class="form-select" required><?= select_options(\App\Libraries\ServiceDesk::CATEGORIES, old('category'), '— Select —') ?></select></div>
        <div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select"><?= select_options(\App\Libraries\ServiceDesk::PRIORITIES, old('priority', 'normal')) ?></select></div>
        <div class="col-md-4"><label class="form-label">Reported by</label><select name="reported_via" class="form-select"><?= select_options(array_diff_key(\App\Libraries\ServiceDesk::VIA, ['status_page' => 1]), old('reported_via', 'phone')) ?></select></div>
        <div class="col-12"><label class="form-label">Details *</label><textarea name="description" class="form-control" rows="3" required placeholder="What the customer said: since when, error code on the inverter, generation reading…"><?= esc(old('description')) ?></textarea></div>
        <div class="col-md-4"><label class="form-label">Contact phone</label><input name="contact_phone" id="contact_phone" class="form-control" value="<?= esc(old('contact_phone')) ?>" placeholder="If different from the customer's"></div>
        <?php if (can('amc.manage')): ?>
        <div class="col-md-4"><label class="form-label">Assign technician (optional)</label><select name="assigned_to" class="form-select"><?= select_options(staff_options('service') ?: staff_options(), old('assigned_to'), '— Later —') ?></select></div>
        <div class="col-md-4"><label class="form-label">Visit date</label><input type="date" name="scheduled_date" class="form-control" value="<?= esc(old('scheduled_date')) ?>"></div>
        <?php endif ?>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Register complaint</button> <a href="<?= site_url('admin/service') ?>" class="btn btn-light">Cancel</a></div>
    <div class="form-text">The customer gets an acknowledgement with the complaint number, and the service team is notified.</div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    const c = document.getElementById('customer_id'), p = document.getElementById('project_id');
    const filter = () => { let first = null; [...p.options].forEach(o => { if (!o.value) return; o.hidden = c.value && o.dataset.customer !== c.value; if (!o.hidden && !first) first = o; });
        if (p.selectedOptions[0] && p.selectedOptions[0].hidden) p.value = ''; if (!p.value && first && c.value) p.value = first.value; };
    c.addEventListener('change', filter); filter();
})();
</script>
<?= $this->endSection() ?>
