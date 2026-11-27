<style>
@page { margin: 18mm 18mm 16mm 18mm; footer: html_aftr; }
body { font-family: dejavusans, sans-serif; font-size: 9.8pt; color: #111; line-height: 1.5; }
</style>
<htmlpagefooter name="aftr"><table style="width:100%;font-size:7.5pt;color:#555;border-top:0.2mm solid #ccc"><tr><td style="padding-top:1mm">Consumer signature: ____________________</td>
    <td style="text-align:center;padding-top:1mm"><?= esc($q['quote_no']) ?> · Page {PAGENO} of {nbpg}</td><td style="text-align:right;padding-top:1mm">Vendor signature: ____________________</td></tr></table></htmlpagefooter>
<?php if (! empty($a['stamp'])): ?><div style="height:120mm"></div><?php endif ?>
<?= $body ?>
