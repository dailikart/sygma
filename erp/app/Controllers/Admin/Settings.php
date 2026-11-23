<?php

namespace App\Controllers\Admin;

class Settings extends AdminController
{
    /** group => [key => label | [label, type]] ; type: text | password | bool | number */
    private array $fields = [
        'Company' => [
            'companyName' => 'Company name', 'companyAddress' => 'Address', 'companyPhone' => 'Phone',
            'companyEmail' => 'Email', 'companyGstin' => 'GSTIN', 'companyTagline' => 'Tagline (shown on the login page)', 'companyState' => 'State (for GST: same state = CGST+SGST, other = IGST)',
        ],
        'Appearance' => [
            'theme' => ['Theme colour', 'select', ['' => 'Slate (default)', 'ocean' => 'Ocean blue', 'forest' => 'Forest green', 'sunset' => 'Sunset orange',
                'purple' => 'Royal purple', 'crimson' => 'Crimson', 'teal' => 'Teal', 'solar' => 'Solar amber']],
        ],
        'Numbering' => [
            'quotePrefix' => 'Quotation', 'invoicePrefix' => 'Invoice', 'receiptPrefix' => 'Receipt', 'projectPrefix' => 'Project',
            'poPrefix' => 'Purchase order', 'purchasePrefix' => 'Purchase', 'returnPrefix' => 'Purchase return',
            'dispatchPrefix' => 'Dispatch', 'supplierPayPrefix' => 'Supplier payment', 'incomePrefix' => 'Income',
            'expensePrefix' => 'Expense', 'journalPrefix' => 'Journal / contra', 'amcPrefix' => 'AMC contract', 'servicePrefix' => 'Service complaint',
        ],
        'Email (SMTP)' => [
            'smtpEnabled' => ['Send email through this SMTP server', 'bool'],
            'smtpHost' => 'SMTP host (Hostinger: smtp.hostinger.com · Gmail: smtp.gmail.com)', 'smtpPort' => ['Port (465 for SSL, 587 for TLS)', 'number'],
            'smtpCrypto' => ['Encryption (set automatically for ports 465 / 587)', 'select', ['ssl' => 'SSL (port 465)', 'tls' => 'TLS / STARTTLS (port 587)', '' => 'None']],
            'smtpUser' => 'SMTP username (full email address)',
            'smtpPass' => ['SMTP password (Gmail: an App Password, not your Gmail password)', 'password'], 'mailFromEmail' => 'From email (usually same as username)',
            'mailFromName' => 'From name (e.g. your company name)',
            'emailUseQueue' => ['Send staff notification emails through the queue (needs the cron job)', 'bool'],
        ],
        'Quotation' => [
            'quoteValidDays' => ['Quotation valid for (days)', 'number'],
            'quoteUnitsPerKw' => 'Units generated per kW per day (for savings)',
            'quoteTariff' => 'Electricity tariff ₹ per unit (for savings)',
            'quoteTerms' => ['Default terms printed on quotations', 'textarea'],
        ],
        'IndiaMART leads' => [
            'indiamartEnabled' => ['Import IndiaMART enquiries as leads', 'bool'],
            'indiamartKey'     => ['IndiaMART CRM key (Lead Manager → CRM integration)', 'password'],
        ],
        'Meta (Facebook / Instagram) leads' => [
            'metaEnabled'   => ['Import Facebook / Instagram Lead Ads as leads', 'bool'],
            'metaPageToken' => ['Page access token (long-lived)', 'password'],
            'metaAppSecret' => ['App secret (to verify calls from Meta)', 'password'],
        ],
        'Google Ads leads' => [
            'googleEnabled' => ['Import Google Ads lead-form leads', 'bool'],
        ],
        'AMC' => [
            'amcReminderDays' => ['Remind this many days before a service visit', 'number'],
        ],
        'WhatsApp (WeBase Cloud API)' => [
            'whatsappEnabled' => ['Send WhatsApp messages', 'bool'],
            'whatsappBaseUrl' => 'API base URL', 'whatsappVendorUid' => 'Vendor UID',
            'whatsappApiToken' => ['API token', 'password'], 'whatsappFromPhoneId' => 'From phone number ID (optional)',
        ],
        'Tally ledger names' => [
            'tallySalesLedger' => 'Sales ledger', 'tallyPurchaseLedger' => 'Purchase ledger',
            'tallyOutputCgst' => 'Output CGST ledger ({rate} = half rate)', 'tallyOutputSgst' => 'Output SGST ledger',
            'tallyOutputIgst' => 'Output IGST ledger ({rate} = full rate)', 'tallyInputCgst' => 'Input CGST ledger',
            'tallyInputSgst' => 'Input SGST ledger', 'tallyInputIgst' => 'Input IGST ledger',
        ],
    ];

