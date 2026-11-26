<p>A lead is an enquiry from a possible customer. Record every call so nothing is forgotten.</p>
<h4>Add a lead</h4>
<ol class="steps">
    <li><span class="path">Marketing → Leads</span> → <span class="btnname">New Lead</span>.</li>
    <li>Enter name, phone, city, source (walk-in, referral, Facebook…), <strong>B2B or B2C</strong>, kW needed and the <strong>next follow-up date</strong>.</li>
    <li>If the phone number is already in the system, a warning shows the existing lead or customer, so you do not enter it twice.</li>
    <li>Click <span class="btnname">Save</span>.</li>
</ol>
<h4>Leads from IndiaMART, Facebook / Instagram and Google Ads</h4>
<p>Once set up in Settings (see <a href="<?= site_url('admin/manual/administration') ?>">Users, roles &amp; settings</a>), enquiries from <strong>IndiaMART</strong>, <strong>Facebook / Instagram Lead Ads</strong> and <strong>Google Ads lead forms</strong> arrive in Leads by themselves, with the source set, a follow-up for today and the person's answers in the notes. They all follow the rules below.</p>
<h4>IndiaMART enquiries come in by themselves</h4>
<p>When IndiaMART import is set up (<span class="path">Administration → Settings → IndiaMART leads</span>), every IndiaMART enquiry becomes a lead with source <strong>IndiaMART</strong>, today's follow-up date, the product and message in the notes, and kW read from the message (e.g. "5 kW"). Enquiries with a company name are B2B. The marketing team gets a notification.</p>
<ul class="small">
    <li>The same enquiry is never imported twice.</li>
    <li>If the phone number already belongs to an open lead, the new enquiry is added to that lead's follow-ups instead of creating a duplicate.</li>
    <li>Filter the list with <strong>Source → IndiaMART</strong>.</li>
</ul>
<h4>Follow-ups</h4>
<ol class="steps">
    <li>Open the lead and add a follow-up after every call: what was discussed, the new status (Contacted, Follow-up, Interested…) and the next date.</li>
    <li>The dashboard card <strong>Follow-ups due</strong> lists today's and overdue calls. Click it to see the list.</li>
</ol>
<h4>Send a quotation</h4>
<p>On the lead page click <span class="btnname">Create quotation for this lead</span>. See <a href="<?= site_url('admin/manual/quotations') ?>">Quotations</a>. Converting the quotation later creates the customer automatically.</p>
<h4>Convert to customer</h4>
<ol class="steps">
    <li>When the customer confirms, open the lead → <span class="btnname">Convert</span>. Choose <strong>B2B</strong> (business, with GSTIN) or <strong>B2C</strong> (home owner).</li>
    <li>Check the customer details: address, state, GSTIN (B2B), EB service number.</li>
    <li>Next: <a href="<?= site_url('admin/manual/invoices') ?>">create the invoice</a>.</li>
</ol>
<p class="small text-muted">A lead you lose: set the status to <strong>Lost</strong>. It leaves the open list but stays in the history.</p>
<h4>Delete a lead</h4>
<ul>
    <li>For a wrong or duplicate entry: open the lead → <span class="btnname">Delete</span> and confirm.</li>
    <li>Only roles with <em>Leads: delete</em> (<span class="path">Administration → Roles &amp; Access</span>) see the button.</li>
    <li>A lead already converted to a customer cannot be deleted (delete the customer instead, if really needed). The delete is recorded in the User Log.</li>
</ul>
