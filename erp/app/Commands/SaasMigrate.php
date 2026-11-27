<?php

namespace App\Commands;

use App\Libraries\Saas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SaasMigrate extends BaseCommand
{
    protected $group       = 'Sygma SaaS';
    protected $name        = 'saas:migrate';
    protected $description = 'Database updates for every company (or --company=code).';

    public function run(array $params)
    {
        $code = Saas::opt('company');
        $list = $code ? array_filter([Saas::findByCode((string) $code)]) : Saas::companies();
        if (! $list) {
            CLI::error($code ? "No company {$code}." : 'No companies.');

            return 1;
        }
        $fail = 0;
        foreach ($list as $c) {
            try {
                $n = Saas::within($c, static fn () => Saas::runMigrations());
                CLI::write("{$c['code']}: " . ($n ? "{$n} update(s) applied" : 'up to date'), 'green');
                Saas::log('migrate', (int) $c['id'], "{$n} update(s) (command line)");
            } catch (\Throwable $e) {
                $fail++;
                CLI::error("{$c['code']}: " . $e->getMessage());
            }
        }

        return $fail ? 1 : 0;
    }
}
