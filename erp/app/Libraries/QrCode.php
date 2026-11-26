<?php

namespace App\Libraries;

use InvalidArgumentException;

/**
 * Small QR code generator (byte mode, error correction level M, versions 1–40),
 * so the GST e-invoice QR prints on the invoice PDF without an extra package.
 * Follows ISO/IEC 18004 (structure after Project Nayuki's reference implementation, MIT).
 */
class QrCode
{
    private const ECC_PER_BLOCK = [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28];
    private const NUM_BLOCKS    = [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49];
    private const ECL_BITS      = 0; // M

    private int $version;
    private int $size;
    /** @var list<list<bool>> */
    private array $mod = [];
    /** @var list<list<bool>> */
    private array $fn = [];

    /** SVG image of the QR code for $text (black squares, white quiet zone of 4 modules). */
    public static function svg(string $text, int $px = 4): string
    {
        $q = new self($text);
        $n = $q->size + 8;
        $path = '';
        for ($y = 0; $y < $q->size; $y++) {
            for ($x = 0; $x < $q->size; $x++) {
                if ($q->mod[$y][$x]) {
                    $path .= 'M' . ($x + 4) . ',' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . ($n * $px) . '" height="' . ($n * $px) . '" viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges">'
            . '<rect width="' . $n . '" height="' . $n . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }

    /** As a data URI for <img src="..."> (works in mPDF and browsers). */
    public static function dataUri(string $text, int $px = 4): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $px));
    }

    /** @return list<list<bool>> the module matrix (true = dark) */
    public static function matrix(string $text): array
    {
        return (new self($text))->mod;
    }

    public function __construct(string $text)
    {
        $data = array_values(unpack('C*', $text) ?: []);
        $len  = count($data);
        for ($v = 1; ; $v++) {
            if ($v > 40) {
                throw new InvalidArgumentException('Text too long for a QR code.');
            }
            $capBits = self::numDataCodewords($v) * 8;
            $used    = 4 + ($v < 10 ? 8 : 16) + $len * 8;
            if ($used <= $capBits) {
                break;
            }
        }
        $this->version = $v;
        $this->size    = $v * 4 + 17;

        // data bits: byte mode
        $bits = [];
        $put  = static function (int $val, int $n) use (&$bits): void {
            for ($i = $n - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };
        $put(4, 4);
        $put($len, $v < 10 ? 8 : 16);
        foreach ($data as $b) {
            $put($b, 8);
        }
        $cap = self::numDataCodewords($v) * 8;
        $put(0, min(4, $cap - count($bits)));
        $put(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $cap; $pad ^= 0xEC ^ 0x11) {
            $put($pad, 8);
        }
        $codewords = [];
        foreach (array_chunk($bits, 8) as $byte) {
            $codewords[] = array_reduce($byte, static fn ($a, $b) => ($a << 1) | $b, 0);
        }

        $row = array_fill(0, $this->size, false);
        $this->mod = array_fill(0, $this->size, $row);
        $this->fn  = array_fill(0, $this->size, $row);
        $this->drawFunctionPatterns();
        $this->drawCodewords($this->addEccAndInterleave($codewords));

        // choose the best mask
        $best = 0;
        $min  = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->applyMask($m);
            $this->drawFormatBits($m);
            $p = $this->penalty();
            if ($p < $min) {
                $min  = $p;
                $best = $m;
            }
            $this->applyMask($m); // undo (XOR)
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);
    }

    // ------------------------------------------------------------------

