<?php $qs = service('request')->getGet(); unset($qs['export']); ?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-end no-print">
    <?php if (isset($from)): ?><?= view('admin/partials/range', ['from' => $from, 'to' => $to]) ?><?php endif ?>
    <?= $extra ?? '' ?>
    <div class="ms-auto d-flex gap-2">
        <a href="?<?= esc(http_build_query($qs + ['export' => 'excel'])) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>
