<?php

namespace App\Controllers;

/**
 * "Install on phone" (Progressive Web App): manifest and home-screen icons made from
 * the company name, theme and logo in Settings.
 */
class AppManifest extends BaseController
{
    private const THEMES = ['' => '#1f2937', 'ocean' => '#0c4a6e', 'forest' => '#14532d', 'sunset' => '#ea580c', 'purple' => '#7c3aed', 'crimson' => '#e11d48', 'teal' => '#0d9488', 'solar' => '#d97706'];

    public function manifest()
    {
        helper('app');
        $name  = trim((string) setting('Solar.companyName')) ?: 'Sygma Solar Suite';
        $color = self::THEMES[(string) setting('Solar.theme')] ?? '#1f2937';
        $v     = substr(md5((string) setting('Solar.logoPath') . $color), 0, 8);

        return $this->response->setHeader('Content-Type', 'application/manifest+json; charset=utf-8')->setHeader('Cache-Control', 'no-cache')
            ->setBody(json_encode([
                'name'             => $name,
                'short_name'       => mb_strlen($name) > 12 ? mb_substr(preg_replace('/\s+(pvt|private|ltd|limited|solutions?|tech|technologies)\.?\b.*$/i', '', $name), 0, 12) : $name,
                'description'      => 'Sygma Solar Suite – leads, projects, stock and accounts',
                'start_url'        => site_url('admin') . '?source=app',
                'scope'            => rtrim(site_url('/'), '/') . '/',
                'display'          => 'standalone',
                'orientation'      => 'portrait-primary',
                'background_color' => '#ffffff',
                'theme_color'      => $color,
                'icons'            => [
                    ['src' => site_url('app-icon/192.png') . '?v=' . $v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => site_url('app-icon/512.png') . '?v=' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => site_url('app-icon/512-maskable.png') . '?v=' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ],
                'shortcuts'        => [
                    ['name' => 'My Tasks', 'url' => site_url('admin/tasks')],
                    ['name' => 'New Lead', 'url' => site_url('admin/leads/new')],
                    ['name' => 'Complaints', 'url' => site_url('admin/service')],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** 192.png, 512.png, 512-maskable.png (logo on white, or a sun on the theme colour). */
    public function icon(string $file)
    {
        helper('app');
        if (! preg_match('/^(192|512)(-maskable)?\.png$/', $file, $m)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $size     = (int) $m[1];
        $maskable = ! empty($m[2]);
        $logo     = (string) setting('Solar.logoPath');
        $color    = self::THEMES[(string) setting('Solar.theme')] ?? '#1f2937';
        $cache    = WRITEPATH . 'cache/app-icon-' . md5($logo . $color . $file) . '.png';
        if (! is_file($cache)) {
            file_put_contents($cache, $this->draw($size, $maskable, $logo !== '' ? WRITEPATH . 'uploads/' . $logo : '', $color));
        }

        return $this->response->setHeader('Content-Type', 'image/png')->setHeader('Cache-Control', 'public, max-age=604800')->setBody(file_get_contents($cache));
    }

    private function draw(int $size, bool $maskable, string $logo, string $hex): string
    {
        $im = imagecreatetruecolor($size, $size);
        imagealphablending($im, true);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $src = null;
        if ($logo !== '' && is_file($logo)) {
            $data = @file_get_contents($logo);
            $src  = $data ? @imagecreatefromstring($data) : null;
        }
        if ($src) {
            imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
            $pad  = (int) round($size * ($maskable ? 0.2 : 0.08)); // maskable icons need a safe zone
            $box  = $size - 2 * $pad;
            $w    = imagesx($src);
            $h    = imagesy($src);
            $k    = min($box / $w, $box / $h);
            $nw   = (int) round($w * $k);
            $nh   = (int) round($h * $k);
            imagecopyresampled($im, $src, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
        } else {
            // Sun on the theme colour.
            imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
            $sun = imagecolorallocate($im, 251, 191, 36);
            $c   = $size / 2;
            $rad = $size * ($maskable ? 0.16 : 0.2);
            imagesetthickness($im, max(2, (int) ($size / 40)));
            for ($i = 0; $i < 12; $i++) {
                $a = $i * M_PI / 6;
                imageline($im, (int) ($c + cos($a) * $rad * 1.45), (int) ($c + sin($a) * $rad * 1.45), (int) ($c + cos($a) * $rad * 2.0), (int) ($c + sin($a) * $rad * 2.0), $sun);
            }
            imagefilledellipse($im, (int) $c, (int) $c, (int) ($rad * 2), (int) ($rad * 2), $sun);
        }
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }
}
