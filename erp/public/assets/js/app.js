// Repeating line items: <table class="line-items" data-template="#row-tpl"> with
// a <template id="row-tpl"> whose inputs use the __i__ placeholder.
document.addEventListener('click', function (e) {
    const add = e.target.closest('[data-add-row]');
    if (add) {
        const table = document.querySelector(add.dataset.addRow);
        const tpl = document.querySelector(table.dataset.template);
        const i = Date.now();
        table.querySelector('tbody').insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', i));
        recalc(table);
    }
    const del = e.target.closest('[data-remove-row]');
    if (del) {
        const table = del.closest('table');
        del.closest('tr').remove();
        recalc(table);
    }
    if (e.target.closest('[data-confirm]') && !confirm(e.target.closest('[data-confirm]').dataset.confirm)) {
        e.preventDefault();
    }
});

// When an item is picked, fill rate / tax / HSN from the option's data attributes.
document.addEventListener('change', function (e) {
    if (e.target.matches('.item-select')) {
        const opt = e.target.selectedOptions[0];
        const tr = e.target.closest('tr');
        if (opt && tr) {
            ['rate', 'tax_percent', 'hsn', 'description'].forEach(function (f) {
                const input = tr.querySelector('[data-field="' + f + '"]');
                if (input && opt.dataset[f.replace('_p', 'P')] !== undefined) {
                    input.value = opt.dataset[f.replace('_p', 'P')];
                }
            });
        }
    }
    const table = e.target.closest('table.line-items');
    if (table) recalc(table);
});
document.addEventListener('input', function (e) {
    const table = e.target.closest('table.line-items');
    if (table) recalc(table);
});

function recalc(table) {
    if (!table) return;
    let sub = 0, tax = 0;
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        const q = parseFloat((tr.querySelector('[data-field="qty"]') || {}).value) || 0;
        const r = parseFloat((tr.querySelector('[data-field="rate"]') || {}).value) || 0;
        const t = parseFloat((tr.querySelector('[data-field="tax_percent"]') || {}).value) || 0;
        const amt = Math.round(q * r * 100) / 100;
        const tx = Math.round(amt * t) / 100;
        sub += amt; tax += tx;
        const out = tr.querySelector('[data-out="amount"]');
        if (out) out.textContent = (amt + tx).toFixed(2);
    });
    const set = function (sel, v) { const el = document.querySelector(sel); if (el) el.textContent = v.toFixed(2); };
    set('#sum-subtotal', sub); set('#sum-tax', tax); set('#sum-total', sub + tax);
}
document.querySelectorAll('table.line-items').forEach(recalc);
