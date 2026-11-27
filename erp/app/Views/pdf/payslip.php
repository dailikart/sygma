<?= view('pdf/_style', ['docTitle' => 'PAYSLIP']) ?>
<?php foreach ($lines as $i => $l): $earn = [['Basic', $l['basic']], ['HRA', $l['hra']], ['Conveyance', $l['conveyance']], ['Special allowance', $l['special']], ['Wages', $l['wages']], ['Overtime (' . qty($l['ot_hours']) . ' hrs)', $l['ot_amount']], ['Incentive / bonus', $l['other_earnings']]];
    $ded = [['Provident fund', $l['pf_employee']], ['ESI', $l['esi_employee']], ['Professional tax', $l['pt']], ['TDS', $l['tds']], ['Advance recovery', $l['advance_recovery']], ['Other deductions', $l['other_deductions']]];
    $earn = array_values(array_filter($earn, static fn ($x) => (float) $x[1] != 0)); $ded = array_values(array_filter($ded, static fn ($x) => (float) $x[1] != 0)); ?>
<?php if ($i > 0): ?><pagebreak /><?= view('pdf/_style', ['docTitle' => 'PAYSLIP']) ?><?php endif ?>
<p style="margin:0 0 8px"><strong>Payslip for <?= date('F Y', strtotime($run['month'])) ?></strong><?= $run['status'] === 'draft' ? ' <span style="color:#c00">(DRAFT – not final)</span>' : '' ?></p>
<table class="box" style="font-size:9.5pt"><tr>
    <td style="width:50%">Employee: <strong><?= esc($l['name']) ?></strong><br>Code: <?= esc($l['emp_code']) ?><br>Designation: <?= esc((string) $l['designation']) ?><?= $l['department'] ? ' · ' . esc($l['department']) : '' ?><br>Date of joining: <?= fdate($l['join_date']) ?></td>
    <td style="width:50%">PAN: <?= esc((string) $l['pan']) ?: '—' ?><br>UAN: <?= esc((string) $l['uan']) ?: '—' ?> &nbsp; ESI: <?= esc((string) $l['esi_no']) ?: '—' ?><br>Bank: <?= esc(trim($l['bank_name'] . ' ' . $l['bank_account'])) ?: '—' ?><br>
        Days in month: <?= (int) $l['days_in_month'] ?> · Paid days: <strong><?= qty($l['paid_days']) ?></strong><?= (float) $l['absent'] ? ' · Absent: ' . qty($l['absent']) : '' ?><?= (float) $l['leave_days'] ? ' · Leave: ' . qty($l['leave_days']) : '' ?></td>
</tr></table>
<br>
<table class="items"><thead><tr><th style="width:32%">Earnings</th><th class="r" style="width:18%">₹</th><th style="width:32%">Deductions</th><th class="r" style="width:18%">₹</th></tr></thead><tbody>
<?php for ($r = 0; $r < max(count($earn), count($ded), 1); $r++): ?>
    <tr><td><?= esc($earn[$r][0] ?? '') ?></td><td class="r"><?= isset($earn[$r]) ? money($earn[$r][1], false) : '' ?></td><td><?= esc($ded[$r][0] ?? '') ?></td><td class="r"><?= isset($ded[$r]) ? money($ded[$r][1], false) : '' ?></td></tr>
<?php endfor ?>
    <tr><td><strong>Gross earnings</strong></td><td class="r"><strong><?= money($l['gross'], false) ?></strong></td><td><strong>Total deductions</strong></td><td class="r"><strong><?= money($l['total_deductions'], false) ?></strong></td></tr>
</tbody></table>
<table class="tot" style="margin-top:8px"><tr><td style="width:60%" class="muted"><?= amount_in_words((float) $l['net']) ?></td><td class="r" style="font-size:13pt"><strong>Net pay: <?= money($l['net']) ?></strong></td></tr></table>
<?php if ((float) $l['pf_employer'] || (float) $l['esi_employer']): ?><p class="muted">Employer contribution (not deducted from you): PF <?= money($l['pf_employer']) ?><?= (float) $l['esi_employer'] ? ', ESI ' . money($l['esi_employer']) : '' ?>.</p><?php endif ?>
<?= $l['remarks'] ? '<p class="muted">Note: ' . esc($l['remarks']) . '</p>' : '' ?>
<br><table><tr><td class="muted">This is a computer generated payslip and needs no signature.</td><td class="r">For <?= esc((string) setting('Solar.companyName')) ?></td></tr></table>
<?php endforeach ?>
