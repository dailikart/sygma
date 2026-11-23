<h4>Users</h4>
<ol class="steps">
    <li><span class="path">Administration → Users</span> → New: username, email (used to log in), password, and tick the <strong>roles</strong>. One person can have several roles.</li>
    <li>To stop someone logging in, click <span class="btnname">Deactivate</span> on the users list (<span class="btnname">Activate</span> brings them back). Their past entries stay.</li>
</ol>
<h4>Roles &amp; Access</h4>
<p><span class="path">Administration → Roles &amp; Access</span>: tick which modules each role can <em>view</em> or <em>manage</em>. Super Admin always has everything.</p>
<table class="table table-sm">
    <?php foreach (config('AuthGroups')->groups as $g): ?><tr><th style="width:28%"><?= esc($g['title']) ?></th><td><?= esc($g['description']) ?></td></tr><?php endforeach ?>
</table>
<h4>Editing and deleting saved entries</h4>
<p>Two permissions in <span class="path">Roles &amp; Access</span> decide who may change saved work. They are given to Super Admin and Admin / Manager; tick them for any other role that needs them.</p>
<table class="table table-sm">
    <tr><th style="width:28%">Edit saved entries</th><td><span class="btnname">Edit</span> on an invoice (customer, dates, kW, lines, amounts, meter charges) and the pencil on a payment (amount, date, mode, account, reference). Totals, GST, accounts and the project's kW are updated; the invoice / receipt number stays.</td></tr>
    <tr><th>Delete entries</th><td><span class="btnname">Delete</span> on an invoice (removes its payments, loan schedule and project; dispatched stock goes back to the store), on a payment, on a project (the invoice stays), on a customer (only when they have no invoices), on purchase orders, purchases (refused if it has returns or its stock is already used; stock leaves the store, PO quantities go back) and purchase returns (stock comes back), on items (only with zero stock and not in a kit; their history stays), and on opening / adjustment lines in an item's stock ledger.</td></tr>
</table>
<p class="small text-muted">Every edit and delete is recorded in the User Log and the invoice's activity log. A deleted entry cannot be brought back.</p>
<h4>Settings</h4>
<p><span class="path">Administration → Settings</span>: Company (name, address, GSTIN, state, tagline shown on the login page), Appearance (theme, logo, signature, seal), Numbering prefixes, Email (SMTP), AMC reminder days, WhatsApp, Tally ledger names. Click <span class="btnname">Save</span> at the bottom.</p>
<h4>IndiaMART leads</h4>
<ol class="steps">
    <li>In IndiaMART seller panel: <em>Lead Manager → CRM integration</em>. Copy the <strong>CRM key</strong> (Pull API).</li>
    <li><span class="path">Settings → IndiaMART leads</span>: switch on <strong>Import IndiaMART enquiries as leads</strong>, paste the key, <span class="btnname">Save</span>.</li>
    <li>Click <span class="btnname">Fetch now</span> to test. For automatic import add the cron job shown on the Settings page (every 10 minutes). IndiaMART allows one check every 5 minutes.</li>
    <li>Optional, for instant leads: in IndiaMART's <em>Push API</em> setting, paste the push address shown on the Settings page.</li>
</ol>
<h4>Facebook / Instagram Lead Ads</h4>
<ol class="steps">
    <li><span class="path">Settings</span> shows the <strong>Callback URL</strong> and <strong>Verify token</strong>. In Meta for Developers, create a Business app, add Webhooks → Page → subscribe to <em>leadgen</em> with these.</li>
    <li>Generate a long-lived <strong>Page access token</strong> (permissions <code>leads_retrieval</code>, <code>pages_manage_metadata</code>) and copy the <strong>App secret</strong>; paste both in <span class="path">Settings → Meta leads</span>, tick Import, Save.</li>
    <li>Test with Meta's Lead Ads Testing Tool; the lead appears with source "Facebook / Instagram". If the token is wrong the lead is still saved with a note saying so.</li>
</ol>
<h4>Google Ads lead forms</h4>
<ol class="steps">
    <li>Tick <strong>Import Google Ads lead-form leads</strong> in Settings and Save.</li>
    <li>In Google Ads → lead form → Lead delivery → Webhook, paste the <strong>Webhook URL</strong> and <strong>Key</strong> shown in Settings.</li>
    <li><em>Send test data</em> creates a "(Google test lead)" in Leads.</li>
</ol>
<h4>Other admin screens</h4>
<table class="table table-sm">
    <tr><th style="width:28%">User Log</th><td>Every save, change, delete, login, logout and failed login, with user, date &amp; time and IP. Filter and export to Excel.</td></tr>
    <tr><th>Document Types</th><td>The customer document checklist; mark which are required for the PCR.</td></tr>
    <tr><th>WhatsApp Templates</th><td>Which approved WhatsApp template is sent for each event.</td></tr>
    <tr><th>Message Log</th><td>Every email and WhatsApp sent to customers, with success or the error.</td></tr>
    <tr><th>Database Updates</th><td>After a new version is deployed, a yellow banner appears: take a database backup (phpMyAdmin → Export), then <span class="btnname">Run database updates</span>.</td></tr>
</table>
