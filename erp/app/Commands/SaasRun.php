<?php

namespace App\Commands;

use App\Libraries\Saas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Cron jobs in multi-company mode, e.g.
 *   php spark saas:run "queue:work emails --stop-when-empty"
 *   php spark saas:run "amc:remind"
 */
class SaasRun extends BaseCommand
{
    protected $group       = 'Sygma SaaS';
    protected $name        = 'saas:run';
    protected $description = 'Runs a company command for every active company (or --company=code).';
    protected $usage       = 'saas:run "<command and options>" [--company=code]';

    public function run(array $params)
    {
        $cmd = trim((string) ($params[0] ?? ''));
        if ($cmd === '' || str_starts_with($cmd, 'saas')) {
            CLI::error('Give the command in quotes, e.g. php spark saas:run "amc:remind"');

            return 1;
        }
        $code = Saas::opt('company');
        $list = $code ? array_filter([Saas::findByCode((string) $code)]) : Saas::companies();
        foreach ($list as $c) {
            if (! $code && ($c['status'] === 'suspended' || $c['status'] === 'setup' || ($c['expires_on'] && $c['expires_on'] < date('Y-m-d')))) {
                continue;
            }
            CLI::write("== {$c['code']} ({$c['name']})", 'yellow');
            try {
                $out = Saas::within($c, static fn () => command($cmd));
                if (is_string($out) && trim($out) !== '') {
                    CLI::write(rtrim($out));
                }
            } catch (\Throwable $e) {
                CLI::error($e->getMessage());
            }
        }

        return 0;
    }
}
