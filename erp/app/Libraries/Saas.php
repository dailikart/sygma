<?php

namespace App\Libraries;

use CodeIgniter\Config\Factories;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\MigrationRunner;
use Config\Services;
use RuntimeException;

/**
 * Multi-company (SaaS) mode.
 *
 * One code base, one database. Every company's tables carry its own prefix
 * (t_<code>_leads, t_<code>_invoices, …). The company is chosen from the web
 * address (client1.yourdomain.com); the owner control panel lives on its own
 * address (saas.ownerHosts). Nothing app-related exists without a prefix, so a
 * query that forgot the prefix fails instead of reaching another company.
 *
 * .env:
 *   saas.enabled       = true
 *   saas.ownerHosts    = 'admin.yourdomain.com'   (comma separated)
 *   saas.baseDomain    = 'yourdomain.com'         (new companies get <code>.yourdomain.com)
 *   saas.ownerEmail    = 'you@example.com'        (first owner login, created on first sign-in)
 *   saas.ownerPassword = 'a-strong-password'      (remove after the first sign-in)
 *
 * Without saas.enabled the ERP runs as a single company, exactly as before.
 */
class Saas
{
    public const T_COMPANIES = 'saas_companies';
    public const T_OWNERS    = 'saas_owners';
    public const T_LOG       = 'saas_log';

    private static ?array $current = null;
    private static ?array $baseDb  = null;
    private static ?BaseConnection $db = null;
    private static bool $owner = false;

