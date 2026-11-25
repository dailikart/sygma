<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Dispatch <?= esc($d['dispatch_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $short = false; foreach ($lines as $l) { if ($d['status'] === 'draft' && ($balances[$l['item_id']][$d['location_id']] ?? 0) < $l['qty']) { $short = true; } } ?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <?= status_badge($d['status']) ?>
    <a href="<?= site_url('admin/projects/' . $project['id']) ?>" class="small"><?= esc($project['project_no']) ?></a>
    <a href="<?= site_url('admin/dispatches/' . $d['id'] . '/delivery-note') ?>" target="_blank" class="btn btn-primary ms-auto"><i class="bi bi-file-earmark-text"></i> Print Delivery Note</a>
    <a href="<?= site_url('admin/dispatches/' . $d['id'] . '/print') ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Loading list</a>
    <?php if ($d['status'] === 'draft' && can('dispatch.manage')): ?>
        <form method="post" action="<?= site_url('admin/dispatches/' . $d['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" data-confirm="Delete this draft dispatch list?"><i class="bi bi-trash"></i> Delete draft</button></form>
    <?php endif ?>
</div>
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-body">
    <div class="row small mb-3"><div class="col-sm-6"><strong><?= esc($customer['name']) ?></strong><br><?= esc((string) $project['site_address']) ?><br><a href="tel:<?= esc((string) $customer['phone']) ?>"><?= esc((string) $customer['phone']) ?></a></div>
        <div class="col-sm-6 text-sm-end">From: <strong><?= esc($location['name']) ?></strong><br>System: <?= qty($project['capacity_kw']) ?> kW<?= $d['vehicle_no'] ? '<br>Vehicle: ' . esc($d['vehicle_no']) : '' ?><?= $d['driver_name'] ? '<br>Driver: ' . esc($d['driver_name']) : '' ?><?= ! empty($d['dispatched_through']) ? '<br>Through: ' . esc($d['dispatched_through']) : '' ?><?= ! empty($d['destination']) ? '<br>Destination: ' . esc($d['destination']) : '' ?></div></div>
    <?php $serialEdit = $d['status'] !== 'delivered' && can('dispatch.manage'); ?>
    <form method="post" action="<?= site_url('admin/dispatches/' . $d['id'] . '/serials') ?>" id="serialForm"><?= csrf_field() ?>
    <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>#</th><th>Item</th><th>Category</th><th class="text-end">Qty</th><?= $d['status'] === 'draft' ? '<th class="text-end">In store</th>' : '' ?><th style="min-width:180px">Serial no(s)</th></tr></thead><tbody>
    <?php foreach ($lines as $n => $l): $have = $balances[$l['item_id']][$d['location_id']] ?? 0; ?>
        <tr><td><?= $n + 1 ?></td><td><?= esc($l['name']) ?></td><td><small><?= esc((string) $l['category']) ?></small></td><td class="text-end"><?= qty($l['qty']) ?> <?= esc($l['unit']) ?></td>
            <?php if ($d['status'] === 'draft'): ?><td class="text-end <?= $have < $l['qty'] ? 'text-danger fw-semibold' : '' ?>"><?= qty($have) ?><?= $have <= 0 ? ' Out of Stock' : '' ?></td><?php endif ?>
            <td><?php if ($serialEdit): ?><textarea name="serials[<?= $l['id'] ?>]" rows="1" class="form-control form-control-sm" placeholder="<?= stripos((string) $l['category'], 'inverter') !== false ? 'Inverter S.No' : 'Optional' ?>"><?= esc((string) $l['serial_nos']) ?></textarea>
                <?php else: ?><small style="white-space:pre-line"><?= esc((string) $l['serial_nos']) ?></small><?php endif ?></td></tr>
    <?php endforeach ?>
    </tbody></table></div>
    <?php if ($serialEdit): ?><button class="btn btn-sm btn-outline-primary">Save serial numbers</button> <small class="text-muted">One per line. Inverter serial numbers fill the installation form automatically.</small><?php endif ?>
    </form>
    <?php if ($taken): $names = array_column($lines, 'name', 'item_id'); ?>
    <h3 class="h6 mt-2"><i class="bi bi-diagram-3"></i> Taken from (oldest stock first)</h3>
    <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Item</th><th class="text-end">Qty</th><th>Purchase · supplier bill · PO</th><th>Received</th><th class="text-end">Rate</th></tr></thead><tbody>
    <?php foreach ($taken as $itemId => $lots): foreach ($lots as $l): ?>
        <tr><td><a href="<?= site_url('admin/stock/item/' . $itemId . '/lots') ?>"><?= esc($names[$itemId] ?? ('#' . $itemId)) ?></a></td><td class="text-end"><?= qty($l['taken']) ?></td>
            <td><?= view('admin/stock/_lot_source', ['lot' => $l]) ?></td><td class="text-nowrap"><small><?= fdate($l['received_on']) ?></small></td><td class="text-end"><?= money($l['rate'], false) ?></td></tr>
    <?php endforeach; endforeach ?>
    </tbody></table></div>
    <?php endif ?>
    <?php if ($photos): ?><h3 class="h6">Delivery photos</h3><?= view('admin/projects/_files', ['list' => $photos]) ?><?php endif ?>
    <?php if ($d['delivery_note']): ?><p class="small">Note: <?= esc($d['delivery_note']) ?></p><?php endif ?>
    <p class="small text-muted mb-0">Dispatched <?= fdate($d['dispatched_at'], true) ?> <?= $d['dispatched_by'] ? 'by ' . esc(user_name($d['dispatched_by'])) : '' ?> · Delivered <?= fdate($d['delivered_at'], true) ?> <?= $d['delivered_by'] ? 'by ' . esc(user_name($d['delivered_by'])) : '' ?></p>
</div></div></div>
<div class="col-lg-4">
    <?php if ($d['status'] === 'draft' && can('dispatch.manage')): ?>
    <form method="post" action="<?= site_url('admin/dispatches/' . $d['id'] . '/dispatch') ?>" class="card card-body"><?= csrf_field() ?>
        <h3 class="h6"><i class="bi bi-truck"></i> Load &amp; dispatch</h3>
        <?php if ($short): ?><div class="alert alert-danger py-2 small">Some items are short in this store. Add stock (purchase / adjustment) before dispatching.</div><?php endif ?>
        <label class="form-label small">Vehicle no.</label><input name="vehicle_no" class="form-control mb-2">
        <label class="form-label small">Driver / delivery person</label><input name="driver_name" class="form-control mb-2">
        <label class="form-label small">Dispatched through</label><input name="dispatched_through" class="form-control mb-2" list="dthrough" placeholder="Auto / Tempo / Own vehicle / Courier">
        <datalist id="dthrough"><option value="AUTO"><option value="TEMPO"><option value="OWN VEHICLE"><option value="LORRY"><option value="COURIER"></datalist>
        <label class="form-label small">Destination</label><input name="destination" class="form-control mb-2" value="<?= esc(strtoupper((string) ($customer['city'] ?? ''))) ?>">
        <label class="form-label small">Terms of delivery</label><input name="terms_of_delivery" class="form-control mb-3">
        <button class="btn btn-primary" <?= $short ? 'disabled' : '' ?> data-confirm="Material loaded? Stock will be reduced and shown as in transit.">Dispatch now</button>
    </form>
    <?php elseif ($d['status'] === 'dispatched' && (can('delivery.update') || can('dispatch.manage'))): ?>
    <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/dispatches/' . $d['id'] . '/deliver') ?>" class="card card-body border-success border"><?= csrf_field() ?>
        <h3 class="h6"><i class="bi bi-geo-alt"></i> Delivered at site</h3>
        <label class="form-label small">Photo of the material at the site *</label>
        <input type="file" name="photo" accept="image/*" capture="environment" class="form-control mb-2" required>
        <label class="form-label small">Note (received by, any shortage…)</label><input name="delivery_note" class="form-control mb-3">
        <button class="btn btn-success btn-lg"><i class="bi bi-check2-circle"></i> Mark delivered</button>
    </form>
    <?php endif ?>
</div>
</div>
<?= $this->endSection() ?>
