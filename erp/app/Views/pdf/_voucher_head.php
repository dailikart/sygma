<style>
body { font-family: dejavusans, sans-serif; font-size: 9.5pt; color: #111; }
table { width: 100%; border-collapse: collapse; }
.frame { border: 1px solid #333; padding: 10px 16px; }
.c { text-align: center; } .r { text-align: right; } .muted { color: #555; }
.title { font-size: 14pt; letter-spacing: 4px; font-weight: bold; text-decoration: underline; }
.lbl { width: 30%; font-style: italic; color: #333; vertical-align: top; padding: 4px 0; }
.val { vertical-align: top; padding: 4px 0; font-weight: bold; }
.lines td { border-bottom: 1px solid #ccc; padding: 3px 4px; }
.lines th { border-bottom: 1px solid #333; text-align: left; padding: 3px 4px; font-size: 8.5pt; }
</style>
<table><tr><td>No.: <strong><?= esc($v['voucher_no']) ?></strong></td><td class="r">Dated <strong><?= date('j-M-Y', strtotime($v['voucher_date'])) ?></strong></td></tr></table>
<div class="c" style="margin:4px 0 8px">
    <?= brand_img('logo', 'max-height:36px;max-width:150px') ?><br>
    <strong style="font-size:12pt"><?= esc((string) setting('Solar.companyName')) ?></strong><br>
    <span class="muted"><?= nl2br(esc((string) setting('Solar.companyAddress'))) ?>
    <?= setting('Solar.companyPhone') ? '<br>Ph: ' . esc(setting('Solar.companyPhone')) : '' ?><?= setting('Solar.companyEmail') ? ' &nbsp; E-Mail: ' . esc(setting('Solar.companyEmail')) : '' ?>
    <?= setting('Solar.companyGstin') ? '<br>GSTIN: ' . esc(setting('Solar.companyGstin')) : '' ?></span>
</div>
<div class="c" style="margin-bottom:10px"><span class="title"><?= esc($docTitle) ?></span></div>
