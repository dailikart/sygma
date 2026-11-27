<?php

namespace App\Commands;

use App\Libraries\Saas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SaasInstall extends BaseCommand
{
    protected $group       = 'Sygma SaaS';
    protected $name        = 'saas:install';
    protected $description = 'Creates the owner tables (saas_companies, saas_owners, saas_log).';

    public function run(array $params)
    {
        Saas::install();
        CLI::write('Owner tables ready.', 'green');
    }
}
