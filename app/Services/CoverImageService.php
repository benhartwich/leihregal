<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CoverImageService
{
    public const MAX_WIDTH  = 600;
    public const MAX_HEIGHT = 900;
    public const JPEG_Q     = 82;

    public function storeFromUrl(string $url, ?string $oldPath = null): ?string
    {
        $contents = @file_get_contents($url);
        if (! $contents) return null;
        return $this->storeBinary($contents, $oldPath);
    }

    public function storeFromUpload(UploadedFile $file, ?string $oldPath = null): ?string
    {
        $contents = file_get_contents($file->getRealPath());
        if (! $contents) return null;
        return $this->storeBinary($contents, $oldPath);
    }

    public function storeBinary(string $contents, ?string $oldPath = null): ?string
    {
        $resized = $this->resize($contents);
        if (! $resized) return null;

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
        $filename = 'covers/' . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, $resized);
        return $filename;
    }

    public function resize(string $binary): ?string
    {
        $img = @imagecreatefromstring($binary);
        if (! $img) return null;

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w === 0 || $h === 0) {
            imagedestroy($img);
            return null;
        }

        $scale = min(self::MAX_WIDTH / $w, self::MAX_HEIGHT / $h, 1.0);
        if ($scale < 1.0) {
            $newW = (int) round($w * $scale);
            $newH = (int) round($h * $scale);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        ob_start();
        imagejpeg($img, null, self::JPEG_Q);
        $out = ob_get_clean();
        imagedestroy($img);
        return $out ?: null;
    }
}
