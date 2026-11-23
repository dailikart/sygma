<?= view('pdf/_style', ['docTitle' => 'DISPATCH LIST']) ?>
<table><tr><td style="width:55%" class="box"><span class="muted">Deliver to</span><br><strong><?= esc($customer['name']) ?></strong><br><?= esc((string) $project['site_address']) ?><br>Ph: <?= esc((string) $customer['phone']) ?></td>
<td class="box">Dispatch No: <strong><?= esc($d['dispatch_no']) ?></strong><br>Project: <?= esc($project['project_no']) ?> (<?= qty($project['capacity_kw']) ?> kW)<br>From store: <?= esc($location['name']) ?><br>Date: <?= fdate($d['dispatched_at'] ?? $d['created_at']) ?><br>Vehicle: <?= esc((string) $d['vehicle_no']) ?> &nbsp; Driver: <?= esc((string) $d['driver_name']) ?></td></tr></table><br>
<table class="items"><thead><tr><th>#</th><th>Item</th><th>Category</th><th class="r">Qty</th><th>Unit</th><th>Loaded ✓</th><th>Received ✓</th></tr></thead><tbody>
<?php foreach ($lines as $n => $l): ?><tr><td><?= $n + 1 ?></td><td><?= esc($l['name']) ?><?= $l['sku'] ? ' <span class="muted">' . esc($l['sku']) . '</span>' : '' ?></td><td><?= esc((string) $l['category']) ?></td><td class="r"><?= qty($l['qty']) ?></td><td><?= esc($l['unit']) ?></td><td>☐</td><td>☐</td></tr><?php endforeach ?>
</tbody></table>
<br><br><table><tr><td>Store in-charge<br><br><br>________________</td><td>Driver<br><br><br>________________</td><td class="r">Received by customer<br><br><br>________________</td></tr></table>
