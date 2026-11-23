<?php

namespace App\Commands;

use App\Filters\DemoGuard;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Demo site only: sets every demo login's password to `demo.password` from
 * .env without touching the data. (demo:reset also applies it every night.)
 */
class DemoPassword extends BaseCommand
{
    protected $group       = 'Solar';
    protected $name        = 'demo:password';
    protected $description = 'Demo only: sets all demo logins to demo.password from .env (data is kept).';

    public function run(array $params)
    {
        if (! DemoGuard::enabled()) {
            CLI::error('Refused: demo.mode is not true in .env.');

            return EXIT_ERROR;
        }
        if (! DemoReset::applyPassword()) {
            CLI::error('Set demo.password in .env first.');

            return EXIT_ERROR;
        }
        CLI::write('Demo password updated for every login.', 'green');

        return EXIT_SUCCESS;
    }
}
