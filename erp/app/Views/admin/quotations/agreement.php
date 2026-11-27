<?= $this->extend('admin/layout') ?>
<?php
use App\Libraries\Agreement;
use App\Libraries\QuickQuote as QQ;

$v = static fn ($k) => old($k, $a[$k] ?? '');
$stages = old('stages', $a['stages']);
?>
<?= $this->section('title') ?>Agreement · <?= esc($q['quote_no']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="<?= site_url('admin/quotations/' . $q['id']) ?>" class="btn btn-light"><i class="bi bi-arrow-left"></i> <?= esc($q['quote_no']) ?></a>
    <div class="small text-muted"><?= esc($q['party_name']) ?> · <?= qty($q['capacity_kw']) ?> kW · <?= money($q['total']) ?></div>
    <?php if ($a['saved']): ?><div class="ms-auto btn-group"><a href="<?= site_url('admin/quotations/' . $q['id'] . '/agreement/pdf') ?>" target="_blank" class="btn btn-primary" id="openPdf"><i class="bi bi-file-earmark-pdf"></i> Open agreement PDF</a>
        <a href="<?= site_url('admin/quotations/' . $q['id'] . '/agreement/pdf?download=1') ?>" class="btn btn-outline-primary"><i class="bi bi-download"></i></a></div><?php endif ?>
</div>
<form method="post" action="<?= site_url('admin/quotations/' . $q['id'] . '/agreement') ?>" style="max-width:980px">
<?= csrf_field() ?>
<div class="card mb-3"><div class="card-header bg-white fw-semibold">Customer details for the agreement</div><div class="card-body row g-3">
    <div class="col-md-3"><label class="form-label">Agreement date</label><input type="date" name="date" class="form-control" value="<?= esc((string) $v('date')) ?>"></div>
    <div class="col-md-5"><label class="form-label">Father's / husband's name</label><input name="father_name" class="form-control" value="<?= esc((string) $v('father_name')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Aadhaar number</label><input name="aadhaar" class="form-control" inputmode="numeric" maxlength="14" placeholder="1234 5678 9012" value="<?= esc((string) $v('aadhaar')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Customer address</label><input name="address" class="form-control" value="<?= esc((string) $v('address')) ?>"></div>
    <div class="col-md-2"><label class="form-label">PIN code</label><input name="pincode" class="form-control" inputmode="numeric" maxlength="6" value="<?= esc((string) $v('pincode')) ?>"></div>
    <div class="col-md-4"><label class="form-label">EB consumer number</label><input name="eb_number" class="form-control" value="<?= esc((string) $v('eb_number')) ?>"></div>
    <div class="col-12 small text-muted">Name, system size, panel and inverter makes, total cost and your company details come from the quotation automatically. Blank details print as a line to fill by hand.</div>
</div></div>

<div class="card mb-3"><div class="card-header bg-white fw-semibold">Payment stages for this customer <span class="fw-normal small text-muted">percent of <?= money($q['total']) ?>; must add up to 100%</span></div>
    <div class="card-body"><div id="stages">
    <?php foreach ($stages as $k => $s): ?>
        <div class="border rounded p-2 mb-2 stage"><div class="d-flex gap-2 mb-2"><input class="form-control form-control-sm" name="stages[<?= $k ?>][name]" value="<?= esc($s['name']) ?>">
            <div class="input-group input-group-sm" style="width:130px"><input type="number" step="0.01" min="0" class="form-control text-end pct" name="stages[<?= $k ?>][pct]" value="<?= esc(QQ::fmt((float) $s['pct'])) ?>"><span class="input-group-text">%</span></div>
            <span class="small text-muted align-self-center amt" style="width:110px"></span><button type="button" class="btn btn-sm btn-outline-danger del">✕</button></div>
            <textarea class="form-control form-control-sm" rows="2" name="stages[<?= $k ?>][text]"><?= esc((string) $s['text']) ?></textarea></div>
    <?php endforeach ?></div>
    <template id="stageTpl"><div class="border rounded p-2 mb-2 stage"><div class="d-flex gap-2 mb-2"><input class="form-control form-control-sm" name="stages[__i__][name]" placeholder="Stage name"><div class="input-group input-group-sm" style="width:130px"><input type="number" step="0.01" min="0" class="form-control text-end pct" name="stages[__i__][pct]"><span class="input-group-text">%</span></div><span class="small text-muted align-self-center amt" style="width:110px"></span><button type="button" class="btn btn-sm btn-outline-danger del">✕</button></div><textarea class="form-control form-control-sm" rows="2" name="stages[__i__][text]" placeholder="When it is paid"></textarea></div></template>
    <button type="button" class="btn btn-sm btn-outline-primary" id="addStage">+ Add stage</button> <span class="ms-2 fw-semibold small" id="stageTotal"></span></div></div>

<div class="card mb-3"><div class="card-header bg-white fw-semibold">Agreement text</div><div class="card-body">
    <div class="form-check mb-2"><input type="hidden" name="stamp" value="0"><input class="form-check-input" type="checkbox" name="stamp" value="1" id="stamp" <?= $v('stamp') ? 'checked' : '' ?>><label class="form-check-label" for="stamp">Print on stamp paper: leave the top half of page 1 blank</label></div>
    <div class="small text-muted mb-1">Click where the detail should go in the text, then click the detail:</div>
    <div class="d-flex flex-wrap gap-1 mb-2"><?php foreach (Agreement::PLACEHOLDERS as $k => $l): ?><button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ph" data-ph="{<?= $k ?>}"><?= esc($l) ?></button><?php endforeach ?></div>
    <div class="small text-muted mb-1"><code># title</code> · <code>## centred line</code> · <code>### underlined heading</code> · <code>- numbered clause</code> · <code>&gt; small note</code> · <code>**bold**</code></div>
    <textarea name="text" id="agText" class="form-control font-monospace" rows="18"><?= esc((string) $v('text')) ?></textarea>
    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="reset_text" value="1" id="rt"><label class="form-check-label small" for="rt">Replace with the standard text from Quick Quote Prices → Terms &amp; agreement</label></div>
</div></div>
<button class="btn btn-primary" name="open" value="1"><i class="bi bi-file-earmark-check"></i> Save &amp; open agreement</button>
<button class="btn btn-outline-secondary">Save</button>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    const total = <?= json_encode((float) $q['total']) ?>, box = document.getElementById('stages');
    let seq = 100;
    const money = n => '₹' + Math.round(n).toLocaleString('en-IN');
    function sum() {
        let t = 0;
        box.querySelectorAll('.stage').forEach(st => { const p = parseFloat(st.querySelector('.pct').value) || 0; t += p; st.querySelector('.amt').textContent = money(total * p / 100); });
        const el = document.getElementById('stageTotal'), ok = Math.abs(t - 100) < 0.01;
        el.textContent = 'Total: ' + Math.round(t * 100) / 100 + '%' + (ok ? ' ✓' : ' (should be 100%)'); el.className = 'ms-2 fw-semibold small ' + (ok ? 'text-success' : 'text-danger');
    }
    document.getElementById('addStage').addEventListener('click', () => { box.insertAdjacentHTML('beforeend', document.getElementById('stageTpl').innerHTML.replaceAll('__i__', 'n' + (seq++))); sum(); });
    box.addEventListener('click', e => { if (e.target.classList.contains('del')) { e.target.closest('.stage').remove(); sum(); } });
    box.addEventListener('input', sum); sum();
    const ta = document.getElementById('agText');
    document.querySelectorAll('.ph').forEach(b => b.addEventListener('click', () => {
        const s = ta.selectionStart, e = ta.selectionEnd, t = b.dataset.ph;
        ta.value = ta.value.slice(0, s) + t + ta.value.slice(e); ta.focus(); ta.selectionStart = ta.selectionEnd = s + t.length;
    }));
    <?php if (session('open_pdf')): ?>const o = document.getElementById('openPdf'); if (o) window.open(o.href, '_blank');<?php endif ?>
})();
</script>
<?= $this->endSection() ?>
