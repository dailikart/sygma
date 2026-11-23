<p>Send your accounts to Tally without typing them again.</p>
<ol class="steps">
    <li>Check <span class="path">Settings → Tally ledger names</span> match your Tally company (Sales, Purchase, Output / Input CGST, SGST, IGST names; <code>{rate}</code> is replaced by the GST rate).</li>
    <li><span class="path">Reports → Tally Export</span>: choose the period (and a voucher type if you want only one) → <span class="btnname">Show</span>.</li>
    <li>First time only: <span class="btnname">1. Ledger masters XML</span> → in Tally: <em>Gateway of Tally → Import → Masters</em>.</li>
    <li><span class="btnname">2. Vouchers XML</span> → in Tally: <em>Gateway of Tally → Import → Transactions</em>.</li>
</ol>
<p>Voucher types exported: Sales, Receipt, Purchase (with supplier invoice no. and date), Debit Note, Payment, Journal, Contra. <span class="btnname">Excel</span> gives the same entries as a spreadsheet.</p>
<p class="small text-muted">Export each period once. Importing the same vouchers twice creates duplicates in Tally.</p>
