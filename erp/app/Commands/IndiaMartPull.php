<?php

namespace App\Commands;

use App\Libraries\IndiaMart;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Cron: php spark indiamart:pull   (every 10 minutes is enough; IndiaMART allows one call per 5 minutes). */
class IndiaMartPull extends BaseCommand
{
    protected $group       = 'Solar';
    protected $name        = 'indiamart:pull';
    protected $description = 'Imports new IndiaMART enquiries as leads.';

    public function run(array $params)
    {
        helper(['app', 'setting']);
        if (! setting('Solar.indiamartEnabled') || trim((string) setting('Solar.indiamartKey')) === '') {
            CLI::write('IndiaMART import is off or the CRM key is missing (Settings).');

            return;
        }
        try {
            $r = IndiaMart::pull();
            CLI::write($r['message']);
        } catch (\RuntimeException $e) {
            CLI::write($e->getMessage());
        }
    }
}
