<?= $this->extend('admin/layout') ?>
<?= $this->section('title') ?>Message Log<?= $this->endSection() ?>
<?= $this->section('content') ?>
<p class="text-muted">Last 300 customer emails and WhatsApp messages with their result. WhatsApp messages are sent by the queue worker (<code>php spark queue:work whatsapp</code>).</p>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Time</th><th>Channel</th><th>To</th><th>Event</th><th>Status</th><th>Response</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= fdate($r['created_at'], true) ?></td><td><?= esc($r['channel']) ?></td><td><?= esc($r['recipient']) ?></td><td><?= esc((string) $r['event']) ?></td>
        <td><?= $r['status'] === 'sent' ? '<span class="badge text-bg-success">sent</span>' : '<span class="badge text-bg-danger">failed</span>' ?></td><td><small class="text-muted text-break"><?= esc(mb_strimwidth((string) $r['response'], 0, 160, '…')) ?></small></td></tr><?php endforeach ?>
    <?php if (! $rows): ?><tr><td colspan="6" class="text-muted text-center py-3">No messages yet.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
