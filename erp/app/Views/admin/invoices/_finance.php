<?php $modes = \App\Libraries\Finance::MODES; $canEdit = $financeEditable && (can('invoices.manage') || can('projects.manage') || can('payments.manage')); ?>
<div class="card mb-3" id="loan">
    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-wallet2"></i> Payment mode:</span>
        <?php if ($inv['finance_mode'] === 'loan'): ?><span class="badge text-bg-primary">Bank loan</span> <span class="small"><?= esc((string) $inv['loan_bank']) ?> · <?= money($inv['loan_amount']) ?><?= $inv['loan_ref'] ? ' · Ref ' . esc($inv['loan_ref']) : '' ?></span>
        <?php elseif ($inv['finance_mode'] === 'cash'): ?><span class="badge text-bg-success">Cash / self-funded</span>
        <?php else: ?><span class="badge text-bg-warning">Not decided yet</span> <small class="text-danger">Set it before material is dispatched.</small><?php endif ?>
        <?php if ($canEdit): ?><button class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="collapse" data-bs-target="#financeForm" type="button">Change</button>
        <?php elseif (! $financeEditable && $inv['status'] !== 'cancelled'): ?><small class="ms-auto text-muted">Locked – material dispatched</small><?php endif ?>
    </div>
    <?php $ebEdit = $inv['status'] !== 'cancelled' && (can('invoices.manage') || can('payments.manage')); ?>
    <div class="card-body border-bottom py-2 small d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-lightning-charge"></i> EB / meter charges:</span>
        <?php if ($inv['eb_charges_by'] === 'company'): ?><span class="badge text-bg-light border">Paid by company</span> <span>–</span>
        <?php elseif ($inv['eb_charges_by'] === 'customer'): ?><span class="badge text-bg-info">Paid by customer</span> <strong><?= money($inv['eb_charges_amount']) ?></strong> beyond the invoice · total receivable <strong><?= money((float) $inv['total'] + (float) $inv['eb_charges_amount']) ?></strong>
        <?php else: ?><span class="badge text-bg-warning">Not decided</span><?php endif ?>
        <?php if ($ebEdit): ?>
        <form method="post" action="<?= site_url('admin/invoices/' . $inv['id'] . '/eb-charges') ?>" class="ms-auto d-flex gap-1 align-items-center"><?= csrf_field() ?>
            <select name="eb_charges_by" class="form-select form-select-sm eb-by" style="max-width:260px"><?= select_options(eb_charges_options(), (string) $inv['eb_charges_by']) ?></select>
            <input type="number" step="0.01" min="0" name="eb_charges_amount" class="form-control form-control-sm eb-amt" style="max-width:110px" placeholder="₹" value="<?= esc((string) ((float) $inv['eb_charges_amount'] ?: 5000)) ?>">
            <button class="btn btn-sm btn-outline-primary">Save</button></form>
        <script>document.querySelectorAll('.eb-by').forEach(s => { const a = s.form.querySelector('.eb-amt'), f = () => { a.hidden = s.value !== 'customer'; }; s.addEventListener('change', f); f(); });</script>
        <?php endif ?>
    </div>
    <?php if ($canEdit): ?>
    <form method="post" action="<?= site_url('admin/invoices/' . $inv['id'] . '/finance') ?>" class="card-body border-bottom collapse <?= $inv['finance_mode'] === '' ? 'show' : '' ?>" id="financeForm">
        <?= csrf_field() ?>
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">Payment mode</label><select name="finance_mode" class="form-select form-select-sm fin-mode"><?= select_options($modes, $inv['finance_mode']) ?></select></div>
            <div class="col-md-3 fin-loan"><label class="form-label small">Bank / lender</label><input name="loan_bank" class="form-control form-control-sm" value="<?= esc((string) $inv['loan_bank']) ?>"></div>
            <div class="col-md-2 fin-loan"><label class="form-label small">Loan amount</label><input type="number" step="0.01" name="loan_amount" class="form-control form-control-sm" value="<?= (float) $inv['loan_amount'] ?: '' ?>"></div>
            <div class="col-md-2 fin-loan"><label class="form-label small">Sanction ref.</label><input name="loan_ref" class="form-control form-control-sm" value="<?= esc((string) $inv['loan_ref']) ?>"></div>
            <div class="col-md-2"><label class="form-label small">Invoice format</label><select name="print_format" class="form-select form-select-sm"><?= select_options(['cumulative' => 'Cumulative', 'itemwise' => 'Item-wise'], $inv['print_format']) ?></select></div>
            <div class="col-12"><button class="btn btn-sm btn-primary">Save</button></div>
        </div>
    </form>
    <?php endif ?>
    <?php if ($inv['finance_mode'] === 'loan'): $sum = array_sum(array_column($tranches, 'amount')); $recd = array_sum(array_map(static fn ($t) => $t['status'] === 'received' ? (float) $t['amount'] : 0, $tranches)); ?>
    <form method="post" action="<?= site_url('admin/invoices/' . $inv['id'] . '/tranches') ?>">
        <?= csrf_field() ?>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle line-items" id="tr-rows" data-template="#tr-tpl">
            <thead><tr><th>#</th><th>Disbursement amount</th><th>Expected date</th><th>Status</th><th>Receipt</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tranches as $t): $edit = $t['status'] === 'pending' && (can('invoices.manage') || can('payments.manage')); ?>
                <tr><td><?= (int) $t['seq'] ?></td>
                    <td><?= $edit ? '<input type="number" step="0.01" name="t[' . $t['id'] . '][amount]" value="' . esc($t['amount']) . '" class="form-control form-control-sm" style="max-width:150px">' : money($t['amount']) ?></td>
                    <td><?= $edit ? '<input type="date" name="t[' . $t['id'] . '][expected_date]" value="' . esc((string) $t['expected_date']) . '" class="form-control form-control-sm" style="max-width:160px">' : fdate($t['expected_date']) ?></td>
                    <td><?= $t['status'] === 'received' ? '<span class="badge text-bg-success">Received ' . fdate($t['payment_date']) . '</span>' : '<span class="badge text-bg-secondary">Pending</span>' ?></td>
                    <td><?= $t['receipt_no'] ? '<a href="' . site_url('admin/vouchers/print/payment/' . $t['payment_id']) . '" target="_blank">' . esc($t['receipt_no']) . '</a>' : '' ?></td>
                    <td><?= $edit ? '<input type="checkbox" class="btn-check" name="delete[]" value="' . $t['id'] . '" id="del-tr-' . $t['id'] . '" autocomplete="off"><label class="btn btn-sm btn-outline-danger" for="del-tr-' . $t['id'] . '" title="Tick, then Save schedule"><i class="bi bi-trash"></i> Remove</label>' : '' ?></td></tr>
            <?php endforeach ?>
            </tbody>
            <tfoot><tr class="small"><td></td><td colspan="5">Scheduled <?= money($sum) ?> of loan <?= money($inv['loan_amount']) ?> · Received <?= money($recd) ?>
                <?php if (abs($sum - (float) $inv['loan_amount']) > 0.01): ?><span class="text-danger"> · schedule does not match the loan amount</span><?php endif ?></td></tr></tfoot>
        </table></div>
        <template id="tr-tpl"><tr><td>new</td><td><input type="number" step="0.01" name="t[new__i__][amount]" class="form-control form-control-sm" style="max-width:150px"></td>
            <td><input type="date" name="t[new__i__][expected_date]" class="form-control form-control-sm" style="max-width:160px"></td><td colspan="2"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row><i class="bi bi-x"></i></button></td></tr></template>
        <?php if (can('invoices.manage') || can('payments.manage')): ?>
        <div class="card-body py-2 d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#tr-rows"><i class="bi bi-plus-lg"></i> Add disbursement</button>
            <button class="btn btn-sm btn-primary ms-auto">Save schedule</button></div>
        <div class="card-body pt-0 small text-muted">When the bank pays, record it with <strong>Payment</strong> → mode "Loan disbursement from bank" and pick the disbursement.</div>
        <?php endif ?>
    </form>
    <?php endif ?>
</div>
<script>
document.querySelectorAll('.fin-mode').forEach(function (s) {
    const f = () => s.closest('form').querySelectorAll('.fin-loan').forEach(e => e.style.display = s.value === 'loan' ? '' : 'none');
    s.addEventListener('change', f); f();
});
document.getElementById('tranche')?.addEventListener('change', function () {
    const o = this.selectedOptions[0]; if (o && o.dataset.amount) { document.querySelector('[name=amount]').value = o.dataset.amount; document.getElementById('pay_mode').value = 'loan'; }
});
</script>
