<?php

namespace App\Commands;

use App\Libraries\Features;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Modules & options for this company from the command line.
 *
 *   php spark features                      list with on / off
 *   php spark features off amc invoices.loan
 *   php spark features on amc
 */
class FeaturesCommand extends BaseCommand
{
    protected $group       = 'Solar';
    protected $name        = 'features';
    protected $description = 'Lists or switches modules / options on or off for this company.';
    protected $usage       = 'features [on|off] [key ...]';

    public function run(array $params)
    {
        helper('setting');
        $action = strtolower($params[0] ?? 'list');
        $keys   = array_slice($params, 1);
        $all    = Features::all();

        if (in_array($action, ['on', 'off'], true)) {
            $unknown = array_diff($keys, array_keys($all));
            if ($keys === [] || $unknown !== []) {
                CLI::error($keys === [] ? 'Name at least one module or option.' : 'Unknown: ' . implode(', ', $unknown));
                $action = 'list';
            } else {
                $saved = (array) setting('Features.off');
                $saved = $action === 'off' ? array_merge($saved, $keys) : array_diff($saved, $keys);
                Features::save($saved);
                CLI::write('Saved.', 'green');
            }
        }

        foreach ($all as $key => $def) {
            $core  = ! empty($def['core']);
            $state = Features::on($key) ? CLI::color(' on ', 'green') : CLI::color(' off', 'red');
            CLI::write(sprintf('%s  %-24s %s%s', $state, $key, str_contains($key, '.') ? '  · ' : '', $def['title'] . ($core ? ' (core)' : '')));
        }

        return EXIT_SUCCESS;
    }
}
