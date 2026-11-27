<?php

namespace App\Commands;

use App\Libraries\Saas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SaasCreate extends BaseCommand
{
    protected $group       = 'Sygma SaaS';
    protected $name        = 'saas:create';
    protected $description = 'Creates a company with its tables and first Super Admin.';
    protected $usage       = 'saas:create --code=sun --name="Sun Power" --domain=sun.example.com --admin-email=a@b.com --admin-password=... [--off=amc,designs] [--expires=YYYY-MM-DD] [--max-users=N] [--state="Tamil Nadu"]';

    public function run(array $params)
    {
        $o = static fn (string $k) => Saas::opt($k);
        try {
            $c = Saas::createCompany([
                'code' => (string) $o('code'), 'name' => (string) ($o('name') ?: $o('code')), 'domain' => (string) $o('domain'),
                'admin_email' => (string) $o('admin-email'), 'admin_password' => (string) $o('admin-password'),
                'off' => array_filter(explode(',', (string) $o('off'))), 'expires_on' => (string) $o('expires'),
                'max_users' => (string) $o('max-users'), 'state' => (string) $o('state'), 'plan' => (string) $o('plan'),
            ]);
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());

            return 1;
        }
        CLI::write("Company {$c['name']} ({$c['code']}) created at {$c['domain']} with tables {$c['db_prefix']}*.", 'green');

        return 0;
    }
}
