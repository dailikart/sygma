<?php

namespace App\Commands;

use App\Filters\DemoGuard;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Restores the live demo: reloads docs/database/sygma_demo.sql into the
 * database from .env and puts the demo photos / documents back.
 *
 * ERASES ALL DATA. Runs only when `demo.mode = true` is set in .env.
 *
 * Cron (every night at 02:00):
 *   0 2 * * * cd /path/to/erp && php spark demo:reset >> /dev/null 2>&1
 */
class DemoReset extends BaseCommand
{
    protected $group       = 'Solar';
    protected $name        = 'demo:reset';
    protected $description = 'Live demo only: wipes the database and reloads the demo data (needs demo.mode = true in .env).';

    public function run(array $params)
    {
        if (! DemoGuard::enabled()) {
            CLI::error('Refused: demo.mode is not true in .env. This command erases every record and is only for the demo site.');

            return EXIT_ERROR;
        }

        $file = ROOTPATH . 'docs/database/sygma_demo.sql';
        if (! is_file($file)) {
            CLI::error("Missing {$file}");

            return EXIT_ERROR;
        }

        $db = db_connect();
        $db->initialize();
        $mysqli = $db->connID;
        if (! $mysqli instanceof \mysqli) {
            CLI::error('demo:reset needs database.default.DBDriver = MySQLi');

            return EXIT_ERROR;
        }

        // Drop every table first so nothing created after the snapshot survives.
        $mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->listTables(true) as $table) {
            $mysqli->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
        }

        if (! $mysqli->multi_query((string) file_get_contents($file))) {
            CLI::error('Import failed: ' . $mysqli->error);

            return EXIT_ERROR;
        }
        do {
            if ($res = $mysqli->store_result()) {
                $res->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
        if ($mysqli->errno) {
            CLI::error('Import failed: ' . $mysqli->error);

            return EXIT_ERROR;
        }
        $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');

        // Uploaded files: remove visitors' uploads, restore the demo ones.
        $uploads = WRITEPATH . 'uploads/';
        $this->emptyDir($uploads);
        $this->copyDir(ROOTPATH . 'docs/database/demo_uploads/', $uploads);

        cache()->clean();

        CLI::write('Demo data restored. Login: demo@sygmasolar.com / Demo@12345', 'green');

        return EXIT_SUCCESS;
    }

    private function emptyDir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            if ($f->getFilename() === 'index.html' && $f->getPath() === rtrim($dir, '/')) {
                continue;
            }
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
    }

    private function copyDir(string $from, string $to): void
    {
        if (! is_dir($from)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($it as $f) {
            $target = $to . substr($f->getPathname(), strlen($from));
            if ($f->isDir()) {
                is_dir($target) || mkdir($target, 0775, true);
            } else {
                copy($f->getPathname(), $target);
            }
        }
    }
}
