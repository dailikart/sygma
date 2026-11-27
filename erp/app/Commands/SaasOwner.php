<?php

namespace App\Commands;

use App\Libraries\Saas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SaasOwner extends BaseCommand
{
    protected $group       = 'Sygma SaaS';
    protected $name        = 'saas:owner';
    protected $description = 'Adds an owner login, or sets its password: saas:owner email password [name]';
    protected $usage       = 'saas:owner <email> <password> [name]';

    public function run(array $params)
    {
        [$email, $password, $name] = array_pad($params, 3, null);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 10) {
            CLI::error('Give an email and a password of at least 10 characters.');

            return 1;
        }
        Saas::install();
        $db  = Saas::db();
        $row = $db->table(Saas::T_OWNERS)->where('email', strtolower($email))->get()->getRowArray();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($row) {
            $db->table(Saas::T_OWNERS)->where('id', $row['id'])->update(['password_hash' => $hash] + ($name ? ['name' => $name] : []));
            CLI::write("Password changed for {$email}.", 'green');
        } else {
            $db->table(Saas::T_OWNERS)->insert(['name' => $name ?: 'Owner', 'email' => strtolower($email), 'password_hash' => $hash, 'created_at' => date('Y-m-d H:i:s')]);
            CLI::write("Owner {$email} added.", 'green');
        }

        return 0;
    }
}
