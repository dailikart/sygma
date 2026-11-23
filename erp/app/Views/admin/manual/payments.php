<p>Record every amount received against the invoice. The receipt number and accounting entry are created automatically.</p>
<h4>Record a payment</h4>
<ol class="steps">
    <li>Open the invoice → <span class="btnname">Payment</span>.</li>
    <li>Enter the amount, date, mode (Bank transfer, UPI, Cheque, Cash, or <em>Loan disbursement from bank</em>), the bank / cash account it came into, and the UTR / transaction or cheque number.</li>
    <li>Tick <strong>Email the payment receipt to the customer</strong> if you want it sent (and WhatsApp, if set up). Nothing is sent if it is not ticked.</li>
    <li><span class="btnname">Save payment</span>. The screen says whether the email went through.</li>
</ol>
<ul>
    <li>Part payments are fine; the invoice shows <?= status_badge('partial') ?> until fully paid.</li>
    <li>When the invoice is fully paid, the EB team is told that EB charges can now be paid.</li>
    <li>Print a receipt any time: click the receipt number (<code>RCT/…</code>) on the invoice, in <span class="path">Sales → Payments</span>, the Day Book or the ledger. It shows your logo, address, the amount in words, the balance after this receipt, signature and seal.</li>
</ul>
<h4>Bank loan customers</h4>
<ol class="steps">
    <li>On the invoice, set payment mode <strong>Bank loan</strong>: bank name, loan amount, sanction reference. Three disbursements are created.</li>
    <li>Edit the amounts and expected dates, or <span class="btnname">Add disbursement</span>. To delete one, click its <span class="btnname">Remove</span> button (it turns red), then <span class="btnname">Save schedule</span>. The total should equal the loan amount; a red note warns if it does not.</li>
    <li>When the bank pays: <span class="btnname">Payment</span> → mode <strong>Loan disbursement from bank</strong> → pick the disbursement. It is marked <span class="badge text-bg-success">Received</span> with its receipt number, and the receipt says "LOAN DISBURSEMENT from &lt;bank&gt;".</li>
    <li><span class="path">Reports → Customers: Cash / Loan</span> shows loan amount, disbursed, pending and due for every loan customer.</li>
</ol>
