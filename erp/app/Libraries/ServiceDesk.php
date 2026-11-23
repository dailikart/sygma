<?php

namespace App\Libraries;

/** Service complaints / tickets: create, update history, who may act. */
class ServiceDesk
{
    public const CATEGORIES = [
        'no_generation'  => 'No generation / system off',
        'low_generation' => 'Low generation',
        'inverter_error' => 'Inverter error / display',
        'damage'         => 'Panel / structure damage',
        'wiring'         => 'Wiring / MCB / earthing',
        'net_meter'      => 'Net meter / EB issue',
        'cleaning'       => 'Cleaning request',
        'monitoring'     => 'Monitoring app / WiFi',
        'other'          => 'Other',
    ];
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    public const STATUSES   = ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
    public const VIA        = ['phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'walk_in' => 'Walk-in', 'email' => 'Email', 'status_page' => 'Customer status page', 'amc_visit' => 'During AMC visit'];

    /** Is the project inside an active AMC contract today? */
    public static function underAmc(?int $projectId): bool
    {
        if (! $projectId) {
            return false;
        }
        $today = date('Y-m-d');

        return (bool) db_connect()->table('amc_contracts')->where('project_id', $projectId)
            ->where('start_date <=', $today)->where('end_date >=', $today)->countAllResults();
    }

    /** Creates the ticket, logs it, tells the service team and acknowledges the customer. Returns the ticket id. */
    public static function create(int $customerId, ?int $projectId, string $category, string $priority, string $description, string $via, ?string $phone = null, ?int $userId = null): int
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->table('service_tickets')->insert([
            'ticket_no'     => Numbering::next('service_tickets', 'ticket_no', (string) setting('Solar.servicePrefix')),
            'customer_id'   => $customerId, 'project_id' => $projectId,
            'category'      => isset(self::CATEGORIES[$category]) ? $category : 'other',
            'priority'      => isset(self::PRIORITIES[$priority]) ? $priority : 'normal',
            'description'   => trim($description) ?: null, 'reported_via' => isset(self::VIA[$via]) ? $via : 'phone',
            'contact_phone' => $phone ?: null, 'status' => 'open', 'under_amc' => self::underAmc($projectId) ? 1 : 0,
            'created_by'    => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        self::addUpdate($id, 'open', 'Complaint registered (' . self::VIA[isset(self::VIA[$via]) ? $via : 'phone'] . ').', null, $userId);
        $t = $db->table('service_tickets t')->select('t.*, c.name, c.email, c.phone')->join('customers c', 'c.id = t.customer_id')->where('t.id', $id)->get()->getRowArray();
        Notifier::role('service', 'New complaint ' . $t['ticket_no'] . ' – ' . $t['name'], self::CATEGORIES[$t['category']] . ($t['priority'] === 'urgent' ? ' (URGENT)' : ''), 'admin/service/' . $id);
        Messenger::customer('service_ticket_created', ['name' => $t['name'], 'email' => $t['email'], 'phone' => $t['phone']], [
            'ticket_no' => $t['ticket_no'], 'category' => self::CATEGORIES[$t['category']],
        ] + ($projectId ? ['status_link' => StatusLink::url($projectId)] : []), 'Complaint registered – ' . $t['ticket_no'],
            "Dear {$t['name']},\n\nWe have registered your complaint {$t['ticket_no']} (" . self::CATEGORIES[$t['category']] . "). Our service team will contact you shortly.");

        return $id;
    }

    public static function addUpdate(int $ticketId, ?string $status, ?string $note, ?string $photo = null, ?int $userId = null): void
    {
        db_connect()->table('service_ticket_updates')->insert([
            'ticket_id' => $ticketId, 'status' => $status, 'note' => $note, 'photo_path' => $photo,
            'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Office staff with AMC / service rights, or the technician the ticket is assigned to. */
    public static function canWork(array $ticket): bool
    {
        return can('amc.manage') || ((int) $ticket['assigned_to'] && (int) $ticket['assigned_to'] === (int) auth()->id());
    }

    /** Open tickets assigned to the user (for My Tasks / dashboard). */
    public static function forUser(int $userId): array
    {
        return db_connect()->table('service_tickets t')->select('t.*, c.name AS customer, c.city, c.phone')
            ->join('customers c', 'c.id = t.customer_id')->where('t.assigned_to', $userId)->whereIn('t.status', ['assigned', 'in_progress', 'open'])
            ->orderBy("FIELD(t.priority, 'urgent', 'high', 'normal', 'low')", '', false)->orderBy('t.scheduled_date')->get()->getResultArray();
    }
}
