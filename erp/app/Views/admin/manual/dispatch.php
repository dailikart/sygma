<p>The store prepares the material for the project, prints the loading list / delivery note and dispatches it. Stock is reduced at dispatch.</p>
<h4>Prepare the dispatch list</h4>
<ol class="steps">
    <li>Open the project → <span class="btnname">Prepare dispatch list from kit</span>. The kit chosen on the invoice (or the kit for the project's kW) is loaded with all its items.</li>
    <li>Choose the <strong>store</strong>. Each line shows the stock there and warns <span class="badge text-bg-danger">Out of stock</span>.</li>
    <li>Change quantities, add items, or <span class="btnname">Load group</span> to add another kit. <span class="btnname">Save dispatch list</span> (it is a draft until dispatched).</li>
</ol>
<h4>Dispatch</h4>
<ol class="steps">
    <li>On the dispatch page fill vehicle no., driver / delivery person, <strong>Dispatched through</strong>, <strong>Destination</strong> and <strong>Terms of delivery</strong> (these print on the delivery note).</li>
    <li>Enter <strong>serial numbers</strong> per line (inverter S.No, panel serials…) and <span class="btnname">Save serial numbers</span>. The installation form is filled from them.</li>
    <li><span class="btnname">Loading list</span> prints the list for loading the vehicle; <span class="btnname">Print Delivery Note</span> prints the Tally-style delivery note with your logo, the customer, items, HSN, quantities and serials.</li>
    <li><span class="btnname">Dispatch now</span>. Stock goes down and shows as <strong>In transit</strong> until delivered. The delivery person is notified and the customer gets a message.</li>
</ol>
<ul class="small">
    <li>Material cannot be dispatched until the invoice's <strong>payment mode</strong> (Cash / Bank loan) is set.</li>
    <li>A wrong <strong>draft</strong> can be deleted. Material can be sent in more than one dispatch: <span class="btnname">Prepare another dispatch list</span>.</li>
    <li>When everything has gone, <span class="btnname">Complete dispatch stage</span> if it did not close by itself.</li>
</ul>
<h4>Which purchase the material came from</h4>
<p>At dispatch the <strong>oldest stock is used first</strong> (FIFO). After dispatch, the page shows <em>Taken from</em>: for each item the purchase no., supplier bill no., PO no., date and rate of the stock that went. Click the item to see all its lots. See <a href="<?= site_url('admin/manual/inventory') ?>">Inventory → stock lots</a>.</p>
