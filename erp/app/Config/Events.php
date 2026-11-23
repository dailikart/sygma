<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

// User log: logins, logouts and failed login attempts.
$authLog = static function (string $action, ?int $userId, string $text): void {
    try {
        db_connect()->table('activity_log')->insert([
            'user_id' => $userId, 'entity' => 'audit', 'action' => $action,
            'description' => $text . ' | IP ' . service('request')->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable) {
    }
};
Events::on('login', static fn ($user) => $authLog('login', (int) $user->id, 'Logged in'));
Events::on('logout', static fn ($user) => $authLog('logout', $user ? (int) $user->id : null, 'Logged out'));
Events::on('failedLogin', static fn ($credentials) => $authLog('failed_login', null, 'Failed login for ' . ($credentials['email'] ?? $credentials['username'] ?? '?')));

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});
