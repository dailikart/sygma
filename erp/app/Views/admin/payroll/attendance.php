<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Attendance · <?= date('F Y', strtotime($month)) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$days  = (int) date('t', strtotime($month));
$st    = \App\Libraries\Payroll::STATUSES;
$edit  = ! $locked && can('payroll.manage');
$prev  = date('Y-m', strtotime($month . ' -1 month'));
$next  = date('Y-m', strtotime($month . ' +1 month'));
$dow   = static fn (int $d) => date('D', strtotime(substr($month, 0, 8) . str_pad((string) $d, 2, '0', STR_PAD_LEFT)));
?>
<style>
.att td.c, .att th.c { width: 28px; min-width: 28px; padding: 2px; text-align: center; font-size: 12px; }
.att td.c { cursor: <?= $edit ? 'pointer' : 'default' ?>; user-select: none; font-weight: 600; }
.att .s-P { background: #d1fae5; color: #065f46 } .att .s-A { background: #fee2e2; color: #991b1b } .att .s-H { background: #fef3c7; color: #92400e }
.att .s-L { background: #dbeafe; color: #1e40af } .att .s-W, .att .s-O { background: #e5e7eb; color: #374151 } .att .wk { background: #f3f4f6 }
.att th.sticky, .att td.sticky { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 170px }
</style>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/attendance?month=' . $prev) ?>"><i class="bi bi-chevron-left"></i></a>
    <form method="get"><input type="month" name="month" value="<?= substr($month, 0, 7) ?>" class="form-control form-control-sm" onchange="this.form.submit()"></form>
    <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/attendance?month=' . $next) ?>"><i class="bi bi-chevron-right"></i></a>
    <span class="small ms-2"><?php foreach ($st as $k => [$label]): ?><span class="badge s-<?= $k ?> me-1" style="color:inherit"><span class="att"><span class="s-<?= $k ?> px-1"><?= $k ?></span></span> <?= $label ?></span><?php endforeach ?></span>
    <?php if ($run): ?><a href="<?= site_url('admin/payroll/' . $run['id']) ?>" class="btn btn-sm btn-outline-primary ms-auto">Salary <?= esc($run['run_no']) ?> <?= status_badge($run['status']) ?></a><?php endif ?>
</div>
<?php if ($locked): ?><div class="alert alert-info py-2 small">The salary for this month is approved, so the sheet is locked.</div><?php endif ?>
<?php if ($edit): ?>
<div class="d-flex flex-wrap gap-2 mb-2 small">
    <button type="button" class="btn btn-sm btn-outline-success" id="fillAll"><i class="bi bi-magic"></i> Fill blanks: present, <?= esc($weekOff) ?> week off</button>
    <span class="text-muted align-self-center">Click a cell to change it (P → A → H → L → W → O → blank). Click a date to set that day for everyone (e.g. a holiday).</span>
</div>
<?php endif ?>
<form method="post" action="<?= site_url('admin/attendance') ?>"><?= csrf_field() ?><input type="hidden" name="month" value="<?= substr($month, 0, 7) ?>">
<div class="card"><div class="table-responsive"><table class="table table-sm table-bordered mb-0 att align-middle">
    <thead><tr><th class="sticky">Employee</th>
        <?php for ($d = 1; $d <= $days; $d++): ?><th class="c <?= $dow($d) === $weekOff ? 'wk' : '' ?> <?= $edit ? 'colset' : '' ?>" data-d="<?= $d ?>" style="<?= $edit ? 'cursor:pointer' : '' ?>"><?= $d ?><br><span class="text-muted fw-normal"><?= substr($dow($d), 0, 2) ?></span></th><?php endfor ?>
        <th class="text-end">Paid days</th><th style="width:70px">OT hrs</th></tr></thead>
    <tbody>
    <?php foreach ($emps as $e): ?>
        <tr data-e="<?= $e['id'] ?>" data-type="<?= $e['pay_type'] ?>">
            <td class="sticky small"><a href="<?= site_url('admin/employees/' . $e['id']) ?>"><?= esc($e['name']) ?></a><div class="text-muted"><?= esc($e['emp_code']) ?><?= $e['pay_type'] === 'daily' ? ' · daily' : '' ?></div></td>
            <?php for ($d = 1; $d <= $days; $d++): $s = $marks[$e['id']][$d] ?? ''; ?>
                <td class="c s-<?= $s ?> <?= $dow($d) === $weekOff ? 'wk' : '' ?>" data-d="<?= $d ?>" data-wk="<?= $dow($d) === $weekOff ? 1 : 0 ?>"><?= $s ?><input type="hidden" name="att[<?= $e['id'] ?>][<?= $d ?>]" value="<?= $s ?>"></td>
            <?php endfor ?>
            <td class="text-end small paid"></td>
            <td><input type="number" step="0.5" min="0" name="ot[<?= $e['id'] ?>]" value="<?= (float) ($ot[$e['id']] ?? 0) ?: '' ?>" class="form-control form-control-sm py-0" <?= $edit ? '' : 'disabled' ?>></td>
        </tr>
    <?php endforeach ?>
    <?php if (! $emps): ?><tr><td colspan="<?= $days + 3 ?>" class="text-center text-muted py-4">No employees for this month. <a href="<?= site_url('admin/employees/new') ?>">Add employees</a> first.</td></tr><?php endif ?>
    </tbody>
</table></div></div>
<?php if ($edit && $emps): ?><button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Save attendance</button>
<span class="small text-muted ms-2">Blank days count as worked for monthly staff, and as not worked for daily-wage staff.</span><?php endif ?>
</form>
<script>
(() => {
    const order = ['', 'P', 'A', 'H', 'L', 'W', 'O'], days = <?= $days ?>, edit = <?= $edit ? 'true' : 'false' ?>;
    const set = (td, s) => { td.className = td.className.replace(/\bs-\w?\b/g, '').trim() + ' s-' + s; td.firstChild.nodeValue = s; td.querySelector('input').value = s; };
    const paid = tr => {
        let a = 0, h = 0, p = 0, l = 0, any = 0;
        tr.querySelectorAll('td.c').forEach(td => { const v = td.querySelector('input').value; if (v) any++; if (v === 'A') a++; if (v === 'H') h++; if (v === 'P') p++; if (v === 'L') l++; });
        tr.querySelector('.paid').textContent = tr.dataset.type === 'daily' ? (p + h / 2 + l) : (days - a - h / 2);
    };
    document.querySelectorAll('.att tbody tr[data-e]').forEach(tr => {
        tr.querySelectorAll('td.c').forEach(td => { if (! td.firstChild || td.firstChild.nodeType !== 3) td.insertBefore(document.createTextNode(''), td.firstChild); });
        paid(tr);
    });
    if (! edit) return;
    document.querySelector('.att tbody').addEventListener('click', e => {
        const td = e.target.closest('td.c'); if (! td) return;
        set(td, order[(order.indexOf(td.querySelector('input').value) + 1) % order.length]); paid(td.parentElement);
    });
    document.querySelectorAll('th.colset').forEach(th => th.addEventListener('click', () => {
        const cells = [...document.querySelectorAll('td.c[data-d="' + th.dataset.d + '"]')]; if (! cells.length) return;
        const next = order[(order.indexOf(cells[0].querySelector('input').value) + 1) % order.length];
        cells.forEach(td => { set(td, next); paid(td.parentElement); });
    }));
    document.getElementById('fillAll')?.addEventListener('click', () => document.querySelectorAll('.att tbody tr[data-e]').forEach(tr => {
        tr.querySelectorAll('td.c').forEach(td => { if (! td.querySelector('input').value) set(td, td.dataset.wk === '1' ? 'W' : 'P'); }); paid(tr);
    }));
})();
</script>
<?= $this->endSection() ?>
