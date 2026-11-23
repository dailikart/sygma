<p>Add many records at once from Excel: <strong>Items</strong>, <strong>Customers</strong>, <strong>Suppliers</strong>, <strong>Income Heads</strong> and <strong>Expense Heads</strong>.</p>
<ol class="steps">
    <li>Open the list (e.g. <span class="path">Inventory → Items</span>) → <span class="btnname">Bulk upload</span>.</li>
    <li><span class="btnname">Download the Excel template</span>. It has the column headings and one sample row. The items template also has a <em>Valid values</em> sheet with your installation types, categories, locations and units.</li>
    <li>Fill one row per record and delete the sample row. Columns marked <strong>*</strong> are required. Keep the headings as they are.</li>
    <li>Save as <strong>.xlsx</strong> (or .csv) and upload it.</li>
    <li>Tick <strong>Update records that already exist</strong> only if you want existing ones changed; otherwise they are skipped.</li>
    <li><span class="btnname">Upload</span>. You get "X added, Y updated, Z skipped".</li>
</ol>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> The whole file is checked first. If any row has a problem, <strong>nothing is saved</strong> and every problem is listed with its Excel row number, e.g. "Row 5: GST % must be a number". Fix the file and upload again.</div>
<table class="table table-sm">
    <thead><tr><th>Upload</th><th>Existing record matched by</th><th>Notes</th></tr></thead>
    <tr><td>Items</td><td>Item code / SKU (or name if no code)</td><td>New installation types and categories are created automatically. The opening stock location must already exist. Opening stock is added only for new items.</td></tr>
    <tr><td>Customers</td><td>Name + phone</td><td>Type must be B2B or B2C.</td></tr>
    <tr><td>Suppliers</td><td>Name</td><td>GSTIN must be 15 characters.</td></tr>
    <tr><td>Income / Expense heads</td><td>Name</td><td></td></tr>
</table>
