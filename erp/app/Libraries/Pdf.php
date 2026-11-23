<?php

namespace App\Libraries;

use Mpdf\Mpdf;

/**
 * Renders a view to PDF with mPDF and streams it to the browser.
 */
class Pdf
{
    /** Renders a view to a PDF file in writable/cache/mail and returns its path. */
    public static function toFile(string $view, array $data, string $filename, string $format = 'A4'): string
    {
        $tmp = WRITEPATH . 'cache/mpdf';
        $dir = WRITEPATH . 'cache/mail';
        foreach ([$tmp, $dir] as $d) {
            if (! is_dir($d)) {
                mkdir($d, 0775, true);
            }
        }
        $path = $dir . '/' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $filename) . '-' . bin2hex(random_bytes(4)) . '.pdf';
        $mpdf = new Mpdf(['tempDir' => $tmp, 'format' => $format, 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 12, 'margin_right' => 12]);
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML(view($view, $data));
        $mpdf->Output($path, 'F');

        return $path;
    }

    public static function stream(string $view, array $data, string $filename, bool $download = false, string $format = 'A4'): void
    {
        $tmp = WRITEPATH . 'cache/mpdf';
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf(['tempDir' => $tmp, 'format' => $format, 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 12, 'margin_right' => 12]);
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML(view($view, $data));
        $mpdf->Output($filename . '.pdf', $download ? 'D' : 'I');
        exit;
    }
}
