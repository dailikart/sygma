<p>A project is created automatically when an invoice is saved with <strong>Start the installation project</strong> ticked. It moves through these stages in order:</p>
<table class="table table-sm">
    <thead><tr><th>#</th><th>Stage</th><th>Who</th><th>Done when</th></tr></thead>
    <tr><td>1</td><td><a href="<?= site_url('admin/manual/survey') ?>">Site Survey &amp; Approval</a></td><td>Site Surveyor</td><td>Site approved</td></tr>
    <tr><td>2</td><td><a href="<?= site_url('admin/manual/dispatch') ?>">Material Dispatch</a></td><td>Store</td><td>Material dispatched</td></tr>
    <tr><td>3</td><td><a href="<?= site_url('admin/manual/delivery') ?>">Delivery at Site</a></td><td>Delivery person</td><td>Delivered with photo</td></tr>
    <tr><td>4</td><td><a href="<?= site_url('admin/manual/installation') ?>">Installation</a></td><td>Installation team</td><td>Photos, agreement, serial nos. uploaded</td></tr>
    <tr><td>5</td><td><a href="<?= site_url('admin/manual/dcr') ?>">DCR &amp; Installation Submission</a></td><td>EB / Subsidy Liaison</td><td>Subsidy customers only</td></tr>
    <tr><td>6</td><td><a href="<?= site_url('admin/manual/eb') ?>">EB / Net Meter</a></td><td>EB / Subsidy Liaison</td><td>Commissioned (needs full payment)</td></tr>
    <tr><td>7</td><td><a href="<?= site_url('admin/manual/subsidy') ?>">Subsidy</a></td><td>EB / Subsidy Liaison</td><td>Subsidy customers only</td></tr>
</table>
<h4>Project page</h4>
<ul>
    <li>One card per stage. The current stage is open for editing; finished stages show who completed them and when.</li>
    <li><strong>Assign</strong>: an admin can choose the person for a stage. "Anyone in role" means everyone with that role sees it in My Tasks.</li>
    <li>Each completed stage notifies the next team (bell + My Tasks), and the customer gets a message at key points (dispatched, delivered, commissioned) if email / WhatsApp is set up.</li>
    <li><span class="btnname">Put on hold</span> / <span class="btnname">Resume</span>: pause a project (e.g. customer not ready). <span class="btnname">Open in Maps</span> opens the site address.</li>
</ul>
<h4>Finding projects</h4>
<p><span class="path">Projects</span> lists all projects with filters by stage and status. The dashboard's <strong>Active projects by stage</strong> boxes open the list for that stage. <span class="path">Reports → Project Status</span> shows days in each stage.</p>
