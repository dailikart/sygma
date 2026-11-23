<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Company and accounting defaults.
 *
 * Every value here can be changed from Admin → Settings. Changes are saved in
 * the `settings` table and read with setting('Solar.<name>').
 */
class Solar extends BaseConfig
{
    public string $companyName    = 'Sygma Solar Suite';
    public string $companyAddress = '';
    public string $companyPhone   = '';
    public string $companyEmail   = '';
    public string $companyGstin   = '';

    /** Shown under the company name on the login page. */
    public string $companyTagline = 'Powering homes with the sun';

    /** Used to decide CGST+SGST (same state) or IGST (other state). */
    public string $companyState = 'Tamil Nadu';

    public string $currency = '₹';

    /** Number prefixes. The financial year and a running number are added. */
    public string $invoicePrefix  = 'INV';
    public string $receiptPrefix  = 'RCT';
    public string $projectPrefix  = 'PRJ';
    public string $poPrefix       = 'PO';
    public string $purchasePrefix = 'PUR';
    public string $returnPrefix   = 'PRN';
    public string $dispatchPrefix = 'DSP';
    public string $supplierPayPrefix = 'SPAY';
    public string $incomePrefix   = 'INC';
    public string $expensePrefix  = 'EXP';
    public string $journalPrefix  = 'JV';
    public string $amcPrefix      = 'AMC';

    /** Days before an AMC visit to remind the customer and service team. */
    public int $amcReminderDays = 3;

    // ── Appearance / branding ──
    /** default | ocean | forest | sunset | purple | crimson | teal | solar */
    public string $theme         = '';
    public string $logoPath      = '';
    public string $signaturePath = '';
    public string $sealPath      = '';

    // ── Email (SMTP) ── Hostinger: smtp.hostinger.com, 465, ssl
    public bool $smtpEnabled     = false;
    public string $smtpHost      = 'smtp.hostinger.com';
    public int $smtpPort         = 465;
    public string $smtpCrypto    = 'ssl';
    public string $smtpUser      = '';
    public string $smtpPass      = '';
    public string $mailFromEmail = '';
    public string $mailFromName  = '';
    /** false = staff notification emails are sent immediately (no cron needed). */
    public bool $emailUseQueue   = false;

    // ── WhatsApp (WeBase Cloud API) ──
    public bool $whatsappEnabled       = false;
    public string $whatsappBaseUrl     = 'https://whatsappcloud.webase.co.in/api';
    public string $whatsappVendorUid   = '';
    public string $whatsappApiToken    = '';
    public string $whatsappFromPhoneId = '';

    // ── Tally ledger names (must match the ledgers in your Tally company) ──
    public string $tallySalesLedger    = 'Sales';
    public string $tallyPurchaseLedger = 'Purchase';
    /** {rate} is replaced with the half rate (CGST/SGST) or full rate (IGST), e.g. 9 or 18. */
    public string $tallyOutputCgst = 'Output CGST @ {rate}%';
    public string $tallyOutputSgst = 'Output SGST @ {rate}%';
    public string $tallyOutputIgst = 'Output IGST @ {rate}%';
    public string $tallyInputCgst  = 'Input CGST @ {rate}%';
    public string $tallyInputSgst  = 'Input SGST @ {rate}%';
    public string $tallyInputIgst  = 'Input IGST @ {rate}%';
}
