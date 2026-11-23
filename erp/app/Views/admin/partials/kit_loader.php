<?php
/**
 * Kit loader for invoice / quotation forms: "Load items (B2B)" lists every kit item,
 * "Fill as B2C package" adds a system-supply line + installation line. Needs $kits and
 * elements #kit, #load-kit, #load-package, #lines, #capacity_kw, #print_format, [name=installation_type_id].
 */
$db = db_connect();
$kitJson = [];
foreach ($kits as $k) {
    $ki = $db->table('kit_items ki')->select('ki.item_id, ki.qty, i.sale_price, i.tax_percent, i.hsn')->join('items i', 'i.id = ki.item_id')->where('ki.kit_id', $k['id'])->get()->getResultArray();
    $value = 0; $byTax = [];
    foreach ($ki as $r) { $v = (float) $r['qty'] * (float) $r['sale_price']; $value += $v; $byTax[(string) (float) $r['tax_percent']] = ($byTax[(string) (float) $r['tax_percent']] ?? 0) + $v; }
    arsort($byTax);
    $kitJson[$k['id']] = ['kw' => (float) $k['capacity_kw'], 'name' => $k['name'], 'value' => round($value, 2), 'tax' => (float) (array_key_first($byTax) ?? 0), 'items' => array_map(static fn ($r) => ['item_id' => $r['item_id'], 'qty' => $r['qty']], $ki)];
}
?>
<script>
const KITS = <?= json_encode($kitJson) ?>;
document.getElementById('load-package').addEventListener('click', function () {
    const kit = KITS[document.getElementById('kit').value];
    if (!kit) { alert('Choose the kit (system size) first.'); return; }
    const table = document.getElementById('lines');
    const typeSel = document.querySelector('[name=installation_type_id]');
    const type = typeSel && typeSel.value ? typeSel.selectedOptions[0].text + ' ' : '';
    table.querySelector('tbody').innerHTML = '';
    const add = function (desc, hsn, rate, tax) {
        document.querySelector('[data-add-row="#lines"]').click();
        const tr = table.querySelector('tbody tr:last-child');
        tr.querySelector('[data-field="description"]').value = desc;
        tr.querySelector('[data-field="hsn"]').value = hsn;
        tr.querySelector('[data-field="qty"]').value = 1;
        tr.querySelector('[data-field="rate"]').value = rate;
        tr.querySelector('[data-field="tax_percent"]').value = tax;
    };
    add('Supply of ' + kit.kw + ' kW ' + type + 'Solar Power System', '8541', kit.value.toFixed(2), kit.tax);
    add('Installation & commissioning service', '9954', '0.00', 18);
    const cap = document.getElementById('capacity_kw'); if (!cap.value) cap.value = kit.kw;
    const pf = document.getElementById('print_format'); if (pf) pf.value = 'cumulative';
    table.dispatchEvent(new Event('input', {bubbles: true}));
});
document.getElementById('load-kit').addEventListener('click', function () {
    const kit = KITS[document.getElementById('kit').value];
    if (!kit) return;
    const table = document.getElementById('lines');
    table.querySelector('tbody').innerHTML = '';
    kit.items.forEach(function (ki) {
        document.querySelector('[data-add-row="#lines"]').click();
        const tr = table.querySelector('tbody tr:last-child');
        const sel = tr.querySelector('.item-select');
        sel.value = ki.item_id; sel.dispatchEvent(new Event('change', {bubbles: true}));
        tr.querySelector('[data-field="qty"]').value = parseFloat(ki.qty);
    });
    const cap = document.getElementById('capacity_kw');
    if (!cap.value) cap.value = kit.kw;
    table.dispatchEvent(new Event('input', {bubbles: true}));
});
</script>
