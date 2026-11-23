<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>User Manual – <?= esc($topics[$slug][0]) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$groups = [];
foreach ($topics as $s => $t) {
    $groups[$t[2]][$s] = $t;
}
$keys = array_keys($topics);
$pos  = array_search($slug, $keys, true);
$prev = $keys[$pos - 1] ?? null;
$next = $keys[$pos + 1] ?? null;
?>
<style>
.manual h4 { margin-top: 1.6rem; font-size: 1.1rem; font-weight: 600; }
.manual h4:first-child { margin-top: 0; }
.manual ol.steps { counter-reset: s; list-style: none; padding-left: 0; }
.manual ol.steps > li { counter-increment: s; position: relative; padding: .15rem 0 .55rem 2.4rem; }
.manual ol.steps > li::before { content: counter(s); position: absolute; left: 0; top: .1rem; width: 1.7rem; height: 1.7rem; border-radius: 50%;
    background: var(--bs-primary); color: #fff; font-size: .85rem; font-weight: 600; display: flex; align-items: center; justify-content: center; }
.manual .path { background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-radius: .3rem; padding: 0 .35rem; font-size: .9em; white-space: nowrap; }
.manual .btnname { font-weight: 600; }
.manual table { font-size: .92rem; }
.manual-toc a.active { font-weight: 600; background: var(--bs-primary-bg-subtle); }
@media print { .sidebar, .topbar, .manual-toc, .manual-nav, .no-print { display: none !important; } .manual { border: 0 !important; } main { margin: 0 !important; } }
</style>
<div class="row g-3">
    <div class="col-lg-3 manual-toc no-print">
        <div class="card position-sticky" style="top:1rem">
            <div class="card-body pb-2"><input type="search" id="manual-search" class="form-control form-control-sm" placeholder="Search the manual…"></div>
            <div class="list-group list-group-flush small" id="manual-list" style="max-height:72vh;overflow:auto">
            <?php foreach ($groups as $g => $items): ?>
                <div class="list-group-item text-uppercase text-muted fw-semibold py-1 small bg-body-tertiary" data-group><?= esc($g) ?></div>
                <?php foreach ($items as $s => $t): ?>
                    <a href="<?= site_url('admin/manual/' . $s) ?>" class="list-group-item list-group-item-action py-2 <?= $s === $slug ? 'active-topic active' : '' ?>" data-title="<?= esc(strtolower($t[0] . ' ' . $t[3])) ?>">
                        <i class="bi <?= $t[1] ?> me-1"></i> <?= esc($t[0]) ?></a>
                <?php endforeach ?>
            <?php endforeach ?>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card manual">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <div><div class="h5 mb-0"><i class="bi <?= $topics[$slug][1] ?> text-primary"></i> <?= esc($topics[$slug][0]) ?></div>
                    <div class="small text-muted">For: <?= esc($topics[$slug][3]) ?></div></div>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            </div>
            <div class="card-body" id="manual-body"><?= view('admin/manual/' . $slug) ?></div>
            <div class="card-footer bg-white d-flex manual-nav">
                <?php if ($prev): ?><a href="<?= site_url('admin/manual/' . $prev) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= esc($topics[$prev][0]) ?></a><?php endif ?>
                <?php if ($next): ?><a href="<?= site_url('admin/manual/' . $next) ?>" class="btn btn-sm btn-primary ms-auto"><?= esc($topics[$next][0]) ?> <i class="bi bi-arrow-right"></i></a><?php endif ?>
            </div>
        </div>
    </div>
</div>
<script>
// Filter chapters by title; also searches the text of every chapter (loaded once on first search).
(function () {
    const box = document.getElementById('manual-search'), links = document.querySelectorAll('#manual-list a');
    let texts = null;
    async function load() {
        texts = {};
        await Promise.all([...links].map(async a => {
            try { const h = await (await fetch(a.href, {credentials: 'same-origin'})).text();
                const d = new DOMParser().parseFromString(h, 'text/html').getElementById('manual-body');
                texts[a.href] = d ? d.textContent.toLowerCase() : ''; } catch (e) { texts[a.href] = ''; }
        }));
    }
    function apply() {
        const q = box.value.trim().toLowerCase();
        links.forEach(a => { a.hidden = q !== '' && !a.dataset.title.includes(q) && !(texts && (texts[a.href] || '').includes(q)); });
        document.querySelectorAll('#manual-list [data-group]').forEach(g => {
            let n = g.nextElementSibling, any = false;
            while (n && !n.hasAttribute('data-group')) { if (!n.hidden) any = true; n = n.nextElementSibling; }
            g.hidden = !any;
        });
    }
    box.addEventListener('input', async function () { apply(); if (this.value.trim().length >= 3 && !texts) { await load(); apply(); } });
})();
</script>
<?= $this->endSection() ?>
