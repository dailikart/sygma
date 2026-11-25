<?php

/**
 * Small view/controller helpers used across the Solar CRM.
 */

if (! function_exists('money')) {
    function money($amount, bool $symbol = true): string
    {
        $n = (float) $amount;
        // Indian digit grouping: 12,34,567.89
        $neg   = $n < 0;
        $parts = explode('.', number_format(abs($n), 2, '.', ''));
        $int   = $parts[0];
        if (strlen($int) > 3) {
            $last = substr($int, -3);
            $rest = substr($int, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int  = $rest . ',' . $last;
        }

        return ($neg ? '-' : '') . ($symbol ? setting('Solar.currency') : '') . $int . '.' . $parts[1];
    }
}

if (! function_exists('qty')) {
    function qty($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }
}

if (! function_exists('fdate')) {
    function fdate(?string $date, bool $time = false): string
    {
        if (empty($date)) {
            return '—';
        }

        return date($time ? 'd-m-Y h:i A' : 'd-m-Y', strtotime($date));
    }
}

if (! function_exists('asset')) {
    /** URL of a file in public/ with ?v=<last change>, so browsers never keep an old copy after an update. */
    function asset(string $path): string
    {
        $file = FCPATH . ltrim($path, '/');

        return base_url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}

if (! function_exists('can')) {
    function can(string $permission): bool
    {
        $u = auth()->user();

        return $u !== null && $u->can($permission);
    }
}

if (! function_exists('fy_range')) {
    /** [from, to] from the query string, default = current April–March financial year. */
    function fy_range(): array
    {
        $req  = service('request');
        $from = $req->getGet('from');
        $to   = $req->getGet('to');
        if (! $from || ! $to) {
            $y     = (int) date('Y');
            $start = ((int) date('n') >= 4) ? $y : $y - 1;
            $from  = $start . '-04-01';
            $to    = ($start + 1) . '-03-31';
        }

        return [date('Y-m-d', strtotime($from)), date('Y-m-d', strtotime($to))];
    }
}

if (! function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        $map = [
            'unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success', 'cancelled' => 'secondary',
            'active' => 'primary', 'completed' => 'success', 'on_hold' => 'warning', 'pending' => 'secondary',
            'done' => 'success', 'rejected' => 'danger', 'draft' => 'secondary', 'dispatched' => 'info',
            'delivered' => 'success', 'open' => 'primary', 'received' => 'success',
            'new' => 'info', 'contacted' => 'primary', 'follow_up' => 'warning', 'interested' => 'primary',
            'confirmed' => 'success', 'lost' => 'secondary',
            'b2b' => 'dark', 'b2c' => 'info', 'subsidy' => 'success', 'non_subsidy' => 'secondary',
            'redeemed' => 'success', 'applied' => 'info', 'under_review' => 'warning', 'sanctioned' => 'primary',
            'refunded' => 'danger', 'not_applied' => 'secondary',
        ];
        $labels = [
            'partial' => 'Partially Paid', 'on_hold' => 'On Hold', 'follow_up' => 'Follow-up',
            'b2b' => 'B2B', 'b2c' => 'B2C', 'non_subsidy' => 'Non-subsidy', 'under_review' => 'Under review',
            'redeemed' => 'Subsidy received', 'not_applied' => 'Not applied',
        ];
        $s = (string) $status;

        return '<span class="badge text-bg-' . ($map[$s] ?? 'light') . '">' . esc($labels[$s] ?? ucfirst(str_replace('_', ' ', $s))) . '</span>';
    }
}

if (! function_exists('user_name')) {
    function user_name($userId): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = array_column(db_connect()->table('users')->select('id, username')->get()->getResultArray(), 'username', 'id');
        }

        return $userId ? ($cache[$userId] ?? ('#' . $userId)) : '—';
    }
}

if (! function_exists('staff_options')) {
    /** [id => username] of active users, optionally only those in $role. */
    function staff_options(?string $role = null): array
    {
        $b = db_connect()->table('users u')->select('u.id, u.username')->where('u.active', 1)->where('u.deleted_at', null)
            ->groupStart()->where('u.status', null)->orWhere('u.status !=', 'banned')->groupEnd();
        if ($role) {
            $b->join('auth_groups_users g', 'g.user_id = u.id')->where('g.group', $role);
        }

        return array_column($b->orderBy('u.username')->get()->getResultArray(), 'username', 'id');
    }
}

if (! function_exists('options')) {
    /** [id => name] from a table (active rows only when it has is_active). */
    function options(string $table, string $label = 'name', bool $activeOnly = true): array
    {
        $db = db_connect();
        $b  = $db->table($table)->select('id, ' . $label);
        if ($activeOnly && $db->fieldExists('is_active', $table)) {
            $b->where('is_active', 1);
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $b->where('deleted_at', null);
        }

        return array_column($b->orderBy($label)->get()->getResultArray(), $label, 'id');
    }
}