    private function drawFunctionPatterns(): void
    {
        $s = $this->size;
        for ($i = 0; $i < $s; $i++) {
            $this->setFn(6, $i, $i % 2 === 0);
            $this->setFn($i, 6, $i % 2 === 0);
        }
        $this->finder(3, 3);
        $this->finder($s - 4, 3);
        $this->finder(3, $s - 4);
        $pos = $this->alignmentPositions();
        $n   = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setFn($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0); // reserve
        if ($this->version >= 7) {
            $rem = $this->version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = $this->version << 12 | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) !== 0;
                $a   = $s - 11 + $i % 3;
                $b   = intdiv($i, 3);
                $this->setFn($a, $b, $bit);
                $this->setFn($b, $a, $bit);
            }
        }
    }

    private function finder(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $d = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFn($xx, $yy, $d !== 2 && $d !== 4);
                }
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = self::ECL_BITS << 3 | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = ($data << 10 | $rem) ^ 0x5412;
        $s    = $this->size;
        for ($i = 0; $i <= 5; $i++) {
            $this->setFn(8, $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFn(8, 7, (($bits >> 6) & 1) !== 0);
        $this->setFn(8, 8, (($bits >> 7) & 1) !== 0);
        $this->setFn(7, 8, (($bits >> 8) & 1) !== 0);
        for ($i = 9; $i < 15; $i++) {
            $this->setFn(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFn($s - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFn(8, $s - 15 + $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFn(8, $s - 8, true); // dark module
    }

    private function setFn(int $x, int $y, bool $dark): void
    {
        $this->mod[$y][$x] = $dark;
        $this->fn[$y][$x]  = true;
    }

    /** @return list<int> */
    private function alignmentPositions(): array
    {
        $v = $this->version;
        if ($v === 1) {
            return [];
        }
        $num  = intdiv($v, 7) + 2;
        $step = $v === 32 ? 26 : intdiv($v * 4 + $num * 2 + 1, $num * 2 - 2) * 2;
        $res  = [6];
        for ($pos = $this->size - 7; count($res) < $num; $pos -= $step) {
            array_splice($res, 1, 0, [$pos]);
        }

        return $res;
    }

    private static function numRawDataModules(int $v): int
    {
        $r = (16 * $v + 128) * $v + 64;
        if ($v >= 2) {
            $n = intdiv($v, 7) + 2;
            $r -= (25 * $n - 10) * $n - 55;
            if ($v >= 7) {
                $r -= 36;
            }
        }

        return $r;
    }

    private static function numDataCodewords(int $v): int
    {
        return intdiv(self::numRawDataModules($v), 8) - self::ECC_PER_BLOCK[$v] * self::NUM_BLOCKS[$v];
    }

    /** @param list<int> $data @return list<int> */
    private function addEccAndInterleave(array $data): array
    {
        $v         = $this->version;
        $numBlocks = self::NUM_BLOCKS[$v];
        $eccLen    = self::ECC_PER_BLOCK[$v];
        $raw       = intdiv(self::numRawDataModules($v), 8);
        $numShort  = $numBlocks - $raw % $numBlocks;
        $shortLen  = intdiv($raw, $numBlocks);
        $divisor   = self::rsDivisor($eccLen);
        $blocks    = [];
        $k         = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $dat = array_slice($data, $k, $shortLen - $eccLen + ($i < $numShort ? 0 : 1));
            $k += count($dat);
            $ecc = self::rsRemainder($dat, $divisor);
            if ($i < $numShort) {
                $dat[] = null; // padding so all blocks line up
            }
            $blocks[] = array_merge($dat, $ecc);
        }
        $out = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $b) {
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $out[] = $b[$i];
                }
            }
        }

        return $out;
    }

    /** @return list<int> */
    private static function rsDivisor(int $degree): array
    {
        $res = array_fill(0, $degree - 1, 0);
        $res[] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $res[$j] = self::gfMul($res[$j], $root);
                if ($j + 1 < $degree) {
                    $res[$j] ^= $res[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }

        return $res;
    }

    /** @return list<int> */
    private static function rsRemainder(array $data, array $divisor): array
    {
        $res = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($res);
            $res[]  = 0;
            foreach ($divisor as $i => $d) {
                $res[$i] ^= self::gfMul($d, $factor);
            }
        }

        return $res;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    private function drawCodewords(array $data): void
    {
        $i = 0;
        $total = count($data) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x  = $right - $j;
                    $up = (($right + 1) & 2) === 0;
                    $y  = $up ? $this->size - 1 - $vert : $vert;
                    if (! $this->fn[$y][$x] && $i < $total) {
                        $this->mod[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) !== 0;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $m): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->fn[$y][$x]) {
                    continue;
                }
                $inv = match ($m) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($inv) {
                    $this->mod[$y][$x] = ! $this->mod[$y][$x];
                }
            }
        }
    }

    private function penalty(): int
    {
        $s = $this->size;
        $p = 0;
        $m = $this->mod;
        // rows and columns: runs and finder-like patterns
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $s; $a++) {
                $line = [];
                for ($b = 0; $b < $s; $b++) {
                    $line[] = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                }
                $run = 1;
                for ($b = 1; $b <= $s; $b++) {
                    if ($b < $s && $line[$b] === $line[$b - 1]) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $p += 3 + ($run - 5);
                        }
                        $run = 1;
                    }
                }
                $str = implode('', array_map(static fn ($v) => $v ? '1' : '0', $line));
                $p += 40 * (substr_count('0000' . $str . '0000', '10111010000') + substr_count('0000' . $str . '0000', '00001011101'));
            }
        }
        $dark = 0;
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                $dark += $m[$y][$x] ? 1 : 0;
                if ($y < $s - 1 && $x < $s - 1 && $m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $p += 3;
                }
            }
        }
        $total = $s * $s;
        $k     = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;

        return $p + $k * 10;
    }
}
