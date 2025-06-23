<?php

namespace Tests\Unit\Services;

use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageServiceTest extends TestCase
{
    private ImageService $imageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageService = new ImageService();
    }

    public function test_compress_image_reduces_file_size(): void
    {
        // Create a fake image file
        $file = UploadedFile::fake()->image('test.jpg', 1000, 1000)->size(5120); // 5MB fake file

        // Compress the image
        $compressedData = $this->imageService->compressImage($file, 2097152); // 2MB max

        // Check that the compressed data is smaller than the target size
        $this->assertLessThanOrEqual(2097152, strlen($compressedData));
    }

    public function test_save_compressed_image_creates_file(): void
    {
        Storage::fake('public');

        // Create a fake image file
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        // Save compressed image
        $path = $this->imageService->saveCompressedImage(
            $file,
            'test-directory',
            'test-image.jpg',
            'public'
        );

        // Check that the file was created
        $this->assertTrue(Storage::disk('public')->exists($path));

        // Check that the file size is reasonable (less than 2MB)
        $fileSize = strlen(Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(2097152, $fileSize);
    }

    public function test_get_human_file_size_formats_correctly(): void
    {
        $this->assertEquals('1.00KB', $this->imageService->getHumanFileSize(1024));
        $this->assertEquals('1.00MB', $this->imageService->getHumanFileSize(1048576));
        $this->assertEquals('2.00MB', $this->imageService->getHumanFileSize(2097152));
    }
} 