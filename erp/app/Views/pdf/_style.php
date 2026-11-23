<style>
body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #222; }
h1 { font-size: 16pt; margin: 0; color: #0d6efd; }
table { width: 100%; border-collapse: collapse; }
.items th { background: #f1f3f5; border-bottom: 1px solid #999; padding: 5px; text-align: left; font-size: 9pt; }
.items td { border-bottom: 1px solid #ddd; padding: 5px; vertical-align: top; }
.r { text-align: right; } .muted { color: #666; font-size: 9pt; } .tot td { padding: 3px 5px; }
.box { border: 1px solid #ccc; padding: 8px; }
</style>
<table><tr>
    <td style="width:60%"><?= brand_img('logo', 'max-height:48px;max-width:180px;margin-bottom:4px') ?><h1><?= esc(setting('Solar.companyName')) ?></h1>
        <div class="muted"><?= nl2br(esc((string) setting('Solar.companyAddress'))) ?><br><?= esc((string) setting('Solar.companyPhone')) ?> <?= esc((string) setting('Solar.companyEmail')) ?>
        <?= setting('Solar.companyGstin') ? '<br>GSTIN: ' . esc(setting('Solar.companyGstin')) : '' ?></div></td>
    <td class="r" style="width:40%"><strong style="font-size:14pt"><?= esc($docTitle) ?></strong></td>
</tr></table>
<hr>
