<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

/**
 * Creates the first Super Admin login.
 *
 * Set these in .env first (never commit real passwords):
 *   admin.email    = you@company.com
 *   admin.password = 'a-strong-password'
 *
 * Run: php spark db:seed AdminSeeder
 */
class AdminSeeder extends Seeder
{
    public function run()
    {
        $email    = env('admin.email');
        $password = env('admin.password');

        if (empty($email) || empty($password)) {
            echo "Set admin.email and admin.password in .env, then run this again.\n";

            return;
        }

        $users = auth()->getProvider();

        if ($users->findByCredentials(['email' => $email]) !== null) {
            echo "User {$email} already exists. Nothing to do.\n";

            return;
        }

        $user = new User([
            'username' => 'admin',
            'email'    => $email,
            'password' => $password,
        ]);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        $user->activate();

        echo "Super Admin {$email} created.\n";
    }
}
