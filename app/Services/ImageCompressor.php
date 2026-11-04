<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Kompres foto (villa, nota, bukti bayar) sebelum disimpan.
 * Foto HP 4–8 MB → ±200–400 KB, cukup jelas untuk dibaca, hemat storage hosting.
 */
class ImageCompressor
{
    public function store(UploadedFile $file, string $directory, string $disk = 'public', int $maxSide = 1600, int $quality = 78): array
    {
        // Foto HP 12–48 MP butuh 50–200 MB RAM saat didekode GD; naikkan sementara (umum dibatasi 128 MB di cPanel).
        $this->ensureMemory('320M');

        try {
            $image = Image::decodePath($file->getRealPath())
                ->orient()               // putar sesuai EXIF (foto HP sering miring)
                ->scaleDown($maxSide, $maxSide);
            $binary = $image->encode(new JpegEncoder(quality: $quality, progressive: true, strip: true))->toString();
            $ext = 'jpg';
        } catch (\Throwable $e) {
            // Format yang tidak didukung GD (mis. HEIC iPhone) → simpan file asli apa adanya.
            report($e);
            $binary = file_get_contents($file->getRealPath());
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        }

        $path = trim($directory, '/').'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
        Storage::disk($disk)->put($path, $binary);
        $size = strlen($binary);

        unset($image, $binary);
        gc_collect_cycles();

        return [
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'size' => $size,
        ];
    }

    private function ensureMemory(string $wanted): void
    {
        $toBytes = function (string $v): int {
            $v = trim($v);
            if ($v === '-1') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;

            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
            };
        };
        if ($toBytes((string) ini_get('memory_limit')) < $toBytes($wanted)) {
            @ini_set('memory_limit', $wanted);
        }
    }
}