    public static function enabled(): bool
    {
        return filter_var(env('saas.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** The company this request / command works for, or null (owner panel, single-company mode). */
    public static function current(): ?array
    {
        return self::$current;
    }

    public static function isOwnerRequest(): bool
    {
        return self::$owner;
    }

    public static function ownerHosts(): array
    {
        return array_values(array_filter(array_map(static fn ($h) => strtolower(trim($h)), explode(',', (string) env('saas.ownerHosts', '')))));
    }

    /** Request host without port, lower case ('' when unusable). */
    public static function host(): string
    {
        $h = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $h = preg_replace('/:\d+$/', '', $h);

        return preg_match('/^[a-z0-9.-]{1,190}$/', $h) ? $h : '';
    }

    /**
     * Base URL for this request's host (Config\App calls this): same scheme and path as
     * the configured app.baseURL, host taken from the request so each company gets its own.
     */
    public static function baseUrlFor(string $configured): string
    {
        $raw = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (! preg_match('/^[a-z0-9.-]{1,190}(:\d{1,5})?$/', $raw)) {
            return $configured;
        }
        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || str_starts_with($configured, 'https://');
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir    = rtrim(dirname($script), '/');
        if (str_ends_with($dir, '/public') && ! str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/public/')) {
            $dir = substr($dir, 0, -7); // erp/.htaccess sends requests into public/
        }

        return ($https ? 'https' : 'http') . '://' . $raw . $dir . '/';
    }

    /** Runs before routing (pre_system): pick the company for this web request. */
    public static function boot(): void
    {
        if (! self::enabled() || is_cli()) {
            return;
        }
        $host = self::host();
        if (in_array($host, self::ownerHosts(), true)) {
            self::$owner = true;
            self::activate(null);
            // Development toolbar: the login panel reads company tables, which the owner address has not.
            $tb             = config('Toolbar');
            $tb->collectors = array_values(array_filter($tb->collectors, static fn ($c) => ! str_contains((string) $c, 'Shield')));

            return;
        }
        try {
            $company = $host === '' ? null : self::findByHost($host);
        } catch (\Throwable) {
            $company = null;
        }
        if (! $company || $company['status'] === 'deleted') {
            http_response_code(404);
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not found</title>'
                . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:0 16px;text-align:center"><h1 style="font-weight:600">Company not found</h1>'
                . '<p style="color:#555">There is no company at <b>' . htmlspecialchars($host) . '</b>. Check the address you were given, or contact your software provider.</p></div>';
            exit;
        }
        self::activate($company);
        $s             = config('Session');
        $s->cookieName = 'sy_' . $company['code'];
    }

    /** Command-line option: accepts both --key value and --key=value. */
    public static function opt(string $key): ?string
    {
        foreach (\CodeIgniter\CLI\CLI::getOptions() as $k => $v) {
            if ($k === $key) {
                return $v === null ? '' : (string) $v;
            }
            if (str_starts_with((string) $k, $key . '=')) {
                return substr((string) $k, strlen($key) + 1) . ($v !== null && $v !== '' ? ' ' . $v : '');
            }
        }

        return null;
    }

    /** Multi-company: company commands must say which company (php spark saas:run "..."). */
    public static function guardCommand(): void
    {
        if (! self::enabled() || self::$current !== null) {
            return;
        }
        $cmd = (string) (\CodeIgniter\CLI\CLI::getSegment(1) ?? '');
        if (preg_match('/^(migrate|db:|amc:|indiamart:|queue:|demo:|features)/', $cmd)) {
            \CodeIgniter\CLI\CLI::error("Multi-company mode: run company commands through saas:run, e.g.\n  php spark saas:run \"{$cmd}\"            (every active company)\n  php spark saas:run \"{$cmd}\" --company=code");
            exit(1);
        }
    }

    /**
     * Switch everything that is per company: database prefix, cached connections, services,
     * cache prefix and static caches. null = back to the owner / base database.
     */
    public static function activate(?array $company): void
    {
        $cfg = config('Database');
        self::$baseDb ??= $cfg->default;
        $cfg->default = self::$baseDb;
        if ($company) {
            if (! preg_match('/^t_[a-z][a-z0-9]{1,11}_$/', (string) $company['db_prefix'])) {
                throw new RuntimeException('Bad company table prefix.');
            }
            $cfg->default['DBPrefix'] = $company['db_prefix'];
            if (($cfg->default['DBDriver'] ?? 'MySQLi') === 'MySQLi') {
                $cfg->default['DBDriver'] = 'App\Database\MySQLi'; // keeps table aliases (see App\Database\MySQLi\Connection)
            }
        } else {
            $cfg->default['DBPrefix'] = '';
        }
        \Config\Database::resetConnections();
        self::$current = $company;

        config('Cache')->prefix = $company ? 'c' . $company['code'] . '_' : '';
        foreach (['settings', 'auth', 'authentication', 'authorization', 'migrations', 'email', 'cache', 'responsecache'] as $svc) {
            Services::resetSingle($svc);
        }
        Factories::reset('models');
        Features::refresh();
        Lots::reset();
    }

    /** Connection to the base database (no prefix): owner tables saas_*. */
    public static function db(): BaseConnection
    {
        if (self::$db === null) {
            $cfg = self::$baseDb ?? config('Database')->default;
            $cfg['DBPrefix'] = '';
            self::$db = \Config\Database::connect($cfg, false);
        }

        return self::$db;
    }

    public static function findByHost(string $host): ?array
    {
        return self::db()->table(self::T_COMPANIES)->groupStart()->where('domain', $host)->orWhere('alt_domain', $host)->groupEnd()
            ->get()->getRowArray() ?: null;
    }

    public static function find(int $id): ?array
    {
        return self::db()->table(self::T_COMPANIES)->where('id', $id)->get()->getRowArray() ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        return self::db()->table(self::T_COMPANIES)->where('code', strtolower($code))->get()->getRowArray() ?: null;
    }

    /** @return list<array> */
    public static function companies(): array
    {
        return self::db()->table(self::T_COMPANIES)->where('status <>', 'deleted')->orderBy('name')->get()->getResultArray();
    }

    /** Creates the owner tables if they are not there yet. */
    public static function install(): void
    {
        $db    = self::db();
        $forge = \Config\Database::forge($db);
        if (! $db->tableExists(self::T_COMPANIES, false)) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'code'         => ['type' => 'VARCHAR', 'constraint' => 12],
                'name'         => ['type' => 'VARCHAR', 'constraint' => 150],
                'domain'       => ['type' => 'VARCHAR', 'constraint' => 190],
                'alt_domain'   => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'db_prefix'    => ['type' => 'VARCHAR', 'constraint' => 20],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'active'], // active | trial | suspended | setup
                'plan'         => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'max_users'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'expires_on'   => ['type' => 'DATE', 'null' => true],
                'suspend_note' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'contact_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'phone'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'email'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'gstin'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'state'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'notes'        => ['type' => 'TEXT', 'null' => true],
                'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addPrimaryKey('id');
            $forge->addUniqueKey('code');
            $forge->addUniqueKey('domain');
            $forge->addKey('alt_domain');
            $forge->createTable(self::T_COMPANIES, true);
        }
        if (! $db->tableExists(self::T_OWNERS, false)) {
            $forge->addField([
                'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name'          => ['type' => 'VARCHAR', 'constraint' => 100],
                'email'         => ['type' => 'VARCHAR', 'constraint' => 150],
                'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
                'last_login_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addPrimaryKey('id');
            $forge->addUniqueKey('email');
            $forge->createTable(self::T_OWNERS, true);
        }
        if (! $db->tableExists(self::T_LOG, false)) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'owner_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'action'     => ['type' => 'VARCHAR', 'constraint' => 40],
                'detail'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addPrimaryKey('id');
            $forge->addKey(['action', 'created_at']);
            $forge->addKey('company_id');
            $forge->createTable(self::T_LOG, true);
        }
    }

    public static function log(string $action, ?int $companyId = null, string $detail = '', ?int $ownerId = null): void
    {
        self::db()->table(self::T_LOG)->insert([
            'owner_id' => $ownerId ?? (session()->get('owner_id') ?: null), 'company_id' => $companyId, 'action' => $action,
            'detail' => mb_substr($detail, 0, 500), 'ip' => is_cli() ? 'cli' : service('request')->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function validCode(string $code): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9]{1,11}$/', $code) && ! in_array($code, ['www', 'admin', 'owner', 'mail', 'api', 'demo', 'saas'], true);
    }

    /**
     * Creates a company: owner row, all tables (migrations), Super Admin login, company settings and modules.
     *
     * @param array $d code, name, domain, alt_domain?, plan?, max_users?, expires_on?, status?, contact_name?, phone?, email?, gstin?, state?, notes?,
     *                 admin_email, admin_password, off (list of switched-off module keys)
     */
    public static function createCompany(array $d): array
    {
        self::install();
        $code   = strtolower(trim((string) $d['code']));
        $domain = strtolower(trim((string) $d['domain']));
        if (! self::validCode($code)) {
            throw new RuntimeException('Company code: 2–12 lower-case letters or digits, starting with a letter (not www, admin, owner, mail, api, demo, saas).');
        }
        if (! preg_match('/^[a-z0-9.-]{3,190}$/', $domain)) {
            throw new RuntimeException('Enter the web address without https://, e.g. ' . $code . '.' . (env('saas.baseDomain') ?: 'yourdomain.com'));
        }
        if (self::findByCode($code)) {
            throw new RuntimeException("Company code {$code} is already used.");
        }
        if (self::findByHost($domain) || in_array($domain, self::ownerHosts(), true)) {
            throw new RuntimeException("The address {$domain} is already used.");
        }
        if (! filter_var($d['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter the email of the company\'s first Super Admin.');
        }
        if (strlen((string) ($d['admin_password'] ?? '')) < 8) {
            throw new RuntimeException('The Super Admin password needs at least 8 characters.');
        }
        $prefix = 't_' . $code . '_';
        if (self::db()->query('SHOW TABLES LIKE ' . self::db()->escape(str_replace('_', '\_', $prefix) . '%'))->getResultArray()) {
            throw new RuntimeException("Tables starting with {$prefix} already exist in the database. Choose another code.");
        }
        $now = date('Y-m-d H:i:s');
        self::db()->table(self::T_COMPANIES)->insert([
            'code' => $code, 'name' => trim((string) $d['name']), 'domain' => $domain, 'alt_domain' => self::cleanHost($d['alt_domain'] ?? ''),
            'db_prefix' => $prefix, 'status' => 'setup', 'plan' => ($d['plan'] ?? '') ?: null,
            'max_users' => ($d['max_users'] ?? '') !== '' ? (int) $d['max_users'] : null, 'expires_on' => ($d['expires_on'] ?? '') ?: null,
            'contact_name' => ($d['contact_name'] ?? '') ?: null, 'phone' => ($d['phone'] ?? '') ?: null, 'email' => ($d['email'] ?? '') ?: null,
            'gstin' => strtoupper(trim((string) ($d['gstin'] ?? ''))) ?: null, 'state' => ($d['state'] ?? '') ?: null, 'notes' => ($d['notes'] ?? '') ?: null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $company = self::find((int) self::db()->insertID());

        self::within($company, static function () use ($d, $company): void {
            self::runMigrations();
            self::setAdmin((string) $d['admin_email'], (string) $d['admin_password']);
            $settings = ['companyName' => $company['name'], 'companyEmail' => $company['email'], 'companyPhone' => $company['phone'],
                'companyGstin' => $company['gstin'], 'companyState' => $company['state'] ?: 'Tamil Nadu'];
            foreach ($settings as $k => $v) {
                if ($v !== null && $v !== '') {
                    setting('Solar.' . $k, $v);
                }
            }
            Features::save(array_values((array) ($d['off'] ?? [])));
        });
        $status = in_array($d['status'] ?? '', ['active', 'trial'], true) ? $d['status'] : 'active';
        self::db()->table(self::T_COMPANIES)->where('id', $company['id'])->update(['status' => $status]);
        self::log('company_created', (int) $company['id'], "{$company['name']} ({$code}) at {$domain}");

        return self::find((int) $company['id']);
    }

    /** Runs $fn with the company active, then switches back (owner panel / commands). */
    public static function within(array $company, callable $fn): mixed
    {
        $before = self::$current;
        self::activate($company);
        try {
            return $fn();
        } finally {
            self::activate($before);
        }
    }

    /** Brings the active company's tables up to date. Returns the number of updates applied. */
    public static function runMigrations(): int
    {
        $before = self::pendingMigrations();
        $runner = new MigrationRunner(config('Migrations'), db_connect());
        $runner->setNamespace(null);
        if (! $runner->latest()) {
            throw new RuntimeException('Database update failed. Check writable/logs.');
        }

        return $before;
    }

    /** Number of database updates the active company has not run yet. */
    public static function pendingMigrations(): int
    {
        $db     = db_connect();
        $runner = new MigrationRunner(config('Migrations'), $db);
        $runner->setNamespace(null);
        $all  = array_map(static fn ($m) => $m->version . '|' . $m->class, $runner->findMigrations());
        $done = $db->tableExists('migrations')
            ? array_map(static fn ($r) => $r['version'] . '|' . $r['class'], $db->table('migrations')->select('version, class')->get()->getResultArray())
            : [];

        return count(array_diff($all, $done));
    }

    /**
     * Active company: give the first Super Admin this email and password (creates the login if there is none).
     */
    public static function setAdmin(string $email, string $password): int
    {
        $users = auth()->getProvider();
        $db    = db_connect();
        $row   = $db->table('auth_groups_users')->select('user_id')->where('group', 'superadmin')->orderBy('user_id')->get()->getRowArray();
        if ($row && ($user = $users->findById((int) $row['user_id']))) {
            $user->fill(['email' => $email, 'password' => $password]);
            $users->save($user);
            $user->activate();

            return (int) $user->id;
        }
        $user = new \CodeIgniter\Shield\Entities\User(['username' => 'admin', 'email' => $email, 'password' => $password]);
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        $user->activate();

        return (int) $user->id;
    }

    /** Active company: id of the first active Super Admin (for support login). */
    public static function firstAdminId(): ?int
    {
        $row = db_connect()->table('auth_groups_users g')->select('g.user_id')->join('users u', 'u.id = g.user_id')
            ->where(['g.group' => 'superadmin', 'u.active' => 1])->where('u.deleted_at', null)->orderBy('g.user_id')->get()->getRowArray();

        return $row ? (int) $row['user_id'] : null;
    }

    /** Plan limit: message when the active company may not have one more active user, else null. */
    public static function userLimitMessage(): ?string
    {
        $max = self::$current['max_users'] ?? null;
        if ($max === null || $max === '') {
            return null;
        }
        $n = db_connect()->table('users')->where(['active' => 1, 'deleted_at' => null])
            ->groupStart()->where('status', null)->orWhere('status <>', 'banned')->groupEnd()->countAllResults();

        return $n >= (int) $max ? "Your plan allows {$max} active users and you have {$n}. Deactivate a user or contact Sygma Solar Suite to add more." : null;
    }

    /** Usage numbers for the owner panel (active company). */
    public static function usage(): array
    {
        $db  = db_connect();
        $out = [];
        foreach (['users' => ['users', 'deleted_at'], 'leads' => ['leads', 'deleted_at'], 'customers' => ['customers', 'deleted_at'],
            'quotations' => ['quotations', null], 'invoices' => ['invoices', null], 'projects' => ['projects', null]] as $k => [$t, $del]) {
            try {
                $b = $db->table($t);
                if ($del) {
                    $b->where($del, null);
                }
                if ($k === 'users') {
                    $b->where('active', 1);
                }
                $out[$k] = $b->countAllResults();
            } catch (\Throwable) {
                $out[$k] = null;
            }
        }
        try {
            $out['sales'] = (float) ($db->table('invoices')->selectSum('total')->where('status <>', 'cancelled')->get()->getRow()->total ?? 0);
        } catch (\Throwable) {
            $out['sales'] = null;
        }

        return $out;
    }

    /** Deletes a company: all its tables and uploaded files. Cannot be undone. */
    public static function dropCompany(array $company): void
    {
        $db     = self::db();
        $prefix = $company['db_prefix'];
        if (! preg_match('/^t_[a-z][a-z0-9]{1,11}_$/', $prefix)) {
            throw new RuntimeException('Bad prefix.');
        }
        $tables = array_map(static fn ($r) => array_values($r)[0], $db->query('SHOW TABLES LIKE ' . $db->escape(str_replace('_', '\_', $prefix) . '%'))->getResultArray());
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) {
            if (str_starts_with($t, $prefix)) {
                $db->query('DROP TABLE IF EXISTS `' . str_replace('`', '', $t) . '`');
            }
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        $dir = upload_path() . 'tenants/' . $company['code'];
        if (is_dir($dir)) {
            self::rmdir($dir);
        }
        $db->table(self::T_COMPANIES)->where('id', $company['id'])->delete();
        self::log('company_deleted', (int) $company['id'], "{$company['name']} ({$company['code']}): " . count($tables) . ' tables dropped');
    }

    // ---- support login (owner → company Super Admin) -----------------------

    public static function supportToken(array $company, int $ownerId): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['c' => (int) $company['id'], 'o' => $ownerId, 'e' => time() + 120, 'n' => bin2hex(random_bytes(12))])), '+/', '-_'), '=');

        return $payload . '.' . hash_hmac('sha256', $payload, self::secret());
    }

    /** @return array{c:int,o:int,e:int,n:string}|null */
    public static function readSupportToken(string $token): ?array
    {
        [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if ($payload === '' || ! hash_equals(hash_hmac('sha256', $payload, self::secret()), $sig)) {
            return null;
        }
        $d = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        if (! is_array($d) || ($d['e'] ?? 0) < time()) {
            return null;
        }
        if (self::db()->table(self::T_LOG)->where(['action' => 'support_login', 'detail' => 'nonce ' . $d['n']])->countAllResults()) {
            return null; // already used
        }

        return $d;
    }

    private static function secret(): string
    {
        $k = (string) (env('saas.secret') ?: env('encryption.key') ?: config('Encryption')->key);
        if (strlen($k) < 16) {
            throw new RuntimeException('Set encryption.key in .env.');
        }

        return 'sygma-saas|' . $k;
    }

    public static function cleanHost(?string $h): ?string
    {
        $h = strtolower(trim((string) $h));
        $h = preg_replace('#^https?://#', '', $h);
        $h = rtrim(explode('/', $h)[0], '.');

        return $h !== '' && preg_match('/^[a-z0-9.-]{3,190}$/', $h) ? $h : null;
    }

    private static function rmdir(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