if (! function_exists('select_options')) {
    function select_options(array $options, $selected = null, string $placeholder = ''): string
    {
        $html = $placeholder !== '' ? '<option value="">' . esc($placeholder) . '</option>' : '';
        foreach ($options as $value => $label) {
            $sel = ((string) $value === (string) $selected) ? ' selected' : '';
            $html .= '<option value="' . esc((string) $value) . '"' . $sel . '>' . esc((string) $label) . '</option>';
        }

        return $html;
    }
}

if (! function_exists('unread_notifications')) {
    function unread_notifications(): int
    {
        return auth()->id() ? db_connect()->table('notifications')
            ->where(['user_id' => auth()->id(), 'read_at' => null])->countAllResults() : 0;
    }
}

if (! function_exists('amount_in_words')) {
    function amount_in_words(float $amount): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens  = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two   = static fn (int $n) => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        $three = static fn (int $n) => trim(($n >= 100 ? $ones[intdiv($n, 100)] . ' Hundred ' : '') . $two($n % 100));

        $rupees = (int) floor($amount);
        $paise  = (int) round(($amount - $rupees) * 100);
        $parts  = [];
        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $div => $word) {
            if ($rupees >= $div) {
                $parts[] = ($div === 10000000 ? $three(intdiv($rupees, $div)) : $two(intdiv($rupees, $div))) . ' ' . $word;
                $rupees %= $div;
            }
        }
        if ($rupees > 0) {
            $parts[] = $three($rupees);
        }
        $words = trim(implode(' ', $parts)) ?: 'Zero';

        return 'Rupees ' . $words . ($paise ? ' and ' . $two($paise) . ' Paise' : '') . ' Only';
    }
}

if (! function_exists('brand_img')) {
    /** <img> of the company logo / signature for PDFs (local file path), or '' when not uploaded. */
    function brand_img(string $which, string $style = ''): string
    {
        $rel = (string) setting(['signature' => 'Solar.signaturePath', 'seal' => 'Solar.sealPath'][$which] ?? 'Solar.logoPath');
        $path = $rel !== '' ? WRITEPATH . 'uploads/' . $rel : '';

        return $path !== '' && is_file($path) ? '<img src="' . esc($path) . '" style="' . esc($style) . '">' : '';
    }
}

if (! function_exists('gst_state_code')) {
    /** GST state code ("33") from a GSTIN, else from the state name. */
    function gst_state_code(?string $state, ?string $gstin = null): string
    {
        if ($gstin && preg_match('/^(\d{2})/', $gstin, $m)) {
            return $m[1];
        }
        $codes = [
            'jammu and kashmir' => '01', 'himachal pradesh' => '02', 'punjab' => '03', 'chandigarh' => '04', 'uttarakhand' => '05',
            'haryana' => '06', 'delhi' => '07', 'rajasthan' => '08', 'uttar pradesh' => '09', 'bihar' => '10', 'sikkim' => '11',
            'arunachal pradesh' => '12', 'nagaland' => '13', 'manipur' => '14', 'mizoram' => '15', 'tripura' => '16', 'meghalaya' => '17',
            'assam' => '18', 'west bengal' => '19', 'jharkhand' => '20', 'odisha' => '21', 'chhattisgarh' => '22', 'madhya pradesh' => '23',
            'gujarat' => '24', 'dadra and nagar haveli and daman and diu' => '26', 'maharashtra' => '27', 'karnataka' => '29', 'goa' => '30',
            'lakshadweep' => '31', 'kerala' => '32', 'tamil nadu' => '33', 'puducherry' => '34', 'andaman and nicobar islands' => '35',
            'telangana' => '36', 'andhra pradesh' => '37', 'ladakh' => '38',
        ];

        return $codes[strtolower(trim((string) $state))] ?? '';
    }
}

if (! function_exists('eb_charges_options')) {
    /** Who pays the EB / meter charges. */
    function eb_charges_options(): array
    {
        return ['' => '— Not decided —', 'company' => 'Company (included in our price)', 'customer' => 'Customer (extra, beyond the invoice)'];
    }
}

if (! function_exists('eb_charges_input')) {
    /** ['eb_charges_by' => …, 'eb_charges_amount' => …] from posted fields. */
    function eb_charges_input(array $p): array
    {
        $by = in_array($p['eb_charges_by'] ?? '', ['company', 'customer'], true) ? $p['eb_charges_by'] : null;

        return ['eb_charges_by' => $by, 'eb_charges_amount' => $by === 'customer' ? round(max(0, (float) str_replace(',', '', (string) ($p['eb_charges_amount'] ?? 0))), 2) : null];
    }
}

if (! function_exists('eb_charges_extra')) {
    /** Amount the customer pays beyond the invoice for EB / meter charges (0 when the company pays). */
    function eb_charges_extra(array $row): float
    {
        return ($row['eb_charges_by'] ?? null) === 'customer' ? (float) ($row['eb_charges_amount'] ?? 0) : 0.0;
    }
}

if (! function_exists('eb_charges_label')) {
    /** "–" when the company pays, the amount when the customer pays, "" when not decided. */
    function eb_charges_label(array $row): string
    {
        return match ($row['eb_charges_by'] ?? null) {
            'company'  => '–',
            'customer' => money($row['eb_charges_amount'] ?? 0),
            default    => '',
        };
    }
}
