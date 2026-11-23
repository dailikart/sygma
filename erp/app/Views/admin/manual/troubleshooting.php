<table class="table table-sm align-top">
    <thead><tr><th style="width:36%">Problem</th><th>Solution</th></tr></thead>
    <tr><td>"The action you requested is not allowed."</td><td>The page was open too long or you were logged out in between. Reload the page (F5), enter the data again and save.</td></tr>
    <tr><td>Email FAILED: "Unable to initiate AUTH command" / login rejected</td><td>See <a href="<?= site_url('admin/manual/email-whatsapp') ?>">Email setup</a>: port 465 = SSL, 587 = TLS; for Gmail use an App Password. Use <span class="btnname">Send test</span> after saving.</td></tr>
    <tr><td>Cannot dispatch material</td><td>Set the invoice's payment mode (Cash / Bank loan) first. Check stock in the chosen store.</td></tr>
    <tr><td>"EB charges paid" is locked / EB stage will not complete</td><td>The invoice must be fully paid. Record the remaining payment or loan disbursement.</td></tr>
    <tr><td>PCR step cannot be ticked</td><td>Upload every required customer document on the invoice / project page.</td></tr>
    <tr><td>Installation will not complete</td><td>Upload the panel photo, net-meter agreement and panel serial no. photo.</td></tr>
    <tr><td>Supplier invoice "already entered"</td><td>That supplier invoice no. is already on another purchase. Open that purchase instead of entering it again.</td></tr>
    <tr><td>Return quantity refused</td><td>You can return at most what was bought minus what was already returned on that purchase.</td></tr>
    <tr><td>Bulk upload says "Nothing was saved"</td><td>Fix the rows listed (row numbers are Excel rows) and upload the file again.</td></tr>
    <tr><td>Wrong GST type (IGST instead of CGST+SGST)</td><td>Check the customer's / supplier's <strong>state</strong> and your company state in Settings.</td></tr>
    <tr><td>I do not see a menu</td><td>Your role does not include it. Ask the admin to change your roles or Roles &amp; Access.</td></tr>
    <tr><td>Forgot password</td><td>Ask the admin to set a new one in <span class="path">Administration → Users</span>, then change it under your name → Change password.</td></tr>
    <tr><td>Yellow "database update needed" banner</td><td>Super Admin: take a backup, then <span class="path">Administration → Database Updates</span> → <span class="btnname">Run database updates</span>.</td></tr>
</table>
