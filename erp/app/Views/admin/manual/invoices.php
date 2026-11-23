<p>The invoice is where a confirmed order becomes a job. Saving it can start the installation project.</p>
<h4>Create an invoice</h4>
<ol class="steps">
    <li>Customer page → <span class="btnname">Create Invoice</span> (or <span class="path">Sales → Invoices</span> → New and pick the customer).</li>
    <li>Choose <strong>Subsidy customer</strong> or <strong>Non-subsidy customer</strong>, the installation type and the kW.</li>
    <li>Choose the <strong>payment mode</strong>: <em>Cash</em> or <em>Bank loan</em> (bank, loan amount, sanction ref). It can be set later, but material <strong>cannot be dispatched</strong> until it is set.</li>
    <li><strong>EB / meter charges paid by</strong>: filled from the customer. <em>Company</em> = shown as "–"; <em>Customer</em> = the amount (e.g. ₹5,000) collected beyond this invoice. It can be changed later on the invoice page.</li>
    <li>Pick the <strong>kit</strong> for the system, then:
        <ul>
            <li><span class="btnname">Load items (B2B)</span>: lists every item of the kit with qty, rate, HSN and GST, for an item-wise invoice.</li>
            <li><span class="btnname">Fill as B2C package</span>: one line "X kW Solar Power System – supply" with the kit value, plus a line for installation / service. The customer does not see the item break-up.</li>
        </ul>
        You can also add or change lines by hand.</li>
    <li>Keep <strong>Start the installation project</strong> ticked: the project is created and the site surveyor is notified. The kit you chose is what the store will dispatch, so the delivery note always shows the full material even on a B2C package invoice.</li>
    <li>Tick <strong>Email this invoice to the customer</strong> only if you want it sent now. Then <span class="btnname">Save invoice</span>.</li>
</ol>
<h4>On the invoice page</h4>
<ul>
    <li><span class="btnname">View PDF</span> / download, <span class="btnname">Email</span> (send any time later), <span class="btnname">Payment</span>, open the <span class="btnname">Project</span>.</li>
    <li><strong>Format</strong>: <em>Cumulative</em> (one line for the solar system with a GST summary, default for B2C) or <em>Item-wise</em> (default for B2B). Change it on the invoice page.</li>
    <li><strong>Payment mode</strong> card: <span class="btnname">Change</span> cash / loan (allowed until material is dispatched). For a loan, see <a href="<?= site_url('admin/manual/payments') ?>">bank loan disbursements</a>.</li>
    <li><span class="btnname">Mark as Cancelled</span> with a reason, for a wrong or withdrawn invoice. Its accounting entry is reversed.</li>
    <li>Admins (permission "Edit saved entries" / "Delete entries"): <span class="btnname">Edit</span> to correct a saved invoice, <span class="btnname">Delete</span> to remove it with its payments and project. Payments have a pencil (edit) and bin (delete) in the Payments table.</li>
</ul>
<h4>Invoice list</h4>
<p><span class="path">Sales → Invoices</span> shows for each invoice: amount (with <strong>Total</strong> receivable under it when the customer pays meter charges), <strong>Meter charges</strong> ("–" when the company pays), <strong>Paid</strong>, <strong>Due</strong>, status, <strong>EB Payment</strong> (Yes when "EB charges paid" is ticked on the project) and <strong>EB Submission</strong> (Yes when set on the project's EB / Net Meter card). "—" means the invoice has no project. Totals of amount, paid and due are at the bottom.</p>
<h4>Statuses</h4>
<p><?= status_badge('unpaid') ?> no payment yet · <?= status_badge('partial') ?> part paid · <?= status_badge('paid') ?> fully paid · <?= status_badge('cancelled') ?></p>
<p class="small text-muted">GST: same state as your company = CGST + SGST; other state = IGST. Make sure the customer's state is correct before invoicing.</p>
