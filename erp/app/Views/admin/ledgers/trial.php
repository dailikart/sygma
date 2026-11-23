<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Trial Balance<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex mb-3 align-items-end"><?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?><span class="ms-3 text-muted small">Closing balances as on <?= fdate($to) ?></span></div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Ledger</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead><tbody>
    <?php $dr = 0; $cr = 0; $group = null; foreach ($rows as $r): if ($r['balance'] > 0) { $dr += $r['balance']; } else { $cr -= $r['balance']; } ?>
        <?php if ($r['group_name'] !== $group): $group = $r['group_name']; ?><tr class="table-light"><td colspan="3" class="fw-semibold"><?= esc($group) ?></td></tr><?php endif ?>
        <tr><td class="ps-4"><a href="<?= site_url('admin/ledgers/' . $r['id'] . '?to=' . $to . '&from=' . $from) ?>" class="text-reset"><?= esc($r['name']) ?></a></td>
            <td class="text-end"><?= $r['balance'] > 0 ? money($r['balance'], false) : '' ?></td><td class="text-end"><?= $r['balance'] < 0 ? money(-$r['balance'], false) : '' ?></td></tr>
    <?php endforeach ?>
    </tbody><tfoot class="fw-bold"><tr class="<?= abs($dr - $cr) > 0.01 ? 'text-danger' : '' ?>"><td class="text-end">Total</td><td class="text-end"><?= money($dr) ?></td><td class="text-end"><?= money($cr) ?></td></tr></tfoot>
</table></div></div>
<?php if (abs($dr - $cr) > 0.01): ?><div class="alert alert-warning mt-3">Debit and credit differ by <?= money(abs($dr - $cr)) ?>. This comes from opening balances that are not matched (e.g. supplier/bank openings without a capital ledger). Add the difference to a Capital Account ledger opening balance.</div><?php endif ?>
<?= $this->endSection() ?>
