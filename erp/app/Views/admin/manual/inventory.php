<p>The store structure is: <strong>Installation type</strong> → <strong>Category</strong> → <strong>Item</strong>. Items are grouped into <strong>kits</strong> by kW, and stock is kept per <strong>location</strong>.</p>
<h4>Masters</h4>
<ol class="steps">
    <li><span class="path">Inventory → Installation Types</span>: On-grid, Off-grid, Hybrid…</li>
    <li><span class="path">Inventory → Item Categories</span>: Solar Panel, Inverter, Structure, DC Cable, Battery… (optionally linked to an installation type).</li>
    <li><span class="path">Inventory → Locations</span>: each store / warehouse.</li>
    <li><span class="path">Inventory → Items</span> → <span class="btnname">Add Item</span>: name, code / SKU, type, category, unit, HSN, GST %, purchase price, sale price, minimum stock, and optionally opening stock with its location. For many items use <span class="btnname">Bulk upload</span>.</li>
</ol>
<h4>Item Groups / Kits (kW)</h4>
<ol class="steps">
    <li><span class="path">Inventory → Item Groups / Kits</span> → Add: name (e.g. "3 kW On-grid kit"), installation type, capacity in kW.</li>
    <li>Add the items and quantities. Save.</li>
    <li>The kit is used on the invoice (<span class="btnname">Load items</span> / <span class="btnname">Fill as B2C package</span>) and to prepare the dispatch list.</li>
</ol>
<h4>Stock</h4>
<ul>
    <li><span class="path">Inventory → Stock Balance</span>: quantity per location, <strong>in transit</strong> (dispatched, not yet delivered), and value. Filter by location; tick <em>Only items in stock</em>. Click an item for its full movement history.</li>
    <li>Stock goes <strong>in</strong> with purchases and opening stock, and <strong>out</strong> with dispatches and purchase returns.</li>
    <li><span class="path">Inventory → Stock Adjustment</span>: choose location, type (<em>Opening stock</em> or <em>Adjustment</em>) and a reason; enter <code>+10</code> to add or <code>-2</code> to remove per item. Use it after a physical count.</li>
    <li>A wrong opening / adjustment line? Admins (permission "Delete entries") can delete it with the bin icon on that line in the item's stock ledger (Stock Balance → click the quantity). Purchase, return and dispatch lines are removed by deleting that document.</li>
    <li>Items below their minimum stock are listed under <strong>Low stock</strong> on the dashboard.</li>
</ul>
<h4>Which PO and purchase bill: stock lots (FIFO)</h4>
<p>Every purchase (goods received), opening stock or stock added by adjustment is kept as a <strong>lot</strong> that remembers its purchase no., supplier invoice no., PO no., supplier, date and rate. When stock goes out, the <strong>oldest lot is used first</strong> (First In, First Out). A purchase return takes from the lots of the purchase you return against.</p>
<ul>
    <li><span class="path">Stock Balance → Lots</span> (or <span class="btnname">Lots: which PO / purchase bill</span> in the item's ledger): every lot of the item with <em>In / Used / Left</em> and where it went: dispatch, project and customer, or return.</li>
    <li>A <strong>dispatch</strong> shows <em>Taken from</em>: for each item, the purchase, supplier bill and PO the material came from.</li>
    <li>A <strong>purchase</strong> shows <em>Where this stock went</em>: which projects got the material from that bill and how much is still in store.</li>
    <li><span class="path">Stock Balance → Stock ageing</span>: stock still in store, lot by lot, with its age (0–30, 31–90, 91–180, over 180 days) and value. Old lots are slow-moving material.</li>
    <li>Stock value (Stock Balance, Profit &amp; Loss opening/closing stock) and the material cost in <em>Project profit</em> use the real rate of the purchase bill each piece came from.</li>
    <li>A purchase whose stock is already dispatched or returned cannot be deleted; the message names the dispatch / return to remove first.</li>
</ul>
