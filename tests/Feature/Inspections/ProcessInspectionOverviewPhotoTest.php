<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessInspectionOverviewPhoto;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProcessInspectionOverviewPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_processing_keeps_only_webp_variants(): void
    {
        Storage::fake('inspection_photos');
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $block = InspectionOverviewBlock::query()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'position' => 1,
        ]);
        $upload = UploadedFile::fake()->image('vista.jpg', 1200, 800);
        $path = "tests/overview/{$inspection->public_id}/original.jpg";
        Storage::disk('inspection_photos')->put($path, $upload->getContent());
        $photo = InspectionOverviewPhoto::factory()->forBlock($block)->create([
            'original_path' => $path,
            'original_size' => $upload->getSize(),
        ]);

        (new ProcessInspectionOverviewPhoto($photo->id))->handle();

        $photo->refresh();
        $this->assertSame(PhotoProcessingStatus::Ready, $photo->processing_status);
        $this->assertNull($photo->original_path);
        Storage::disk('inspection_photos')->assertMissing($path);
        Storage::disk('inspection_photos')->assertExists($photo->optimized_path);
        Storage::disk('inspection_photos')->assertExists($photo->thumbnail_path);
    }
}
