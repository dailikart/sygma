<p>The latest additions to Sygma Solar Suite, newest first. Each item links to its chapter for the full steps.</p>

<h4><i class="bi bi-boxes"></i> Quick Quote: stock items, versions and the agreement</h4>
<ul>
    <li><strong>Items fetched automatically.</strong> The quick quote now fills the quotation with your real Inventory items: panels, inverter, structure, DCDB, ACDB, cables, earthing, lightning arrestor and so on, with the right quantity, HSN and GST rate. The live summary lists each item and its stock. The invoice made from the quotation then dispatches the right stock. Link items in <span class="path">Quick Quote Prices → Items</span> (or on each panel / inverter price row); anything not linked is matched by its name, or added as a typed line.</li>
    <li><strong>Versions.</strong> <em>Revise</em> saves the changed quotation as a new version (<code>QT/2026-27/0003-R1</code>, <code>-R2</code> …) and keeps the old one. The list shows only the latest version (tick <em>Show earlier versions</em> to see all); every version lists the others on its page. Older versions cannot be changed, accepted or converted, and Sales Team Progress counts each quotation once.</li>
    <li><strong>Agreement.</strong> <em>Agreement</em> on a quotation makes the consumer–vendor agreement for PM Surya Ghar: name, address, EB number, system size, panel and inverter makes and the total come from the quotation; type the father's / husband's name, Aadhaar and date, adjust the payment stages for this customer, and print it (optionally leaving the top half of page 1 blank for stamp paper). Edit the standard text in <span class="path">Quick Quote Prices → Terms &amp; agreement</span>.</li>
</ul>

<h4><i class="bi bi-lightning-charge"></i> Quick Quote: the full proposal in one click</h4>
<ul>
    <li><span class="path">Sales → Quick Quote</span> (or <em>Quick Quote</em> on a lead / customer): type the customer, then either the bi-monthly units from the EB bill or the kW size, and tap the building type, system type (On-Grid DCR / NDCR, Hybrid, Micro inverter), panel technology, wattage, brand and inverter. The price, subsidy, net cost and payback show live on the right.</li>
    <li><strong>Generate Quotation</strong> makes a 4-page PDF: cover letter, bill of materials with makes, total with blended GST and PM Surya Ghar + state subsidy, "how your system works", savings, payback year and break-even chart, and terms with payment stages, warranty and bank details.</li>
    <li>It is saved as a normal quotation (two GST lines: supply at 5% and installation at 18%), so WhatsApp, email, the customer link and <em>Convert to invoice</em> work as before. <em>Revise</em> reopens it with the same choices.</li>
    <li>Admins set the prices once in <span class="path">Administration → Quick Quote Prices</span>: margin %, panel ₹/W per brand (DCR / NDCR), inverter prices by size and phase, common costs, packages, brand names, payment stages, warranty, bank details, subsidy slabs and EB tariff slabs.</li>
    <li><span class="path">Sales → Sales Team Progress</span>: quotes made, sent, won and value won by each salesperson.</li>
</ul>

<h4><i class="bi bi-qr-code"></i> GST e-invoice upload file</h4>
<ul>
    <li><span class="path">Sales → GST e-Invoice</span> lists B2B invoices waiting for an IRN and checks every field the portal checks, telling you exactly what to fix.</li>
    <li>One click makes the JSON file to upload on <code>einvoice1.gst.gov.in</code> (e-Invoice → Bulk Upload), for one invoice or many.</li>
    <li>Enter the IRN, Ack No. and signed QR code the portal gives back: they print on the invoice PDF with the QR code.</li>
    <li>New company fields <em>City</em> and <em>PIN code</em> in <span class="path">Settings → Company</span>. More: <a href="<?= site_url('admin/manual/invoices') ?>">Invoices</a>.</li>
</ul>

<h4><i class="bi bi-diagram-3"></i> Stock lots: which PO and purchase bill each item came from</h4>
<ul>
    <li>Every purchase is kept as a <strong>lot</strong> with its purchase no., supplier invoice no., PO no., supplier, date and rate. Dispatches use the <strong>oldest stock first</strong> (FIFO).</li>
    <li><span class="path">Inventory → Stock Balance → Lots</span>: for any item, see each lot and which dispatch / project / customer it went to.</li>
    <li>A dispatch shows <em>Taken from</em> (the bill and PO of the material sent); a purchase shows <em>Where this stock went</em>.</li>
    <li>New <span class="path">Stock Balance → Stock ageing</span> report: stock still in store by age, to spot slow-moving material.</li>
    <li>Stock value, Profit &amp; Loss closing stock and Project Profit now use the real rate of the purchase bill.</li>
    <li>More: <a href="<?= site_url('admin/manual/inventory') ?>">Inventory</a>, <a href="<?= site_url('admin/manual/dispatch') ?>">Dispatch</a>, <a href="<?= site_url('admin/manual/purchase') ?>">Purchase</a>.</li>
</ul>

<h4><i class="bi bi-badge-3d"></i> 3D roof design</h4>
<ul>
    <li>Draw the roof (or trace it over a drone / satellite photo), add the water tank, staircase room and trees, and the panels are laid out automatically.</li>
    <li>See the shadows move through the day and year in 3D, get the shading loss and the real yearly generation, and make the quotation from the design in one click (the 3D picture goes on the quotation PDF).</li>
    <li>Site photos can be attached to the design. With a Google Maps key: find the house on the map, satellite photo under the plan and automatic roof detection.</li>
    <li>More: <a href="<?= site_url('admin/manual/roof-design') ?>">3D roof design</a>.</li>
</ul>

<h4><i class="bi bi-trash"></i> Delete leads and quotations</h4>
<ul>
    <li>Wrong or duplicate leads and quotations can be deleted, for roles given the new permissions <em>Leads: delete</em> and <em>Quotations: delete</em> in <span class="path">Administration → Roles &amp; Access</span>.</li>
    <li>A lead already converted to a customer, or a quotation already converted to an invoice, cannot be deleted.</li>
    <li>More: <a href="<?= site_url('admin/manual/leads') ?>">Leads</a>, <a href="<?= site_url('admin/manual/quotations') ?>">Quotations</a>.</li>
</ul>

<h4><i class="bi bi-toggles"></i> Modules &amp; options per company</h4>
<ul>
    <li>Each company can have only the modules it needs (for example no AMC, or no subsidy). Switched-off modules disappear from the menu, dashboard and Roles &amp; Access; their data is kept.</li>
    <li>Set by the software provider in <span class="path">Administration → Modules &amp; Options</span>. More: <a href="<?= site_url('admin/manual/administration') ?>">Users, roles &amp; settings</a>.</li>
</ul>

<h4><i class="bi bi-palette"></i> New name and logo</h4>
<p>The software is now <strong>Sygma Solar Suite</strong>, with the new logo on the login page, menu and browser tab. Your own company logo for documents is still set in <span class="path">Settings → Appearance</span>.</p>

<h4>After an update</h4>
<p>When a new version is installed, a yellow banner asks to run <span class="path">Administration → Database Updates</span>. Take a backup first, then click <span class="btnname">Run database updates</span>. New features that need the database (such as stock lots) switch on after that.</p>
