<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class ImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Compress an image to be under the specified file size in bytes.
     */
    public function compressImage(UploadedFile $file, ?int $maxSizeBytes = null): string
    {
        // Use config values if not provided
        $maxSizeBytes = $maxSizeBytes ?? config('image.compression.max_file_size', 2097152);

        // Create image instance
        $image = $this->manager->read($file->getRealPath());

        // Get original dimensions
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Start with quality from config
        $quality = config('image.compression.quality.initial', 90);
        $resizeRatio = 1.0;

        do {
            // Apply resize if needed
            if ($resizeRatio < 1.0) {
                $newWidth = (int) ($originalWidth * $resizeRatio);
                $newHeight = (int) ($originalHeight * $resizeRatio);
                $processedImage = $this->manager->read($file->getRealPath())->resize($newWidth, $newHeight);
            } else {
                $processedImage = clone $image;
            }

            // Encode with current quality
            $encoded = $this->encodeImage($processedImage, $file->getClientOriginalExtension(), $quality);
            $fileSize = strlen($encoded);

            // If size is acceptable, break
            if ($fileSize <= $maxSizeBytes) {
                break;
            }

            // Reduce quality or resize
            $minQuality = config('image.compression.quality.minimum', 50);
            $qualityStep = config('image.compression.quality.step', 10);
            $resizeStep = config('image.compression.resize.step', 0.1);
            $minResizeRatio = config('image.compression.resize.minimum_ratio', 0.3);

            if ($quality > $minQuality) {
                $quality -= $qualityStep;
            } else {
                $resizeRatio -= $resizeStep;
                $quality = config('image.compression.quality.initial', 90); // Reset quality when resizing
            }

            // Prevent infinite loop
            if ($resizeRatio < $minResizeRatio) {
                break;
            }

        } while ($fileSize > $maxSizeBytes);

        return $encoded;
    }

    /**
     * Encode image based on extension with specified quality.
     */
    private function encodeImage(ImageInterface $image, string $extension, int $quality): string
    {
        $extension = strtolower($extension);

        switch ($extension) {
            case 'png':
                return $image->toPng();
            case 'webp':
                return $image->toWebp($quality);
            case 'gif':
                return $image->toGif();
            case 'jpg':
            case 'jpeg':
            default:
                return $image->toJpeg($quality);
        }
    }

    /**
     * Save compressed image to storage.
     */
    public function saveCompressedImage(UploadedFile $file, string $directory, string $filename, string $disk = 'private'): string
    {
        $compressedData = $this->compressImage($file);

        $path = $directory . '/' . $filename;

        \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $compressedData);

        return $path;
    }

    /**
     * Get file size in a human readable format.
     */
    public function getHumanFileSize(int $bytes, int $decimals = 2): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $factor = max(0, min($factor, count($units) - 1));

        $value = $bytes / (1024 ** $factor);
        return sprintf("%.{$decimals}f", $value) . ' ' . $units[$factor];
    }
}
