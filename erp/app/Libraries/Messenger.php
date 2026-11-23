<?php

namespace App\Libraries;

use Throwable;

/**
 * Customer messages by email and WhatsApp for business events.
 * Both are sent in the background by the queue worker.
 *
 * WhatsApp needs an approved template mapped to the event in
 * Admin → WhatsApp Templates. Its "params" list (e.g. "customer_name,amount")
 * picks values from $vars in that order (field_1, field_2…).
 */
class Messenger
{
    /** Events and the variables each one provides. */
    public const EVENTS = [
        'invoice_created'        => 'customer_name, invoice_no, invoice_date, total, capacity_kw',
        'payment_received'       => 'customer_name, amount, payment_date, invoice_no, receipt_no, balance',
        'survey_approved'        => 'customer_name, project_no',
        'material_dispatched'    => 'customer_name, project_no, dispatch_no, vehicle_no',
        'material_delivered'     => 'customer_name, project_no, dispatch_no',
        'installation_completed' => 'customer_name, project_no, capacity_kw',
        'project_commissioned'   => 'customer_name, project_no, commissioned_on, amc_end',
        'subsidy_received'       => 'customer_name, project_no',
        'amc_visit_due'          => 'customer_name, project_no, due_date, visit_no',
    ];

    /**
     * @param list<array{path:string,name:string}> $attachments
     *
     * @return string short result for the screen, e.g. "Email sent to x@y.com."
     */
    public static function customer(string $event, array $customer, array $vars, string $subject, string $body, bool $email = true, array $attachments = []): string
    {
        $vars['customer_name'] ??= $customer['name'] ?? '';
        $result = '';

        if ($email) {
            if (empty($customer['email'])) {
                $result = 'Customer has no email address – email not sent.';
            } else {
                $track = ! empty($vars['status_link']) ? "\n\nFollow your project: " . $vars['status_link'] : '';
                $r = Mailer::send($customer['email'], $subject, $body . $track . "\n\nRegards,\n" . setting('Solar.companyName'), $attachments);
                $result = $r['ok'] ? 'Email sent to ' . $customer['email'] . '.' : 'Email to ' . $customer['email'] . ' FAILED: ' . mb_strimwidth((string) ($r['error'] ?? ''), 0, 450, '…');
                self::log('email', $customer['email'], $event, ['subject' => $subject], $r);
            }
            foreach ($attachments as $a) {
                @unlink($a['path']);
            }
        }

        try {
            $tpl = db_connect()->table('whatsapp_templates')->where(['event' => $event, 'is_active' => 1])->get()->getRowArray();
        } catch (Throwable) {
            $tpl = null;
        }
        if ($tpl && ! empty($customer['phone']) && WhatsApp::isEnabled()) {
            $params = [];
            foreach (array_filter(array_map('trim', explode(',', (string) $tpl['params']))) as $key) {
                $params[] = (string) ($vars[$key] ?? '');
            }
            try {
                service('queue')->push('whatsapp', 'whatsapp', [
                    'phone' => $customer['phone'], 'template' => $tpl['template_name'],
                    'language' => $tpl['language'], 'params' => $params, 'event' => $event,
                ]);
            } catch (Throwable $e) {
                log_message('error', 'Could not queue WhatsApp: ' . $e->getMessage());
            }
            $result .= ' WhatsApp queued.';
        }

        return trim($result);
    }

    public static function log(string $channel, string $to, string $event, array $payload, array $result): void
    {
        db_connect()->table('message_log')->insert([
            'channel' => $channel, 'recipient' => $to, 'event' => $event,
            'payload' => json_encode($payload), 'status' => ! empty($result['ok']) ? 'sent' : 'failed',
            'response' => json_encode($result), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
