<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?><?= esc($title) ?>: <?= $row ? 'Edit' : 'Add' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('admin/' . $uri . '/save' . ($row ? '/' . $row['id'] : '')) ?>" class="card card-body" style="max-width:860px">
    <?= csrf_field() ?>
    <div class="row g-3">
    <?php foreach ($fields as $name => $def): [$label, $type] = $def; $val = old($name, $row[$name] ?? ($type === 'bool' ? 1 : '')); ?>
        <?php if ($type === 'bool'): ?>
            <div class="col-12"><div class="form-check"><input type="hidden" name="<?= $name ?>" value="0">
                <input class="form-check-input" type="checkbox" name="<?= $name ?>" value="1" id="f_<?= $name ?>" <?= $val ? 'checked' : '' ?>>
                <label class="form-check-label" for="f_<?= $name ?>"><?= esc($label) ?></label></div></div>
        <?php elseif ($type === 'textarea'): ?>
            <div class="col-12"><label class="form-label"><?= esc($label) ?></label><textarea name="<?= $name ?>" class="form-control" rows="2"><?= esc((string) $val) ?></textarea></div>
        <?php elseif ($type === 'select'): $opts = $def[2]; if (array_is_list($opts)) { $opts = array_combine($opts, $opts); } ?>
            <div class="col-md-6"><label class="form-label"><?= esc($label) ?></label>
                <select name="<?= $name ?>" class="form-select"><?= select_options($opts, $val, '— Select —') ?></select>
                <?php if (! $opts && $name === 'opening_location_id'): ?><div class="form-text text-danger">No locations yet – <a href="<?= site_url('admin/locations/new') ?>">add a store location</a> first.</div><?php endif ?></div>
        <?php else: ?>
            <div class="col-md-6"><label class="form-label"><?= esc($label) ?></label>
                <input type="<?= $type === 'number' ? 'number' : $type ?>" <?= $type === 'number' ? 'step="0.01"' : '' ?> name="<?= $name ?>" class="form-control" value="<?= esc((string) $val) ?>"></div>
        <?php endif ?>
    <?php endforeach ?>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save</button> <a href="<?= site_url('admin/' . $uri) ?>" class="btn btn-light">Cancel</a></div>
</form>
<?= $this->endSection() ?>
