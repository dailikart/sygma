<?php /* Date range filter. Keeps other query params. */ $qs = service('request')->getGet(); unset($qs['from'], $qs['to']); ?>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end">
    <?php foreach ($qs as $k => $v): if (is_string($v)): ?><input type="hidden" name="<?= esc($k) ?>" value="<?= esc($v) ?>"><?php endif; endforeach ?>
    <div><label class="form-label small mb-0">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-0">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-outline-secondary">Apply</button>
</form>
