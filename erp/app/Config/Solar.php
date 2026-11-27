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
    public string $companyCity    = '';
    public string $companyPincode = '';

    public string $currency = '₹';

    /** Number prefixes. The financial year and a running number are added. */
    public string $quotePrefix    = 'QT';
    public string $designPrefix   = 'RD';

    /** Google Maps browser key for the 3D roof designer (map search, satellite, Solar API). Can also be set as google.mapsKey in .env. */
    public string $googleMapsKey  = '';
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
    public string $servicePrefix  = 'SRV';

    // ── Quotation ──
    /** Days a quotation stays valid. */
    public int $quoteValidDays = 15;
    /** Average units generated per kW per day (used for savings / payback). */
    public string $quoteUnitsPerKw = '4';
    /** Electricity tariff (₹ per unit) used for savings. */
    public string $quoteTariff = '6.50';
    public string $quoteTerms = "1. Prices include supply, installation and commissioning.\n2. 50% advance with order, balance before dispatch.\n3. Subsidy is credited by the government directly to the customer's bank account.\n4. Panel warranty as per manufacturer; system warranty 5 years.";

    /** Quick quote rate card (JSON, edited in Quotations → Quick quote settings). */
    public string $quickQuote = '';

    /** Days before an AMC visit to remind the customer and service team. */
    public int $amcReminderDays = 3;

    // Payroll (Settings → Payroll)
    public float $pfEmployeeRate  = 12;
    public float $pfEmployerRate  = 12;
    public float $pfWageCeiling   = 15000;
    public float $esiEmployeeRate = 0.75;
    public float $esiEmployerRate = 3.25;
    public float $esiWageLimit    = 21000;
    public string $ptSlabs        = '21000:0, 30000:180, 45000:425, 60000:930, 75000:1025, 0:1250';
    public float $otRate          = 2;
    public string $weekOffDay     = 'Sun';

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

    // ── IndiaMART lead import ──
    public bool $indiamartEnabled = false;
    /** CRM key from IndiaMART seller panel → Lead Manager → CRM integration (Pull API). */
    public string $indiamartKey = '';
    public string $indiamartApiUrl = 'https://mapi.indiamart.com/wservce/crm/crmListing/v2/';
    /** Secret part of the Push API address (made automatically). */
    public string $indiamartPushToken = '';
    public string $indiamartLastPull = '';
    public string $indiamartLastCall = '';
    public string $indiamartLastResult = '';

    // ── Meta (Facebook / Instagram) Lead Ads ──
    public bool $metaEnabled = false;
    /** Page access token (long-lived) used to read each lead's details. */
    public string $metaPageToken = '';
    /** App secret, to check that calls really come from Meta. */
    public string $metaAppSecret = '';
    public string $metaVerifyToken = '';
    public string $metaGraphUrl = 'https://graph.facebook.com/v19.0/';
    public string $metaLastResult = '';

    // ── Google Ads lead form ──
    public bool $googleEnabled = false;
    /** The "key" typed into Google Ads → lead form → webhook (made automatically). */
    public string $googleKey = '';
    public string $googleLastResult = '';

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
