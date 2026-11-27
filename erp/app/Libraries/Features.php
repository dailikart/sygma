<?php

namespace App\Libraries;

/**
 * Per-company modules and options (see app/Config/Features.php).
 *
 *   Features::on('amc')               module on?
 *   Features::on('invoices.loan')     option on? (also false when its module is off)
 *   Features::permissionAllowed('quotations.view')
 *   Features::blockedBy('admin/quotations/5')   → title of the module/option that blocks it, or null
 */
class Features
{
    private static ?array $off = null;

    public static function config(): \Config\Features
    {
        return config('Features');
    }

    /** Keys switched off for this company, plus everything that depends on them. */
    public static function offKeys(): array
    {
        if (self::$off !== null) {
            return self::$off;
        }
        try {
            $saved = (array) setting('Features.off');
        } catch (\Throwable) {
            $saved = []; // settings table not ready (fresh install)
        }

        return self::$off = self::withDependants($saved);
    }

    /** Forget the cached state (after saving). */
    public static function refresh(): void
    {
        self::$off = null;
    }

    public static function on(string $key): bool
    {
        $off = self::offKeys();
        if (in_array($key, $off, true)) {
            return false;
        }
        if (str_contains($key, '.')) {
            return ! in_array(explode('.', $key)[0], $off, true);
        }

        return true;
    }

    /** All switchable keys: ['quotations' => def, 'invoices.loan' => def, ...] */
    public static function all(): array
    {
        $out = [];
        foreach (self::config()->catalogue as $mod => $def) {
            $out[$mod] = $def;
            foreach ($def['options'] ?? [] as $opt => $odef) {
                $out[$mod . '.' . $opt] = $odef + ['module' => $mod];
            }
        }

        return $out;
    }

    public static function title(string $key): string
    {
        return self::all()[$key]['title'] ?? $key;
    }

    /** Adds every module/option that requires something already off (and options of off modules). */
    public static function withDependants(array $off): array
    {
        $all = self::all();
        $off = array_values(array_intersect(array_unique($off), array_keys($all)));
        // Core modules can never be off (their options can).
        $off = array_values(array_filter($off, static fn ($k) => str_contains($k, '.') || empty($all[$k]['core'])));
        do {
            $changed = false;
            foreach ($all as $key => $def) {
                if (in_array($key, $off, true)) {
                    continue;
                }
                $parentOff = isset($def['module']) && in_array($def['module'], $off, true);
                $needsOff  = array_intersect($def['requires'] ?? [], $off) !== [];
                if ($parentOff || $needsOff) {
                    $off[]   = $key;
                    $changed = true;
                }
            }
        } while ($changed);

        return $off;
    }

    /** False when the permission belongs to a module/option that is off. */
    public static function permissionAllowed(string $permission): bool
    {
        $prefix = explode('.', $permission)[0];
        foreach (self::offKeys() as $key) {
            if (in_array($prefix, self::all()[$key]['permissions'] ?? [], true)) {
                return false;
            }
        }

        return true;
    }

    /** Title of the module/option that blocks this URL path, or null when it is allowed. */
    public static function blockedBy(string $path): ?string
    {
        $path = trim($path, '/');
        foreach (self::offKeys() as $key) {
            foreach (self::all()[$key]['routes'] ?? [] as $pattern) {
                if (self::matches($pattern, $path)) {
                    return self::title($key);
                }
            }
        }

        return null;
    }

    /** Settings-screen sections to hide. */
    public static function hiddenSettings(): array
    {
        $out = [];
        foreach (self::offKeys() as $key) {
            array_push($out, ...(self::all()[$key]['settings'] ?? []));
        }

        return $out;
    }

    /** "admin/projects/*\/status-link" matches "admin/projects/5/status-link/whatsapp". */
    private static function matches(string $pattern, string $path): bool
    {
        $p = explode('/', trim($pattern, '/'));
        $s = explode('/', $path);
        if (count($s) < count($p)) {
            return false;
        }
        foreach ($p as $i => $seg) {
            if ($seg !== '*' && $seg !== $s[$i]) {
                return false;
            }
        }

        return true;
    }

    /** Only you (the software owner) may change modules: needs features.manage = true in .env. */
    public static function manageable(): bool
    {
        if (Saas::enabled()) {
            return false; // multi-company: modules are set in the owner control panel
        }

        return filter_var(env('features.manage', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Saves only what was switched off on purpose. Modules that depend on it are
     * worked out when reading, so switching e.g. Inventory back on also brings
     * Projects back (unless Projects itself was switched off).
     */
    public static function save(array $off): void
    {
        $all = self::all();
        $off = array_filter(array_unique($off), static fn ($k) => isset($all[$k]) && (str_contains($k, '.') || empty($all[$k]['core'])));
        setting('Features.off', array_values($off));
        self::refresh();
    }

    /** Why a key is off although it was not switched off itself (or null). */
    public static function offBecause(string $key): ?string
    {
        if (self::on($key) || in_array($key, (array) setting('Features.off'), true)) {
            return null;
        }
        $def  = self::all()[$key] ?? [];
        $need = array_merge(isset($def['module']) ? [$def['module']] : [], $def['requires'] ?? []);
        $off  = array_filter($need, static fn ($k) => ! self::on($k));

        return $off ? implode(', ', array_map(self::title(...), $off)) . ' is off' : null;
    }
}
