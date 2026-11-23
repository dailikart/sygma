<?php
/**
 * Repeating item lines with qty × rate + GST.
 * Params: $items (item rows), $price ('sale_price'|'purchase_price'), $rows (prefill), $description (bool)
 */
$description = $description ?? false;
$rows        = $rows ?? [];
$opt = static function ($items, $price, $selected = null) {
    $h = '<option value="">— Item —</option>';
    foreach ($items as $it) {
        $h .= '<option value="' . $it['id'] . '" data-rate="' . esc($it[$price]) . '" data-tax-percent="' . esc($it['tax_percent']) . '" data-hsn="' . esc((string) $it['hsn']) . '" data-description="' . esc($it['name']) . '"'
            . ((string) $selected === (string) $it['id'] ? ' selected' : '') . '>' . esc($it['name']) . ($it['sku'] ? ' (' . esc($it['sku']) . ')' : '') . '</option>';
    }

    return $h;
};
$row = static function ($i, $r) use ($items, $price, $opt, $description) {
    return '<tr>'
        . '<td><select name="items[' . $i . '][item_id]" class="form-select form-select-sm item-select">' . $opt($items, $price, $r['item_id'] ?? null) . '</select>'
        . ($description ? '<input name="items[' . $i . '][description]" data-field="description" class="form-control form-control-sm mt-1" placeholder="Description" value="' . esc($r['description'] ?? '') . '">'
            . '<input type="hidden" name="items[' . $i . '][hsn]" data-field="hsn" value="' . esc($r['hsn'] ?? '') . '">' : '')
        . '</td>'
        . '<td><input type="number" step="0.01" min="0" name="items[' . $i . '][qty]" data-field="qty" class="form-control form-control-sm" value="' . esc($r['qty'] ?? 1) . '"></td>'
        . '<td><input type="number" step="0.01" min="0" name="items[' . $i . '][rate]" data-field="rate" class="form-control form-control-sm" value="' . esc($r['rate'] ?? '') . '"></td>'
        . '<td><input type="number" step="0.01" min="0" name="items[' . $i . '][tax_percent]" data-field="tax_percent" class="form-control form-control-sm" value="' . esc($r['tax_percent'] ?? '') . '"></td>'
        . '<td class="text-end" data-out="amount">0.00</td>'
        . '<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x"></i></button></td></tr>';
};
?>
<div class="table-responsive">
<table class="table table-sm line-items align-middle" id="lines" data-template="#line-tpl">
    <thead><tr><th style="min-width:240px">Item</th><th style="width:110px">Qty</th><th style="width:130px">Rate (₹)</th><th style="width:100px">GST %</th><th class="text-end" style="width:130px">Amount incl. GST</th><th style="width:40px"></th></tr></thead>
    <tbody>
        <?php foreach ($rows ?: [[]] as $i => $r) { echo $row($i, $r); } ?>
    </tbody>
    <tfoot>
        <tr><td colspan="6"><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#lines"><i class="bi bi-plus"></i> Add line</button></td></tr>
        <tr><td colspan="4" class="text-end">Sub total</td><td class="text-end" id="sum-subtotal">0.00</td><td></td></tr>
        <tr><td colspan="4" class="text-end">GST</td><td class="text-end" id="sum-tax">0.00</td><td></td></tr>
        <tr class="fw-bold"><td colspan="4" class="text-end">Total</td><td class="text-end" id="sum-total">0.00</td><td></td></tr>
    </tfoot>
</table>
</div>
<template id="line-tpl"><?= $row('__i__', []) ?></template>
