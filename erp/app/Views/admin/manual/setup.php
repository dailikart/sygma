<p>Do these once, in this order, before the team starts entering work. The Super Admin does this.</p>
<ol class="steps">
    <li><span class="path">Administration → Settings → Company</span>: company name, address, phone, email, GSTIN and <strong>state</strong>. The state decides CGST+SGST (same state) or IGST (other state) on every invoice and purchase.</li>
    <li><span class="path">Settings → Appearance</span>: theme colour, company <strong>logo</strong>, <strong>authorised signature</strong> and <strong>seal</strong>. They print on invoices, receipts, delivery notes and vouchers.</li>
    <li><span class="path">Settings → Email (SMTP)</span> and WhatsApp: see the chapter <a href="<?= site_url('admin/manual/email-whatsapp') ?>">Email &amp; WhatsApp setup</a>.</li>
    <li><span class="path">Administration → Users</span>: create a login for each staff member and tick their roles. One person can have several roles (e.g. Store + Delivery).</li>
    <li><span class="path">Administration → Roles &amp; Access</span>: change what each role can see, if the defaults do not suit you.</li>
    <li><span class="path">Accounts → Bank / Cash Accounts</span>: e.g. "HDFC Bank", "Cash", with opening balances. Use the same names as in Tally.</li>
    <li><span class="path">Accounts → Income Heads / Expense Heads</span>: e.g. "Fuel Expenses", "Salary", "Scrap Sale". Use <span class="btnname">Bulk upload</span> for a long list.</li>
    <li><span class="path">Inventory → Locations</span>: your stores / warehouses.</li>
    <li><span class="path">Inventory → Installation Types</span> (On-grid, Off-grid, Hybrid) → <span class="path">Item Categories</span> (Solar Panel, Inverter, Structure, Cable…) → <span class="path">Items</span> with HSN, GST %, purchase and sale price, minimum stock. Many items? Use <span class="btnname">Bulk upload</span>.</li>
    <li><span class="path">Inventory → Item Groups / Kits</span>: one kit per system size, e.g. "3 kW On-grid kit" = 6 panels + 1 inverter + 1 structure set + cable.</li>
    <li>Opening stock: enter it while creating the item, in the bulk upload, or in <span class="path">Inventory → Stock Adjustment</span> (type "Opening stock").</li>
    <li><span class="path">Purchase → Suppliers</span> with GSTIN, state and the opening balance you owe.</li>
    <li><span class="path">AMC / Service → AMC Plans</span>: e.g. "Free 5-year AMC", 60 months, a visit every 6 months. Tick <strong>Default</strong>.</li>
    <li><span class="path">Administration → Document Types</span>: the customer documents you collect (Aadhaar, EB bill, property tax…). Mark which are <strong>required</strong> for the PCR.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle"></i> After installing a new version of the software, go to <span class="path">Administration → Database Updates</span> and click <span class="btnname">Run database updates</span> when the yellow banner appears.</div>
