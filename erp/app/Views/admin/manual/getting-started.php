<p>This CRM follows a solar job from the first enquiry to the AMC service visits after commissioning. Each person sees only the menus their role allows, so your sidebar may be shorter than someone else's.</p>

<h4>The flow of work</h4>
<div class="d-flex flex-wrap gap-1 align-items-center small mb-2">
    <?php foreach (['Lead', 'Customer', 'Invoice', 'Site survey', 'Dispatch', 'Delivery', 'Installation', 'DCR (subsidy)', 'EB / net meter', 'Subsidy', 'AMC visits'] as $i => $s): ?>
        <?= $i ? '<i class="bi bi-arrow-right text-muted"></i>' : '' ?><span class="badge text-bg-light border"><?= $s ?></span>
    <?php endforeach ?>
</div>
<p class="small text-muted">Each step notifies the next team automatically. Payments, purchases, stock and accounts run alongside.</p>

<h4>Logging in</h4>
<ol class="steps">
    <li>Open the CRM address in a browser (computer or phone) and enter the email and password the admin gave you.</li>
    <li>First time: open your name (top right) → <span class="btnname">Change password</span> and set your own password.</li>
    <li>When you finish, use <span class="btnname">Logout</span> under your name, especially on a shared computer.</li>
</ol>

<h4>Screens you will use every day</h4>
<table class="table table-sm">
    <tr><th style="width:28%">Dashboard</th><td>Open leads, follow-ups due, sales and collections this month, receivable, cash / bank-loan clients, total invoice value, paid and outstanding, projects by stage, low stock.</td></tr>
    <tr><th>My Tasks</th><td>Everything waiting for you: surveys, dispatches, deliveries, installations, EB steps, service visits. Click <span class="btnname">Open</span> to act on it.</td></tr>
    <tr><th>Bell icon <i class="bi bi-bell"></i></th><td>Notifications, for example "new project assigned" or "material dispatched". The red number is the unread count.</td></tr>
    <tr><th>Help <i class="bi bi-question-circle"></i></th><td>The <span class="btnname">Help</span> button at the top of every screen opens the chapter of this manual for that screen.</td></tr>
</table>

<h4>Using it on a phone</h4>
<p>Tap <i class="bi bi-list"></i> (top left) to open the menu. Delivery and installation teams can take photos straight from the phone camera when they upload.</p>

<h4>Good habits</h4>
<ul>
    <li>Fill the fields marked <strong>*</strong>; they are required.</li>
    <li>Every save, change and delete is recorded in the User Log with your name and the time.</li>
    <li>Numbers such as <code>INV/2026-27/0001</code> are given automatically. Do not try to type them.</li>
</ul>
