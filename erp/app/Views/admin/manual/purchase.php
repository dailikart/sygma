<p>Buying material: <strong>Purchase Order</strong> → <strong>Purchase</strong> (goods received against the supplier's invoice) → <strong>Purchase Return</strong> if something goes back. Then pay the supplier from Accounts.</p>
<h4>Suppliers</h4>
<p><span class="path">Purchase → Suppliers</span>: name, contact, phone, GSTIN, <strong>state</strong> (decides CGST+SGST or IGST), address, opening balance you owe. Many suppliers? <span class="btnname">Bulk upload</span>.</p>
<h4>Purchase order</h4>
<ol class="steps">
    <li><span class="path">Purchase → Purchase Orders</span> → New: supplier, date, expected delivery, items with qty and rate. Save. Print / PDF to send to the supplier.</li>
    <li>Status: <?= status_badge('open') ?> → <?= status_badge('partial') ?> (part received) → <?= status_badge('received') ?>. An open PO can be cancelled.</li>
</ol>
<h4>Purchase (goods received)</h4>
<ol class="steps">
    <li>From the PO: <span class="btnname">Receive goods (create purchase)</span>. The remaining quantities are filled in; change them if only part has arrived. Or enter a purchase directly from <span class="path">Purchase → Purchases</span> → New.</li>
    <li>Enter the <strong>Supplier invoice no.</strong> and <strong>Supplier invoice date</strong> exactly as on the supplier's bill (both required), and the store it is received into.</li>
    <li>Save. Stock goes into that store, the supplier's balance goes up and the purchase voucher is posted (sent to Tally with the supplier invoice no. and date).</li>
</ol>
<p class="small text-muted">The same supplier invoice no. cannot be entered twice for one supplier. The system names the purchase it is already on.</p>
<h4>Purchase return (against the purchase)</h4>
<ol class="steps">
    <li><span class="path">Purchase → Purchases</span> → <span class="btnname">Return</span> on the purchase row (or <span class="path">Purchase Returns</span> → New and pick the purchase by supplier / invoice no.).</li>
    <li>The supplier, supplier invoice no. / date, store and all items load from the purchase, with <strong>bought</strong> and <strong>already returned</strong> quantities. Rates and GST are fixed.</li>
    <li>Enter only the <strong>Return qty</strong> for each item going back (maximum = bought − already returned), the return date and the reason. Save.</li>
    <li>Stock goes out of that store and a debit note reduces what you owe the supplier.</li>
</ol>
<h4>Where the stock of a purchase went</h4>
<ul>
    <li>Each purchase becomes a stock <strong>lot</strong>. The purchase page shows <em>Where this stock went</em>: per item, how much was received, used and is still in store, and which dispatch / project / customer or return got it.</li>
    <li>A return takes stock from the lots of the purchase it is against; its page shows <em>Returned from</em>.</li>
    <li>A purchase whose stock is already dispatched or returned cannot be deleted. The message names what to remove first.</li>
</ul>
<h4>Paying the supplier</h4>
<p><span class="path">Accounts → Supplier Payments</span> → New: supplier, account paid from, amount, mode, reference. <span class="path">Reports → Supplier Summary</span> shows each supplier's purchases, returns, payments and balance, with a statement.</p>
