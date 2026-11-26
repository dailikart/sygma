<p>The latest additions to Sygma Solar Suite, newest first. Each item links to its chapter for the full steps.</p>

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
