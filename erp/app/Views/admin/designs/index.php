<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>3D Roof Designs<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <form class="d-flex gap-2" method="get">
        <input name="q" class="form-control form-control-sm" style="max-width:260px" placeholder="Design no., name or lead" value="<?= esc((string) service('request')->getGet('q')) ?>">
        <button class="btn btn-sm btn-outline-secondary">Search</button>
    </form>
    <?php if (can('designs.manage')): ?><a href="<?= site_url('admin/designs/new') ?>" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New design</a><?php endif ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th style="width:84px"></th><th>Design</th><th>Lead</th><th class="text-end">System</th><th class="text-end">Shading</th><th class="text-end">Generation / year</th><th>Quotation</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr onclick="location.href='<?= site_url('admin/designs/' . $r['id']) ?>'" style="cursor:pointer">
            <td><?php if ($r['snapshot_path']): ?><img src="<?= site_url('admin/designs/' . $r['id'] . '/snapshot') ?>" alt="" style="width:72px;height:48px;object-fit:cover;border-radius:6px"><?php else: ?><div class="bg-light rounded text-center text-muted" style="width:72px;height:48px;line-height:48px"><i class="bi bi-badge-3d"></i></div><?php endif ?></td>
            <td><a href="<?= site_url('admin/designs/' . $r['id']) ?>" class="fw-semibold"><?= esc($r['design_no']) ?></a><div class="small text-muted"><?= esc($r['name']) ?></div></td>
            <td><?= $r['lead_id'] ? '<a href="' . site_url('admin/leads/' . $r['lead_id']) . '" onclick="event.stopPropagation()">' . esc((string) $r['lead_name']) . '</a>' : '<span class="text-muted">—</span>' ?></td>
            <td class="text-end"><?= qty($r['capacity_kw']) ?> kW<div class="small text-muted"><?= (int) $r['panel_count'] ?> × <?= qty($r['panel_watt']) ?> W</div></td>
            <td class="text-end"><?= qty($r['shading_loss']) ?>%</td>
            <td class="text-end"><?= number_format((float) $r['kwh_year']) ?> kWh</td>
            <td><?= $r['quote_no'] ? '<a href="' . site_url('admin/quotations/' . $r['quotation_id']) . '" onclick="event.stopPropagation()">' . esc($r['quote_no']) . '</a>' : '<span class="text-muted">—</span>' ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No designs yet. Open a lead and click <b>Design roof in 3D</b>, or start a <a href="<?= site_url('admin/designs/new') ?>">new design</a>.</td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
<?= $this->endSection() ?>
