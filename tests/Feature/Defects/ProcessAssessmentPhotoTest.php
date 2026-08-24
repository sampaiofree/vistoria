<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessAssessmentPhoto;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ProcessAssessmentPhotoTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('imageDimensions')]
    public function test_processing_preserves_aspect_ratio_without_upscaling_or_padding(
        int $sourceWidth,
        int $sourceHeight,
        int $optimizedWidth,
        int $optimizedHeight,
        int $thumbnailWidth,
        int $thumbnailHeight,
    ): void {
        Storage::fake('inspection_photos');
        $photo = $this->photoWithImage($sourceWidth, $sourceHeight);
        $originalContents = Storage::disk('inspection_photos')->get($photo->original_path);

        (new ProcessAssessmentPhoto($photo->id))->handle();

        $photo->refresh();
        $this->assertSame(PhotoProcessingStatus::Ready, $photo->processing_status);
        $this->assertSame($sourceWidth, $photo->original_width);
        $this->assertSame($sourceHeight, $photo->original_height);
        $this->assertSame($optimizedWidth, $photo->optimized_width);
        $this->assertSame($optimizedHeight, $photo->optimized_height);
        $this->assertSame($thumbnailWidth, $photo->thumbnail_width);
        $this->assertSame($thumbnailHeight, $photo->thumbnail_height);
        $this->assertImageDimensions($photo->optimized_path, $optimizedWidth, $optimizedHeight);
        $this->assertImageDimensions($photo->thumbnail_path, $thumbnailWidth, $thumbnailHeight);
        $this->assertSame($originalContents, Storage::disk('inspection_photos')->get($photo->original_path));
    }

    public static function imageDimensions(): array
    {
        return [
            'uploaded landscape' => [754, 502, 754, 502, 480, 320],
            'large landscape' => [4000, 3000, 2000, 1500, 480, 360],
            'portrait' => [300, 500, 300, 500, 288, 480],
            'square' => [800, 800, 800, 800, 480, 480],
            'small image' => [320, 240, 320, 240, 320, 240],
        ];
    }

    public function test_processing_applies_exif_orientation_before_generating_derivatives(): void
    {
        Storage::fake('inspection_photos');
        $photo = $this->photoWithImage(300, 500, 6);

        (new ProcessAssessmentPhoto($photo->id))->handle();

        $photo->refresh();
        $this->assertSame(500, $photo->original_width);
        $this->assertSame(300, $photo->original_height);
        $this->assertSame(500, $photo->optimized_width);
        $this->assertSame(300, $photo->optimized_height);
        $this->assertSame(480, $photo->thumbnail_width);
        $this->assertSame(288, $photo->thumbnail_height);
        $this->assertImageDimensions($photo->optimized_path, 500, 300);
        $this->assertImageDimensions($photo->thumbnail_path, 480, 288);
    }

    public function test_processing_rejects_unsafe_dimensions_without_deleting_the_original(): void
    {
        Storage::fake('inspection_photos');
        config()->set('photos.limits.max_pixels', 10_000);
        $photo = $this->photoWithImage(200, 100);

        try {
            (new ProcessAssessmentPhoto($photo->id))->handle();
            $this->fail('O processamento deveria rejeitar a imagem acima do limite seguro.');
        } catch (RuntimeException $exception) {
            $this->assertSame(config('photos.processing.unsafe_image_message'), $exception->getMessage());
        }

        $photo->refresh();
        $this->assertSame(PhotoProcessingStatus::Failed, $photo->processing_status);
        $this->assertSame(config('photos.processing.unsafe_image_message'), $photo->processing_error);
        Storage::disk('inspection_photos')->assertExists($photo->original_path);
        Storage::disk('inspection_photos')->assertMissing(dirname($photo->original_path).'/optimized.webp');
        Storage::disk('inspection_photos')->assertMissing(dirname($photo->original_path).'/thumbnail.webp');
    }

    private function photoWithImage(int $width, int $height, ?int $orientation = null): AssessmentPhoto
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create();
        $photo = AssessmentPhoto::factory()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
            'original_path' => null,
        ]);
        $contents = $this->imageBlob($width, $height, $orientation);
        $path = "tests/{$photo->public_id}/original.jpg";
        Storage::disk('inspection_photos')->put($path, $contents);
        $photo->update([
            'original_path' => $path,
            'original_size' => strlen($contents),
        ]);

        return $photo->refresh();
    }

    private function imageBlob(int $width, int $height, ?int $orientation = null): string
    {
        $image = new \Imagick;
        $image->newImage($width, $height, new \ImagickPixel('#336699'));
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(85);
        $contents = $image->getImageBlob();
        $image->clear();
        $image->destroy();

        if ($orientation === null) {
            return $contents;
        }

        $tiff = "II\x2A\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00"
            .pack('v', $orientation)."\x00\x00\x00\x00\x00\x00";
        $payload = "Exif\x00\x00".$tiff;

        return substr($contents, 0, 2)
            ."\xFF\xE1".pack('n', strlen($payload) + 2).$payload
            .substr($contents, 2);
    }

    private function assertImageDimensions(?string $path, int $width, int $height): void
    {
        $this->assertNotNull($path);
        $image = new \Imagick;
        $image->readImageBlob(Storage::disk('inspection_photos')->get($path));
        $this->assertSame($width, $image->getImageWidth());
        $this->assertSame($height, $image->getImageHeight());
        $image->clear();
        $image->destroy();
    }
}
