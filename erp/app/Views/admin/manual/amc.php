<p>After commissioning, the AMC contract and its service visits are created from the plan chosen in the EB stage.</p>
<h4>AMC plans (once)</h4>
<p><span class="path">AMC / Service → AMC Plans</span>: name, duration in months, visit every N months, and <strong>Default</strong> for the plan normally used.</p>
<h4>Service visits</h4>
<ol class="steps">
    <li><span class="path">AMC / Service → Service Visits</span> lists visits due soon. <span class="btnname">Open</span> the contract.</li>
    <li>Choose the technician for each visit and <span class="btnname">Save</span>. The technician sees it in My Tasks.</li>
    <li>After the visit, on that visit's row: date, what was done (e.g. "Cleaning done, generation OK"), a photo → <span class="btnname">Done</span>.</li>
</ol>
<p class="small text-muted">Customers and technicians are reminded <em>N</em> days before a visit (<span class="path">Settings → AMC</span>). This needs the daily cron job set up on the server.</p>
