/*
 * Type-to-search for dropdowns, without any library.
 *
 * Turned on automatically for item dropdowns (name ends in [item_id], .item-select,
 * .dsp-item), for <select data-search>, and for any other dropdown with more than 12
 * choices. Add data-no-search to keep a plain dropdown.
 *
 * The real <select> stays in the page (hidden), so forms, "required" checks and existing
 * scripts keep working: picking a choice sets select.value and fires "change", and when a
 * script sets select.value itself the box shows the new choice.
 */
(function () {
    'use strict';
    const MIN_OPTIONS = 12;
    const proto = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    const protoIdx = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
    let open = null; // { sel, panel, input, list, items, active }

    const style = document.createElement('style');
    style.textContent = `
.ss-wrap { position: relative; }
.ss-wrap > select.ss-native { position: absolute; inset: 0; opacity: 0; pointer-events: none; }
.ss-box { text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; user-select: none; }
.ss-box.ss-placeholder { color: var(--bs-secondary-color); }
.ss-panel { position: fixed; z-index: 2000; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: .5rem; box-shadow: 0 10px 28px rgba(0,0,0,.16); display: flex; flex-direction: column; max-height: 340px; }
.ss-panel input { border: 0; border-bottom: 1px solid var(--bs-border-color); border-radius: .5rem .5rem 0 0; padding: .5rem .7rem; outline: 0; background: var(--bs-body-bg); color: var(--bs-body-color); }
.ss-list { overflow-y: auto; padding: .25rem 0; }
.ss-item { padding: .35rem .7rem; cursor: pointer; font-size: .9rem; }
.ss-item small { color: var(--bs-secondary-color); }
.ss-item.ss-active { background: var(--bs-primary); color: #fff; }
.ss-item.ss-active small { color: rgba(255,255,255,.8); }
.ss-item.ss-selected { font-weight: 600; }
.ss-group { padding: .35rem .7rem .15rem; font-size: .75rem; font-weight: 700; text-transform: uppercase; color: var(--bs-secondary-color); }
.ss-empty { padding: .5rem .7rem; color: var(--bs-secondary-color); font-size: .9rem; }
mark.ss-hit { padding: 0; background: rgba(255, 193, 7, .45); color: inherit; }`;
    document.head.appendChild(style);

    function wanted(sel) {
        if (sel.multiple || sel.size > 1 || sel.dataset.ssDone || sel.hasAttribute('data-no-search') || sel.closest('template')) return false;
        return sel.matches('.item-select, .dsp-item, [data-search]') || /\[item_id\]$/.test(sel.name || '') || sel.options.length > MIN_OPTIONS;
    }

    function label(sel) {
        const o = sel.options[sel.selectedIndex];
        return o ? o.text : '';
    }

    function sync(sel) {
        const box = sel._ssBox;
        if (!box) return;
        const o = sel.options[sel.selectedIndex];
        box.textContent = label(sel) || ' ';
        box.classList.toggle('ss-placeholder', !o || o.value === '');
        box.classList.toggle('disabled', sel.disabled);
        box.setAttribute('aria-disabled', sel.disabled ? 'true' : 'false');
        box.tabIndex = sel.disabled ? -1 : 0;
    }

    function enhance(sel) {
        if (!wanted(sel)) return;
        sel.dataset.ssDone = '1';
        const wrap = document.createElement('div');
        wrap.className = 'ss-wrap';
        const box = document.createElement('div');
        box.className = sel.className.replace(/\b(item-select|dsp-item|fin-mode|eb-by)\b/g, '') + ' ss-box';
        box.setAttribute('role', 'combobox');
        box.setAttribute('aria-haspopup', 'listbox');
        if (sel.style.maxWidth) wrap.style.maxWidth = sel.style.maxWidth;
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        wrap.appendChild(box);
        sel.classList.add('ss-native');
        sel.tabIndex = -1;
        sel._ssBox = box;

        // Keep the box in step when other scripts set the value.
        Object.defineProperty(sel, 'value', { configurable: true, get() { return proto.get.call(this); }, set(v) { proto.set.call(this, v); sync(this); } });
        Object.defineProperty(sel, 'selectedIndex', { configurable: true, get() { return protoIdx.get.call(this); }, set(v) { protoIdx.set.call(this, v); sync(this); } });
        sel.addEventListener('change', () => sync(sel));
        new MutationObserver(() => sync(sel)).observe(sel, { attributes: true, attributeFilter: ['disabled'], childList: true, subtree: true });

        box.addEventListener('mousedown', (e) => { e.preventDefault(); if (open && open.sel === sel) close(); else show(sel, ''); });
        box.addEventListener('keydown', (e) => {
            if (sel.disabled) return;
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' || e.key === 'F4') { e.preventDefault(); show(sel, ''); }
            else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); show(sel, e.key); }
        });
        sync(sel);
    }

    function show(sel, text) {
        if (sel.disabled) return;
        close();
        const panel = document.createElement('div');
        panel.className = 'ss-panel';
        panel.setAttribute('role', 'listbox');
        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = 'Type to search…';
        input.setAttribute('autocomplete', 'off');
        input.value = text;
        const list = document.createElement('div');
        list.className = 'ss-list';
        panel.appendChild(input);
        panel.appendChild(list);
        document.body.appendChild(panel);
        open = { sel, panel, input, list, items: [], active: -1 };
        place();
        render();
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
        input.addEventListener('input', render);
        input.addEventListener('keydown', key);
        list.addEventListener('mousedown', (e) => {
            const it = e.target.closest('.ss-item');
            if (it) { e.preventDefault(); choose(+it.dataset.idx); }
        });
    }

    function place() {
        if (!open) return;
        const r = open.sel._ssBox.getBoundingClientRect();
        const width = Math.max(r.width, 260);
        const below = window.innerHeight - r.bottom;
        open.panel.style.width = Math.min(width, window.innerWidth - 16) + 'px';
        open.panel.style.left = Math.max(8, Math.min(r.left, window.innerWidth - width - 8)) + 'px';
        if (below < 220 && r.top > below) { open.panel.style.top = ''; open.panel.style.bottom = (window.innerHeight - r.top + 2) + 'px'; }
        else { open.panel.style.bottom = ''; open.panel.style.top = (r.bottom + 2) + 'px'; }
    }

    function escapeHtml(s) { return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    function render() {
        const { sel, input, list } = open;
        const terms = input.value.toLowerCase().split(/\s+/).filter(Boolean);
        let html = '', group = null;
        open.items = [];
        Array.from(sel.options).forEach((o, idx) => {
            if (o.hidden || o.disabled) return;
            const text = o.text;
            const hay = (text + ' ' + (o.dataset.description || '') + ' ' + (o.dataset.hsn || '')).toLowerCase();
            if (terms.length && !terms.every((t) => hay.includes(t))) return;
            const g = o.parentElement && o.parentElement.tagName === 'OPTGROUP' ? o.parentElement.label : null;
            if (g !== group) { if (g) html += '<div class="ss-group">' + escapeHtml(g) + '</div>'; group = g; }
            let shown = escapeHtml(text);
            terms.forEach((t) => { shown = shown.replace(new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark class="ss-hit">$1</mark>'); });
            html += '<div class="ss-item' + (idx === sel.selectedIndex ? ' ss-selected' : '') + '" role="option" data-idx="' + idx + '">' + (shown || '&nbsp;') + '</div>';
            open.items.push(idx);
        });
        list.innerHTML = html || '<div class="ss-empty">No match</div>';
        const cur = open.items.indexOf(sel.selectedIndex);
        setActive(terms.length ? 0 : (cur >= 0 ? cur : 0));
    }

    function setActive(n) {
        if (!open || !open.items.length) return;
        open.active = Math.max(0, Math.min(n, open.items.length - 1));
        open.list.querySelectorAll('.ss-item').forEach((el, i) => el.classList.toggle('ss-active', i === open.active));
        const el = open.list.querySelectorAll('.ss-item')[open.active];
        if (el) el.scrollIntoView({ block: 'nearest' });
    }

    function key(e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(open.active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(open.active - 1); }
        else if (e.key === 'PageDown') { e.preventDefault(); setActive(open.active + 8); }
        else if (e.key === 'PageUp') { e.preventDefault(); setActive(open.active - 8); }
        else if (e.key === 'Enter') { e.preventDefault(); if (open.items.length) choose(open.items[open.active]); }
        else if (e.key === 'Escape') { e.preventDefault(); const s = open.sel; close(); s._ssBox.focus(); }
        else if (e.key === 'Tab') { const s = open.sel; if (open.items.length && open.input.value) choose(open.items[open.active], true); else close(); s._ssBox.focus(); }
    }

    function choose(idx, keepFocus) {
        const sel = open.sel;
        close();
        if (sel.selectedIndex !== idx) {
            protoIdx.set.call(sel, idx);
            sel.dispatchEvent(new Event('input', { bubbles: true }));
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        sync(sel);
        if (!keepFocus) sel._ssBox.focus();
    }

    function close() {
        if (open) { open.panel.remove(); open = null; }
    }

    document.addEventListener('mousedown', (e) => { if (open && !open.panel.contains(e.target)) close(); }, true);
    window.addEventListener('resize', close);
    document.addEventListener('scroll', (e) => { if (open && !open.panel.contains(e.target)) place(); }, true);

    function scan(root) {
        (root.querySelectorAll ? root : document).querySelectorAll('select').forEach(enhance);
        if (root.tagName === 'SELECT') enhance(root);
    }
    // New rows ("Add line"), loaded kits, etc.
    new MutationObserver((muts) => muts.forEach((m) => m.addedNodes.forEach((n) => { if (n.nodeType === 1) scan(n); }))).observe(document.body, { childList: true, subtree: true });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => scan(document)); else scan(document);
    window.SearchableSelect = { enhance, scan };
})();
