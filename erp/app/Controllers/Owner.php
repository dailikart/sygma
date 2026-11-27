<?php

namespace App\Controllers;

use App\Libraries\Features;
use App\Libraries\Saas;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Owner control panel (multi-company mode, owner address only):
 * companies, modules, plan / expiry / suspend, database updates, support login.
 * Owner logins are separate from company logins (table saas_owners).
 */
class Owner extends BaseController
{
    protected $helpers = ['form', 'url', 'app', 'setting'];

    private ?array $me = null;

    // ---- login ---------------------------------------------------------------

    public function login()
    {
        $this->ready();
        if ($this->owner()) {
            return redirect()->to(site_url('owner'));
        }

        return view('owner/login', ['error' => session()->getFlashdata('error')]);
    }

    public function doLogin()
    {
        $this->ready();
        $db    = Saas::db();
        $ip    = $this->request->getIPAddress();
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $pass  = (string) $this->request->getPost('password');
        $fails = $db->table(Saas::T_LOG)->where(['action' => 'owner_login_failed', 'ip' => $ip])->where('created_at >', date('Y-m-d H:i:s', time() - 900))->countAllResults();
        if ($fails >= 5) {
            return redirect()->to(site_url('owner/login'))->with('error', 'Too many wrong attempts. Try again in 15 minutes.');
        }
        // First sign-in: the owner from .env (saas.ownerEmail / saas.ownerPassword) is created.
        if (! $db->table(Saas::T_OWNERS)->countAllResults()) {
            $envEmail = strtolower(trim((string) env('saas.ownerEmail', '')));
            $envPass  = (string) env('saas.ownerPassword', '');
            if ($envEmail !== '' && strlen($envPass) >= 10 && hash_equals($envEmail, $email) && hash_equals($envPass, $pass)) {
                $db->table(Saas::T_OWNERS)->insert(['name' => 'Owner', 'email' => $envEmail, 'password_hash' => password_hash($envPass, PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
        $row = $db->table(Saas::T_OWNERS)->where('email', $email)->get()->getRowArray();
        if (! $row || ! password_verify($pass, $row['password_hash'])) {
            Saas::log('owner_login_failed', null, $email, 0);

            return redirect()->to(site_url('owner/login'))->with('error', 'Wrong email or password.');
        }
        session()->regenerate(true);
        session()->set('owner_id', (int) $row['id']);
        $db->table(Saas::T_OWNERS)->where('id', $row['id'])->update(['last_login_at' => date('Y-m-d H:i:s')]);
        Saas::log('owner_login', null, $email, (int) $row['id']);

        return redirect()->to(site_url('owner'));
    }

    public function logout()
    {
        session()->remove('owner_id');
        session()->regenerate(true);

        return redirect()->to(site_url('owner/login'));
    }

    // ---- dashboard -----------------------------------------------------------

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $list   = Saas::companies();
        $today  = date('Y-m-d');
        $soon   = date('Y-m-d', strtotime('+15 days'));
        $counts = ['all' => count($list), 'active' => 0, 'trial' => 0, 'suspended' => 0, 'expired' => 0, 'expiring' => 0, 'setup' => 0];
        foreach ($list as &$c) {
            $c['state_label'] = self::stateOf($c);
            $counts[$c['state_label']] = ($counts[$c['state_label']] ?? 0) + 1;
            if ($c['expires_on'] && $c['expires_on'] >= $today && $c['expires_on'] <= $soon) {
                $counts['expiring']++;
            }
        }
        unset($c);
        $q = strtolower(trim((string) $this->request->getGet('q')));
        if ($q !== '') {
            $list = array_values(array_filter($list, static fn ($c) => str_contains(strtolower($c['name'] . ' ' . $c['code'] . ' ' . $c['domain'] . ' ' . $c['phone'] . ' ' . $c['email']), $q)));
        }
        $f = (string) $this->request->getGet('show');
        if ($f !== '' && $f !== 'all') {
            $list = array_values(array_filter($list, static fn ($c) => $f === 'expiring'
                ? ($c['expires_on'] && $c['expires_on'] >= $today && $c['expires_on'] <= $soon) : $c['state_label'] === $f));
        }

        return $this->page('owner/index', ['companies' => $list, 'counts' => $counts, 'q' => $q, 'show' => $f]);
    }

    // ---- companies -----------------------------------------------------------

    public function create()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        return $this->page('owner/form', ['c' => null, 'catalogue' => Features::config()->catalogue, 'off' => [], 'base' => (string) env('saas.baseDomain', '')]);
    }

    public function store()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $p = $this->request->getPost();
        @set_time_limit(300);
        try {
            $c = Saas::createCompany($p + ['off' => $this->offFromPost($p)]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('owner/companies/' . $c['id']))->with('message', "{$c['name']} is ready. Give the client the address https://{$c['domain']}/ and the Super Admin login ({$p['admin_email']}).");
    }

    public function show($id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $c = $this->company($id);
        $info = ['off' => [], 'usage' => [], 'pending' => null, 'admin' => null, 'error' => null];
        try {
            $info = Saas::within($c, static function (): array {
                $admin = null;
                if ($uid = Saas::firstAdminId()) {
                    $admin = db_connect()->table('auth_identities')->select('secret')->where(['user_id' => $uid, 'type' => 'email_password'])->get()->getRow()->secret ?? null;
                }

                return ['off' => (array) setting('Features.off'), 'usage' => Saas::usage(), 'pending' => Saas::pendingMigrations(), 'admin' => $admin, 'error' => null];
            });
        } catch (\Throwable $e) {
            $info['error'] = $e->getMessage();
        }
        $log = Saas::db()->table(Saas::T_LOG . ' l')->select('l.*, o.name AS owner')->join(Saas::T_OWNERS . ' o', 'o.id = l.owner_id', 'left')
            ->where('l.company_id', $c['id'])->orderBy('l.id', 'DESC')->limit(30)->get()->getResultArray();

        return $this->page('owner/form', ['c' => $c, 'catalogue' => Features::config()->catalogue, 'base' => (string) env('saas.baseDomain', ''), 'log' => $log] + $info);
    }

    public function update($id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $c   = $this->company($id);
        $p   = $this->request->getPost();
        $alt = Saas::cleanHost($p['alt_domain'] ?? '');
        $dom = Saas::cleanHost($p['domain'] ?? '') ?? $c['domain'];
        foreach (array_filter([$dom, $alt]) as $h) {
            $other = Saas::findByHost($h);
            if (($other && (int) $other['id'] !== (int) $c['id']) || in_array($h, Saas::ownerHosts(), true)) {
                return redirect()->back()->withInput()->with('error', "The address {$h} is used by another company.");
            }
        }
        $status = in_array($p['status'] ?? '', ['active', 'trial', 'suspended'], true) ? $p['status'] : $c['status'];
        $row    = [
            'name' => trim((string) ($p['name'] ?? '')) ?: $c['name'], 'domain' => $dom, 'alt_domain' => $alt, 'status' => $status,
            'plan' => ($p['plan'] ?? '') ?: null, 'max_users' => ($p['max_users'] ?? '') !== '' ? max(1, (int) $p['max_users']) : null,
            'expires_on' => ($p['expires_on'] ?? '') ?: null, 'suspend_note' => ($p['suspend_note'] ?? '') ?: null,
            'contact_name' => ($p['contact_name'] ?? '') ?: null, 'phone' => ($p['phone'] ?? '') ?: null, 'email' => ($p['email'] ?? '') ?: null,
            'gstin' => strtoupper(trim((string) ($p['gstin'] ?? ''))) ?: null, 'state' => ($p['state'] ?? '') ?: null, 'notes' => ($p['notes'] ?? '') ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        Saas::db()->table(Saas::T_COMPANIES)->where('id', $c['id'])->update($row);
        $off    = $this->offFromPost($p);
        $before = Saas::within($c, static function () use ($off): array {
            $old = (array) setting('Features.off');
            Features::save($off);

            return $old;
        });
        $changes = [];
        foreach (['status', 'plan', 'max_users', 'expires_on', 'domain', 'alt_domain'] as $k) {
            if ((string) $c[$k] !== (string) $row[$k]) {
                $changes[] = "{$k}: " . ($c[$k] ?: '–') . ' → ' . ($row[$k] ?: '–');
            }
        }
        $title = static fn ($k) => Features::title($k);
        if ($added = array_diff($off, $before)) {
            $changes[] = 'switched off: ' . implode(', ', array_map($title, $added));
        }
        if ($removed = array_diff($before, $off)) {
            $changes[] = 'switched on: ' . implode(', ', array_map($title, $removed));
        }
        Saas::log('company_updated', (int) $c['id'], $changes ? implode('; ', $changes) : 'details saved');

        return redirect()->to(site_url('owner/companies/' . $c['id']))->with('message', 'Saved.');
    }

    public function migrate($id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $c = $this->company($id);
        @set_time_limit(300);
        try {
            $n = Saas::within($c, static fn () => Saas::runMigrations());
        } catch (\Throwable $e) {
            return redirect()->to(site_url('owner/companies/' . $c['id']))->with('error', 'Update failed: ' . $e->getMessage());
        }
        if ($c['status'] === 'setup') {
            Saas::db()->table(Saas::T_COMPANIES)->where('id', $c['id'])->update(['status' => 'active']);
        }
        Saas::log('migrate', (int) $c['id'], "{$n} update(s)");

        return redirect()->to(site_url('owner/companies/' . $c['id']))->with('message', $n ? "{$n} database update(s) applied." : 'Database already up to date.');
    }

    public function migrateAll()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        @set_time_limit(0);
        $ok = $fail = $applied = 0;
        $errors = [];
        foreach (Saas::companies() as $c) {
            try {
                $n = Saas::within($c, static fn () => Saas::runMigrations());
                $applied += $n;
                $ok++;
                if ($n) {
                    Saas::log('migrate', (int) $c['id'], "{$n} update(s) (all companies)");
                }
            } catch (\Throwable $e) {
                $fail++;
                $errors[] = $c['code'] . ': ' . $e->getMessage();
            }
        }

        return redirect()->to(site_url('owner'))->with($fail ? 'error' : 'message', "Database updates: {$ok} companies up to date ({$applied} updates applied)" . ($fail ? "; {$fail} failed: " . implode(' | ', $errors) : '.'));
    }

    public function admin($id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $c     = $this->company($id);
        $email = strtolower(trim((string) $this->request->getPost('admin_email')));
        $pass  = (string) $this->request->getPost('admin_password');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            return redirect()->to(site_url('owner/companies/' . $c['id']))->with('error', 'Enter a valid email and a password of at least 8 characters.');
        }
        try {
            Saas::within($c, static fn () => Saas::setAdmin($email, $pass));
        } catch (\Throwable $e) {
            return redirect()->to(site_url('owner/companies/' . $c['id']))->with('error', $e->getMessage());
        }
        Saas::log('admin_reset', (int) $c['id'], "Super Admin login set to {$email}");

        return redirect()->to(site_url('owner/companies/' . $c['id']))->with('message', "Super Admin login is now {$email} with the new password.");
    }

    public function support($id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $c = $this->company($id);
        if ($c['status'] === 'suspended') {
            return redirect()->to(site_url('owner/companies/' . $c['id']))->with('error', 'The company is suspended. Make it active first.');
        }

        return redirect()->to($this->companyUrl($c, 'saas/support/' . Saas::supportToken($c, (int) $this->me['id'])));
    }

    public function delete($id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $c = $this->company($id);
        if (strtolower(trim((string) $this->request->getPost('confirm'))) !== $c['code']) {
            return redirect()->to(site_url('owner/companies/' . $c['id']))->with('error', 'Type the company code exactly to delete it.');
        }
        if ($c['status'] !== 'suspended') {
            return redirect()->to(site_url('owner/companies/' . $c['id']))->with('error', 'Suspend the company first, then delete it.');
        }
        Saas::dropCompany($c);

        return redirect()->to(site_url('owner'))->with('message', "{$c['name']} and all its data were deleted.");
    }

    public function log()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $rows = Saas::db()->table(Saas::T_LOG . ' l')->select('l.*, o.name AS owner, c.name AS company, c.code')
            ->join(Saas::T_OWNERS . ' o', 'o.id = l.owner_id', 'left')->join(Saas::T_COMPANIES . ' c', 'c.id = l.company_id', 'left')
            ->orderBy('l.id', 'DESC')->limit(300)->get()->getResultArray();

        return $this->page('owner/log', ['rows' => $rows]);
    }

    public function account()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $owners = Saas::db()->table(Saas::T_OWNERS)->select('id, name, email, last_login_at')->orderBy('id')->get()->getResultArray();

        return $this->page('owner/account', ['owners' => $owners]);
    }

