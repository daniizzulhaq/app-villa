<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Format & parsing uang Rupiah.
 * Uang disimpan DECIMAL(15,2) dan dihitung presisi; pembulatan ke rupiah utuh HANYA saat ditampilkan.
 */
final class Rupiah
{
    /** "Rp 1.000.000" — negatif: "−Rp 1.000.000". */
    public static function format(BigDecimal|string|int|float|null $amount, bool $withPrefix = true): string
    {
        $value = BigDecimal::of(self::normalize($amount))->toScale(0, RoundingMode::HALF_UP);
        $negative = $value->isNegative();
        // Kelompokkan ribuan dari string (tanpa float) agar presisi tetap terjaga.
        $digits = strrev(implode('.', str_split(strrev((string) $value->abs()), 3)));

        $text = ($withPrefix ? 'Rp ' : '').$digits;

        return $negative ? '−'.$text : $text;
    }

    /** "Rp 1,2 jt" untuk label grafik/kartu kecil. */
    public static function short(BigDecimal|string|int|float|null $amount): string
    {
        $v = (float) self::normalize($amount);
        $abs = abs($v);
        $sign = $v < 0 ? '−' : '';

        return $sign.match (true) {
            $abs >= 1_000_000_000 => 'Rp '.self::trim($abs / 1_000_000_000).' '.__('M'),
            $abs >= 1_000_000 => 'Rp '.self::trim($abs / 1_000_000).' '.__('jt'),
            $abs >= 1_000 => 'Rp '.self::trim($abs / 1_000).' '.__('rb'),
            default => 'Rp '.number_format($abs, 0, ',', '.'),
        };
    }

    /**
     * Ubah input pengguna ("Rp 1.250.000", "1250000", "1.250.000,50") menjadi string desimal "1250000.50".
     * Mengembalikan null jika tidak valid.
     */
    public static function parse(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $s = trim(str_ireplace(['rp', ' ', "\u{00A0}"], '', $input));
        if ($s === '') {
            return null;
        }
        $negative = str_starts_with($s, '-') || str_starts_with($s, '−');
        $s = ltrim($s, '-−');
        // Format Indonesia: titik = ribuan, koma = desimal
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }

        return ($negative ? '-' : '').(string) BigDecimal::of($s)->toScale(2, RoundingMode::HALF_UP);
    }

    /** "15%" / "12,5%" */
    public static function percent(BigDecimal|string|int|float|null $value, int $decimals = 2): string
    {
        $n = number_format((float) self::normalize($value), $decimals, ',', '.');
        if (str_contains($n, ',')) {
            $n = rtrim(rtrim($n, '0'), ',');
        }

        return $n.'%';
    }

    private static function normalize(BigDecimal|string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0';
        }
        if ($amount instanceof BigDecimal) {
            return (string) $amount;
        }
        if (is_float($amount)) {
            return number_format($amount, 2, '.', '');
        }

        return (string) $amount;
    }

    private static function trim(float $v): string
    {
        $s = number_format($v, 1, ',', '.');

        return str_ends_with($s, ',0') ? substr($s, 0, -2) : $s;
    }
}
