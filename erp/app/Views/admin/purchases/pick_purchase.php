<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>New Purchase Return<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="mb-3"><a href="<?= site_url('admin/purchase-returns') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Purchase Returns</a></div>
<div class="card card-body" style="max-width:980px">
    <h6>Which purchase are the items being returned from?</h6>
    <p class="small text-muted">A return is made against the supplier invoice. Its items, rates and GST are loaded; you enter only the quantities.</p>
    <input type="search" class="form-control mb-2" id="pur-filter" placeholder="Type supplier, supplier invoice no. or purchase no." autofocus>
    <div class="table-responsive" style="max-height:60vh">
    <table class="table table-hover table-sm align-middle mb-0" id="pur-table">
        <thead class="sticky-top bg-body"><tr><th>Purchase no.</th><th>Supplier</th><th>Supplier invoice no.</th><th>Invoice date</th><th class="text-end">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <tr><td><?= esc($p['purchase_no']) ?></td><td><?= esc($p['supplier']) ?></td><td><?= esc((string) $p['bill_no']) ?></td><td><?= fdate($p['bill_date']) ?></td>
                <td class="text-end"><?= money($p['total']) ?></td>
                <td class="text-end"><a href="<?= site_url('admin/purchase-returns/new?purchase=' . $p['id']) ?>" class="btn btn-sm btn-outline-warning">Return items</a></td></tr>
        <?php endforeach ?>
        <?php if (! $purchases): ?><tr><td colspan="6" class="text-center text-muted py-4">No purchases yet.</td></tr><?php endif ?>
        </tbody>
    </table>
    </div>
</div>
<script>
document.getElementById('pur-filter').addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#pur-table tbody tr').forEach(function (tr) { tr.hidden = q !== '' && ! tr.textContent.toLowerCase().includes(q); });
});
</script>
<?= $this->endSection() ?>