    public function saveAccount()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $p  = $this->request->getPost();
        $db = Saas::db();
        if (($p['action'] ?? '') === 'add') {
            $email = strtolower(trim((string) ($p['email'] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) ($p['password'] ?? '')) < 10) {
                return redirect()->to(site_url('owner/account'))->with('error', 'Enter an email and a password of at least 10 characters.');
            }
            if ($db->table(Saas::T_OWNERS)->where('email', $email)->countAllResults()) {
                return redirect()->to(site_url('owner/account'))->with('error', 'That email is already an owner login.');
            }
            $db->table(Saas::T_OWNERS)->insert(['name' => trim((string) ($p['name'] ?? '')) ?: 'Owner', 'email' => $email, 'password_hash' => password_hash((string) $p['password'], PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s')]);
            Saas::log('owner_added', null, $email);

            return redirect()->to(site_url('owner/account'))->with('message', "Owner login {$email} added.");
        }
        if (! password_verify((string) ($p['current'] ?? ''), $this->me['password_hash'])) {
            return redirect()->to(site_url('owner/account'))->with('error', 'Your current password is wrong.');
        }
        if (strlen((string) ($p['new'] ?? '')) < 10 || ($p['new'] ?? '') !== ($p['confirm'] ?? '')) {
            return redirect()->to(site_url('owner/account'))->with('error', 'The new password needs at least 10 characters, typed the same twice.');
        }
        $db->table(Saas::T_OWNERS)->where('id', $this->me['id'])->update(['password_hash' => password_hash((string) $p['new'], PASSWORD_DEFAULT)]);
        Saas::log('owner_password', null, $this->me['email']);

        return redirect()->to(site_url('owner/account'))->with('message', 'Password changed.');
    }

    // ---- helpers -------------------------------------------------------------

    /** active | trial | suspended | expired | setup */
    public static function stateOf(array $c): string
    {
        if (in_array($c['status'], ['suspended', 'setup'], true)) {
            return $c['status'];
        }
        if ($c['expires_on'] && $c['expires_on'] < date('Y-m-d')) {
            return 'expired';
        }

        return $c['status'] === 'trial' ? 'trial' : 'active';
    }

    /** Address of a page in the company's ERP (same scheme, port and path as this panel). */
    private function companyUrl(array $c, string $path): string
    {
        $uri  = $this->request->getUri();
        $port = $uri->getPort();
        $base = rtrim((string) parse_url(base_url(), PHP_URL_PATH), '/');

        return $uri->getScheme() . '://' . $c['domain'] . ($port && ! in_array($port, [80, 443], true) ? ':' . $port : '') . $base . '/' . ltrim($path, '/');
    }

    private function offFromPost(array $p): array
    {
        $on  = array_keys((array) ($p['on'] ?? []));
        $all = Features::all();
        // core modules are always on (their switch is not sent); their options can be off
        $can = array_filter(array_keys($all), static fn ($k) => str_contains($k, '.') || empty($all[$k]['core']));

        return array_values(array_diff($can, $on));
    }

    private function company($id): array
    {
        $c = Saas::find((int) $id);
        if (! $c) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $c;
    }

    private function ready(): void
    {
        if (! Saas::enabled() || ! Saas::isOwnerRequest()) {
            throw PageNotFoundException::forPageNotFound();
        }
        Saas::install();
    }

    private function owner(): ?array
    {
        if ($this->me === null && ($id = (int) session()->get('owner_id'))) {
            $this->me = Saas::db()->table(Saas::T_OWNERS)->where('id', $id)->get()->getRowArray() ?: null;
        }

        return $this->me;
    }

    private function guard()
    {
        $this->ready();
        if (! $this->owner()) {
            return redirect()->to(site_url('owner/login'));
        }

        return null;
    }

    private function page(string $view, array $data)
    {
        return view($view, $data + ['me' => $this->me]);
    }
}