    public function index()
    {
        $this->requirePermission('settings.manage');

        $fields = array_diff_key($this->fields, array_flip(\App\Libraries\Features::hiddenSettings()));

        return $this->render('settings/index', ['fields' => $fields]);
    }

    public function save()
    {
        $this->requirePermission('settings.manage');
        foreach ($this->fields as $group) {
            foreach ($group as $key => $def) {
                $type  = is_array($def) ? $def[1] : 'text';
                $value = $this->request->getPost($key);
                if ($value === null) {
                    continue; // not on the form: keep the saved value
                }
                if ($type === 'password' && trim((string) $value) === '') {
                    continue; // keep the saved token when the box is left empty
                }
                if ($type === 'select' && ! array_key_exists((string) $value, $def[2])) {
                    continue;
                }
                $value = match ($type) {
                    'bool'   => (bool) $value,
                    'number' => (int) $value,
                    'textarea' => trim(str_replace("\r\n", "\n", (string) $value)),
                    default  => trim((string) $value),
                };
                if ($value !== setting('Solar.' . $key)) {
                    setting('Solar.' . $key, $value);
                }
            }
        }

        // Encryption always matches the standard ports (465 = SSL, 587 = TLS).
        $port = (int) setting('Solar.smtpPort');
        if (isset([465 => 1, 587 => 1][$port])) {
            setting('Solar.smtpCrypto', $port === 465 ? 'ssl' : 'tls');
        }

        // Logo / authorised signature images.
        foreach (['logo' => 'logoPath', 'signature' => 'signaturePath', 'seal' => 'sealPath'] as $field => $key) {
            if ($this->request->getPost('remove_' . $field)) {
                @unlink(WRITEPATH . 'uploads/' . setting('Solar.' . $key));
                setting('Solar.' . $key, '');
                continue;
            }
            $file = $this->request->getFile($field);
            if ($file && $file->isValid() && ! $file->hasMoved()) {
                $ext = strtolower($file->getClientExtension() ?: $file->guessExtension());
                if (! in_array($ext, ['png', 'jpg', 'jpeg'], true) || $file->getSizeByUnit('mb') > 2) {
                    return $this->back(ucfirst($field) . ': use a PNG or JPG image up to 2 MB.');
                }
                $name = $field . '-' . time() . '.' . $ext;
                $file->move(WRITEPATH . 'uploads/branding', $name);
                if (setting('Solar.' . $key)) {
                    @unlink(WRITEPATH . 'uploads/' . setting('Solar.' . $key));
                }
                setting('Solar.' . $key, 'branding/' . $name);
            }
        }

        return redirect()->to('admin/settings')->with('message', 'Settings saved.');
    }

    /** Public: company logo / signature image (logo is shown on the login page). */
    public function branding($which)
    {
        $key  = ['signature' => 'signaturePath', 'seal' => 'sealPath'][$which] ?? 'logoPath';
        if ($which !== 'logo' && ! auth()->loggedIn()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $rel  = (string) setting('Solar.' . $key);
        $path = $rel ? realpath(WRITEPATH . 'uploads/' . $rel) : false;
        if (! $path || ! str_starts_with($path, realpath(WRITEPATH . 'uploads'))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path))->setHeader('Cache-Control', 'max-age=3600')->setBody(file_get_contents($path));
    }

    /** Sends a test email with the saved SMTP settings. */
    public function testEmail()
    {
        $this->requirePermission('settings.manage');
        $to = trim((string) $this->request->getPost('test_to'));
        $r  = \App\Libraries\Mailer::send($to, 'Test email – ' . setting('Solar.companyName'), "This is a test email from Sygma Solar Suite.\n\nIf you can read this, email sending works.");

        return redirect()->to('admin/settings#email')->with($r['ok'] ? 'message' : 'error', $r['ok'] ? 'Test email sent to ' . $to . '. Check the inbox (and spam folder).' : 'Test email failed: ' . mb_strimwidth((string) $r['error'], 0, 400, '…'));
    }
}
