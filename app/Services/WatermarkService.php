<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WatermarkService
{
    /**
     * Stamp a diagonal watermark on an image binary string.
     * Requires the GD extension. If GD is unavailable, returns the original
     * binary and logs a warning.
     *
     * @param string $imageData   Raw image binary
     * @param string $watermarkText  Text to stamp (e.g. "RAHASIA • User Name • timestamp")
     * @param string $mimeType    e.g. image/jpeg, image/png
     * @return string  Watermarked image binary (same mime type)
     */
    public function stampImage(string $imageData, string $watermarkText, string $mimeType): string
    {
        if (! extension_loaded('gd')) {
            Log::warning('WatermarkService: GD extension not loaded, streaming original without watermark.');
            return $imageData;
        }

        $src = imagecreatefromstring($imageData);
        if ($src === false) {
            Log::warning('WatermarkService: imagecreatefromstring failed, streaming original.');
            return $imageData;
        }

        $width  = imagesx($src);
        $height = imagesy($src);

        // Semi-transparent red overlay colour for watermark text
        $color = imagecolorallocatealpha($src, 200, 50, 50, 60);
        if ($color === false) {
            $color = imagecolorallocate($src, 200, 50, 50) ?: 0;
        }

        $this->drawDiagonalText($src, $watermarkText, $color, $width, $height);

        ob_start();
        $this->outputImage($src, $mimeType);
        $output = ob_get_clean();
        imagedestroy($src);

        return $output ?: $imageData;
    }

    private function drawDiagonalText(\GdImage $img, string $text, int $color, int $width, int $height): void
    {
        // GD built-in fonts are 8px wide × 16px tall (font=5)
        $fontWidth  = imagefontwidth(5);
        $fontHeight = imagefontheight(5);
        $textPixels = strlen($text) * $fontWidth;

        // Draw text in a diagonal grid pattern across the image
        $stepX = (int) max(200, $textPixels + 40);
        $stepY = 120;

        for ($y = -$height; $y < $height * 2; $y += $stepY) {
            for ($x = -$width; $x < $width * 2; $x += $stepX) {
                imagestring($img, 5, $x, $y, $text, $color);
            }
        }
    }

    private function outputImage(\GdImage $img, string $mimeType): void
    {
        match (true) {
            str_contains($mimeType, 'png')  => imagepng($img),
            str_contains($mimeType, 'gif')  => imagegif($img),
            default                         => imagejpeg($img, null, 85),
        };
    }

    public function buildWatermarkText(string $name, string $nip, string $timestamp): string
    {
        return "RAHASIA \u{2022} {$name} ({$nip}) \u{2022} {$timestamp}";
    }
}
