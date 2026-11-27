<p>Payroll pays your staff every month from their attendance: monthly salaried staff and daily-wage helpers, with PF, ESI, professional tax, TDS and salary advances. Each employee gets a payslip, and the salaries go into the accounts by themselves.</p>
<h4>1. Add employees (once)</h4>
<ol class="steps">
    <li><span class="path">Payroll → Employees</span> → <span class="btnname">Add employee</span>: code (filled for you), name, department, designation, phone, email, joining date.</li>
    <li><strong>Salary</strong>: <em>Monthly salary</em> (Basic, HRA, Conveyance, Special allowance per month) or <em>Daily wage</em> (₹ per day, for helpers and site labour).</li>
    <li>Tick <strong>PF</strong>, <strong>ESI</strong> and <strong>Professional tax</strong> where they apply, and the <strong>TDS per month</strong> if income tax is deducted.</li>
    <li>Bank account and IFSC (for the bank transfer sheet), PAN, UAN, ESI number.</li>
    <li>Optional <strong>ERP login</strong>: the employee then sees their own payslips under <span class="path">their name (top right) → My payslips</span>.</li>
    <li>When someone leaves, edit them and fill the <strong>Exit date</strong>. They are paid up to that date and then drop off the list; their history stays.</li>
</ol>
<h4>2. Attendance (every month)</h4>
<ol class="steps">
    <li><span class="path">Payroll → Attendance</span>, choose the month.</li>
    <li>Click <span class="btnname">Fill blanks: present, Sun week off</span>, then click the days that were different. Each click changes the day: <b>P</b> present → <b>A</b> absent → <b>H</b> half day → <b>L</b> paid leave → <b>W</b> week off → <b>O</b> holiday → blank.</li>
    <li>Click a date at the top to set that day for everyone (e.g. a festival holiday).</li>
    <li>Type the month's <strong>OT hrs</strong> per person if overtime was worked. <span class="btnname">Save attendance</span>.</li>
</ol>
<p class="small text-muted">Monthly staff: blank days count as worked, so you only need to mark absences. Daily-wage staff are paid only for P, H (half) and L days.</p>
<h4>3. Salary run</h4>
<ol class="steps">
    <li><span class="path">Payroll → Salary Runs</span>: choose the month → <span class="btnname">Calculate salaries</span>. Every employee's pay is worked out (see below).</li>
    <li>Check the table. Type any <strong>Incentive / bonus</strong> or <strong>Other deduction</strong> and a remark, then <span class="btnname">Save incentives / deductions &amp; recalculate</span>. Changed the attendance or a salary? <span class="btnname">Recalculate</span>.</li>
    <li><span class="btnname">Approve</span>: payslips become final, the attendance of the month is locked and the salary entry is posted in Accounts.</li>
    <li>Download the <span class="btnname">Bank transfer sheet</span> (account, IFSC, net pay) for your bank's bulk NEFT, and pay.</li>
    <li>Choose the bank account and date → <span class="btnname">Mark salaries paid</span>. The payment entry is posted.</li>
    <li><span class="btnname">Email payslips</span> sends each employee their payslip PDF. <span class="btnname">All payslips</span> prints them all; <span class="btnname">Register</span> is the full salary register in Excel.</li>
</ol>
<p class="small">A mistake after approving? <span class="btnname">Undo payment</span> / <span class="btnname">Undo approval</span> take it back to a draft (the accounts entries are removed), so you can correct and approve again.</p>
<h4>How the pay is worked out</h4>
<table class="table table-sm">
    <tr><th style="width:24%">Monthly staff</th><td>Each salary part × paid days ÷ days in the month. Paid days = days in the month − absent − ½ × half days (week offs, holidays and paid leave are paid). Joined or left in the month: only the days employed.</td></tr>
    <tr><th>Daily wage</th><td>Wage × (present + ½ × half days + paid leave).</td></tr>
    <tr><th>Overtime</th><td>OT hours × hourly rate × 2 (hourly rate = monthly gross ÷ days ÷ 8, or daily wage ÷ 8).</td></tr>
    <tr><th>PF</th><td>12% of the basic earned (wages for daily staff), on at most ₹15,000; the company adds the same 12%.</td></tr>
    <tr><th>ESI</th><td>When the monthly gross is up to ₹21,000: 0.75% from the employee, 3.25% from the company.</td></tr>
    <tr><th>Professional tax</th><td>Tamil Nadu half-yearly slabs on gross × 6; one sixth is deducted each month.</td></tr>
    <tr><th>Advances</th><td>The monthly recovery set on each open advance, never more than what is left.</td></tr>
</table>
<p class="small text-muted">All rates and the PT slabs are in <span class="path">Administration → Settings → Payroll</span> (for another state, type its PT slabs).</p>
<h4>Salary advances</h4>
<p>On the employee's page → <strong>Salary advances</strong>: date, amount, recovery per month and the account it was paid from → <span class="btnname">Give advance</span>. It is recovered from the next salaries automatically.</p>
<h4>In the accounts</h4>
<ul>
    <li><strong>Approval</strong> (Journal): Salaries &amp; Wages and Employer PF / ESI Contribution (expenses) against Salary Payable, PF Payable, ESI Payable, Professional Tax Payable, TDS on Salary Payable and Staff Advances.</li>
    <li><strong>Payment</strong>: Salary Payable against the bank. <strong>Advance</strong>: Staff Advances against the bank.</li>
    <li>They appear in the Day Book, ledgers, Profit &amp; Loss and the Tally export like any other entry.</li>
</ul>
<h4>Who can use it</h4>
<p><span class="path">Administration → Roles &amp; Access</span>: <em>Payroll: view</em> and <em>Payroll: manage</em>. Accounts and Super Admin have it by default. Salaries are private: give it only to the people who run payroll.</p>
