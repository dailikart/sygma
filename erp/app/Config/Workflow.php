<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Installation workflow, stage by stage.
 *
 * A project is created when an invoice is raised. The stages below run in order.
 * When a stage becomes active, everyone in `role` (or the assigned person)
 * gets a notification. Only users with `permission` (or the person assigned to
 * the stage) can complete it.
 */
class Workflow extends BaseConfig
{
    public array $stages = [
        'survey' => [
            'title'      => 'Site Survey & Approval',
            'permission' => 'survey.update',
            'role'       => 'surveyor',
            'icon'       => 'bi-clipboard-check',
        ],
        'dispatch' => [
            'title'      => 'Material Dispatch',
            'permission' => 'dispatch.manage',
            'role'       => 'store',
            'icon'       => 'bi-box-seam',
        ],
        'delivery' => [
            'title'      => 'Delivery at Site',
            'permission' => 'delivery.update',
            'role'       => 'delivery',
            'icon'       => 'bi-truck',
        ],
        'installation' => [
            'title'      => 'Installation',
            'permission' => 'installation.update',
            'role'       => 'installer',
            'icon'       => 'bi-tools',
        ],
        'dcr' => [
            'title'        => 'DCR & Installation Submission',
            'permission'   => 'eb.update',
            'role'         => 'liaison',
            'icon'         => 'bi-patch-check',
            'subsidy_only' => true,
            'option'       => 'projects.subsidy',
        ],
        'eb_process' => [
            'title'      => 'EB / Net Meter',
            'permission' => 'eb.update',
            'role'       => 'liaison',
            'icon'       => 'bi-lightning-charge',
            'option'     => 'projects.eb',
        ],
        'subsidy' => [
            'title'        => 'Subsidy',
            'permission'   => 'subsidy.update',
            'role'         => 'liaison',
            'icon'         => 'bi-bank',
            'subsidy_only' => true,
            'option'       => 'projects.subsidy',
        ],
    ];

    /**
     * EB / net-meter sub-steps, in order (TN Solar Homes portal / TNPDCL).
     * PCR = Project Completion Report: uploaded on the EB portal after
     * installation, using the customer documents collected at confirmation.
     * The commissioning date (last step) starts the AMC.
     */
    public array $ebSteps = [
        'acknowledgement' => 'Application acknowledged / feasibility approved',
        'eb_charges_paid' => 'EB charges paid (needs full invoice payment)',
        'sanction'        => 'Sanction received',
        'pcr_uploaded'    => 'PCR (Project Completion Report) uploaded',
        'inspection'      => 'EB field inspection done',
        'meter_installed' => 'Net meter installed & commissioned (grid connected)',
    ];

    /** Suggested titles for customer document uploads (any title can be typed). */
    public array $documentTitles = [
        'Aadhaar card', 'EB bill', 'Property tax receipt', 'Bank passbook / cancelled cheque',
        'Passport photo', 'PAN card', 'Site photo', 'Signed agreement', 'Ownership proof', 'Other',
    ];

    /** Subsidy status values (PM Surya Ghar portal). */
    public array $subsidyStatuses = [
        'not_applied'  => 'Not applied',
        'applied'      => 'Applied on portal',
        'under_review' => 'Under verification',
        'sanctioned'   => 'Sanctioned',
        'redeemed'     => 'Received by customer',
        'rejected'     => 'Rejected',
        'refunded'     => 'Refunded / reversed',
    ];

    /** Subsidy statuses that complete the stage and close the project. */
    public array $subsidyFinal = ['redeemed', 'rejected', 'refunded'];

    public array $leadStatuses = [
        'new'        => 'New',
        'contacted'  => 'Contacted',
        'follow_up'  => 'Follow-up',
        'interested' => 'Interested',
        'confirmed'  => 'Confirmed (customer)',
        'lost'       => 'Lost',
    ];

    public array $leadSources = [
        'Walk-in', 'Phone call', 'Website', 'Facebook / Instagram', 'IndiaMART',
        'JustDial', 'Google Ads', 'Referral', 'Channel partner', 'Exhibition', 'Other',
    ];
}
