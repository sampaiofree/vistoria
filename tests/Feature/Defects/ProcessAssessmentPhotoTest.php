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
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
        $originalPath = $photo->original_path;

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
        $this->assertNull($photo->original_path);
        Storage::disk('inspection_photos')->assertMissing($originalPath);
    }

    public static function imageDimensions(): array
    {
        return [
            'uploaded landscape' => [754, 502, 754, 502, 480, 320],
            'large landscape' => [4000, 3000, 3000, 2250, 480, 360],
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_processing_does_not_change_the_imagick_time_resource_limit(): void
    {
        Storage::fake('inspection_photos');
        $photo = $this->photoWithImage(320, 240);
        $job = new ProcessAssessmentPhoto($photo->id);
        $image = new \Imagick;
        $initialLimit = $image->getResourceLimit(\Imagick::RESOURCETYPE_TIME);

        $job->handle();

        $this->assertSame(180, $job->timeout);
        $this->assertSame($initialLimit, $image->getResourceLimit(\Imagick::RESOURCETYPE_TIME));
        $this->assertNull(config('photos.processing.time_seconds'));
        $image->clear();
        $image->destroy();
    }

    public function test_final_processing_failure_deletes_the_temporary_original(): void
    {
        Storage::fake('inspection_photos');
        config()->set('photos.limits.max_pixels', 10_000);
        $photo = $this->photoWithImage(200, 100);
        $uploader = User::factory()->for($photo->inspection->organization)->create();
        $responsible = User::factory()->for($photo->inspection->organization)->create();
        InspectionResponsible::factory()->forInspection($photo->inspection, $responsible)->create();
        $photo->update(['uploaded_by' => $uploader->id]);

        $originalPath = $photo->original_path;
        $job = new ProcessAssessmentPhoto($photo->id);

        try {
            $job->handle();
            $this->fail('O processamento deveria rejeitar a imagem acima do limite seguro.');
        } catch (RuntimeException $exception) {
            $this->assertSame(config('photos.processing.unsafe_image_message'), $exception->getMessage());
            $job->failed($exception);
        }

        $photo->refresh();
        $this->assertSame(PhotoProcessingStatus::Failed, $photo->processing_status);
        $this->assertSame(config('photos.processing.unsafe_image_message'), $photo->processing_error);
        $this->assertNull($photo->original_path);
        Storage::disk('inspection_photos')->assertMissing($originalPath);
        Storage::disk('inspection_photos')->assertMissing(dirname($originalPath).'/optimized.webp');
        Storage::disk('inspection_photos')->assertMissing(dirname($originalPath).'/thumbnail.webp');
        $this->assertDatabaseCount('notifications', 2);

        $job->failed(new RuntimeException('Falha duplicada.'));
        $this->assertDatabaseCount('notifications', 2);
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
