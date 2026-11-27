<?php

namespace App\Commands;

use App\Libraries\Saas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SaasList extends BaseCommand
{
    protected $group       = 'Sygma SaaS';
    protected $name        = 'saas:list';
    protected $description = 'Lists the companies.';

    public function run(array $params)
    {
        $rows = array_map(static fn ($c) => [$c['code'], $c['name'], $c['domain'], $c['status'], $c['expires_on'] ?: '-', $c['last_seen_at'] ?: '-'], Saas::companies());
        $rows ? CLI::table($rows, ['Code', 'Name', 'Address', 'Status', 'Expires', 'Last used']) : CLI::write('No companies yet.');
    }
}
