<h4>Email (SMTP)</h4>
<ol class="steps">
    <li><span class="path">Administration → Settings → Email (SMTP)</span>: switch on <strong>Send email through this SMTP server</strong>.</li>
    <li>Fill in for your provider:
        <table class="table table-sm mt-2">
            <thead><tr><th></th><th>Hostinger mailbox (recommended)</th><th>Gmail</th></tr></thead>
            <tr><th>SMTP host</th><td><code>smtp.hostinger.com</code></td><td><code>smtp.gmail.com</code></td></tr>
            <tr><th>Port / Encryption</th><td>465 / SSL</td><td>465 / SSL (or 587 / TLS)</td></tr>
            <tr><th>Username</th><td>full mailbox address, e.g. info@yourdomain.com</td><td>your Gmail address</td></tr>
            <tr><th>Password</th><td>the mailbox password</td><td>a 16-letter <strong>App Password</strong> (Google Account → Security → 2-Step Verification → App passwords), not your Gmail password</td></tr>
        </table></li>
    <li>From email = same as the username. From name = your company name.</li>
    <li><span class="btnname">Save</span>, then <span class="btnname">Send test</span>. If it fails, the message says what to check and quotes the mail server's reply.</li>
</ol>
<p class="small text-muted">Customer emails go only when you tick "Email to customer" on the invoice or payment. Staff notification emails go automatically.</p>
<h4>WhatsApp (WeBase Cloud API)</h4>
<ol class="steps">
    <li><span class="path">Settings → WhatsApp</span>: switch on, enter the API base URL, Vendor UID, API token and (optionally) the from phone number ID.</li>
    <li><span class="path">Administration → WhatsApp Templates</span>: map each event (invoice, receipt, dispatched, delivered, commissioned, AMC reminder…) to your approved template.</li>
    <li>Check results in <span class="path">Administration → Message Log</span>.</li>
</ol>
