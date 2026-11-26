<p>Every invoice, receipt, purchase, return, supplier payment, income and expense is posted automatically as a double-entry voucher. You only enter the day-to-day income and expenses and the occasional journal.</p>
<h4>Masters (once)</h4>
<ul>
    <li><span class="path">Accounts → Bank / Cash Accounts</span>: each bank account and cash, with opening balance.</li>
    <li><span class="path">Accounts → Income Heads / Expense Heads</span>: e.g. Scrap Sale, Fuel, Salary, Rent.</li>
</ul>
<h4>Daily entries</h4>
<ul>
    <li><span class="path">Accounts → Income</span> → New: other income (not customer payments).</li>
    <li><span class="path">Accounts → Expenses</span> → New: head, account paid from, amount, attach the bill. Link it to a project to track project cost; it then counts in <span class="path">Reports → Project Profit</span>.</li>
    <li><span class="path">Accounts → Supplier Payments</span> → New.</li>
    <li><span class="path">Accounts → Journal / Contra</span>: manual entries with Dr / Cr lines, e.g. cash deposited in the bank (contra) or corrections. <span class="btnname">Save voucher</span> only when Dr = Cr.</li>
</ul>
<h4>Books</h4>
<table class="table table-sm">
    <tr><th style="width:28%">Day Book</th><td>All vouchers in a period, by type.</td></tr>
    <tr><th>Ledgers</th><td>Statement of any customer, supplier, bank, income or expense head, with opening and running balance. <span class="btnname">Add ledger</span> for e.g. a Capital Account.</td></tr>
    <tr><th>Trial Balance</th><td>All ledger balances. Dr total must equal Cr total.</td></tr>
    <tr><th>Profit &amp; Loss</th><td>Sales, purchases, opening and closing stock, gross profit, expenses and net result for a period. Stock is valued FIFO: what is still in store at the rate of the purchase bill it came from.</td></tr>
</table>
<h4>Voucher numbers and printing</h4>
<p>Numbers are automatic per financial year: invoices <code>INV/…</code>, receipts <code>RCT/…</code>, income <code>INC/…</code>, expenses <code>EXP/…</code>, supplier payments <code>SPAY/…</code>, purchases <code>PUR/…</code>, returns <code>PRN/…</code>, journal <code>JV/…</code>. The prefixes are changed in <span class="path">Settings → Numbering</span>.</p>
<p>Click a voucher number on any list, the Day Book or a ledger to print it in Tally style: Receipt, Payment voucher, Sales / Purchase / Debit note, Journal / Contra.</p>
<p class="small text-muted">Opening balances: banks in Bank / Cash Accounts, suppliers in the supplier master, and the balancing figure in a Capital Account ledger so the trial balance matches.</p>
