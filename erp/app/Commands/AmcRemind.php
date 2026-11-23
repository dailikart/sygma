<?php

namespace App\Commands;

use App\Libraries\Amc;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Daily AMC reminders. Cron: 0 8 * * * cd /var/www/solar && php spark amc:remind
 */
class AmcRemind extends BaseCommand
{
    protected $group       = 'Solar';
    protected $name        = 'amc:remind';
    protected $description = 'Sends reminders for AMC service visits that are due soon and expires old contracts.';

    public function run(array $params)
    {
        helper(['app', 'setting', 'url']);
        if (! \App\Libraries\Features::on('amc')) {
            CLI::write('AMC module is switched off for this company. Nothing to do.');

            return;
        }
        $r = Amc::sendReminders();
        CLI::write("Reminders sent: {$r['reminded']}. Contracts expired: {$r['expired']}.", 'green');
    }
}
