<?php
/** Purchase return header + lines, loaded from the purchase. Only the return quantity is editable. */
$canReturn = array_sum(array_column($rows, 'left')) > 0;
?>
<input type="hidden" name="purchase_id" value="<?= (int) $head['purchase_id'] ?>">
<input type="hidden" name="supplier_id" value="<?= (int) $head['supplier_id'] ?>">
<input type="hidden" name="location_id" value="<?= (int) $head['location_id'] ?>">
<div class="card card-body mb-3">
    <div class="row g-3">
        <div class="col-md-3"><span class="text-muted small">Supplier</span><br><strong><?= esc($head['supplier_name']) ?></strong></div>
        <div class="col-md-3"><span class="text-muted small">Supplier invoice no. / date</span><br><strong><?= esc($head['bill_no'] ?: '—') ?></strong> dt. <?= fdate($head['bill_date']) ?></div>
        <div class="col-md-2"><span class="text-muted small">Our purchase no.</span><br><a href="<?= site_url('admin/purchases/' . $head['purchase_id']) ?>"><?= esc($head['purchase_no']) ?></a></div>
        <div class="col-md-2"><span class="text-muted small">Return from location</span><br><?= esc($head['location_name']) ?></div>
        <div class="col-md-2"><label class="form-label small text-muted mb-0">Return date *</label><input type="date" name="doc_date" class="form-control form-control-sm" required min="<?= esc($head['bill_date']) ?>" value="<?= esc(old('doc_date', date('Y-m-d'))) ?>"></div>
    </div>
</div>
<div class="card card-body mb-3">
    <?php if (! $canReturn): ?><div class="alert alert-warning mb-3">Everything on this purchase has already been returned.</div><?php endif ?>
    <div class="table-responsive">
    <table class="table table-sm line-items align-middle mb-0">
        <thead><tr><th>Item</th><th class="text-end">Bought</th><th class="text-end">Already returned</th><th style="width:140px">Return qty</th><th class="text-end">Rate (₹)</th><th class="text-end">GST %</th><th class="text-end" style="width:130px">Amount incl. GST</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr<?= $r['left'] <= 0 ? ' class="text-muted"' : '' ?>>
                <td><?= esc($r['name']) ?><input type="hidden" name="items[<?= $i ?>][item_id]" value="<?= (int) $r['item_id'] ?>"></td>
                <td class="text-end"><?= qty($r['qty']) ?> <?= esc($r['unit']) ?></td>
                <td class="text-end"><?= qty($r['returned']) ?></td>
                <td><input type="number" step="0.01" min="0" max="<?= esc((string) $r['left']) ?>" name="items[<?= $i ?>][qty]" data-field="qty" class="form-control form-control-sm" value="<?= esc(old("items.{$i}.qty", '0')) ?>"<?= $r['left'] <= 0 ? ' readonly' : '' ?>>
                    <div class="form-text">max <?= qty($r['left']) ?></div></td>
                <td class="text-end"><?= money($r['rate'], false) ?><input type="hidden" data-field="rate" value="<?= esc($r['rate']) ?>"></td>
                <td class="text-end"><?= qty($r['tax_percent']) ?>%<input type="hidden" data-field="tax_percent" value="<?= esc($r['tax_percent']) ?>"></td>
                <td class="text-end" data-out="amount">0.00</td>
            </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot>
            <tr><td colspan="6" class="text-end">Sub total</td><td class="text-end" id="sum-subtotal">0.00</td></tr>
            <tr><td colspan="6" class="text-end">GST</td><td class="text-end" id="sum-tax">0.00</td></tr>
            <tr class="fw-bold"><td colspan="6" class="text-end">Return value (debit note)</td><td class="text-end" id="sum-total">0.00</td></tr>
        </tfoot>
    </table>
    </div>
    <div class="form-text">Enter the quantity being sent back for each item; leave 0 for items not returned.</div>
</div>
