<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InspectionLocationMapRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspector_can_create_map_upload_source_and_access_private_background(): void
    {
        Storage::fake('inspection_maps');
        [$organization, $user, $inspection, $category] = $this->context();

        $response = $this->actingAs($user)->post(route('inspections.location-maps.store', $inspection), [
            'title' => 'Mapa CIVIL',
            'defect_category_id' => $category->id,
        ]);
        $response->assertRedirect();
        $map = InspectionLocationMap::query()->firstOrFail();

        $this->actingAs($user)->post(route('inspection-location-maps.source', $map), [
            'file' => UploadedFile::fake()->image('mapa.png', 120, 80),
            'lock_version' => $map->lock_version,
        ])->assertRedirect();

        $map->refresh();
        $this->assertSame('ready', $map->processing_status->value);
        Storage::disk('inspection_maps')->assertExists($map->background_path);
        Storage::disk('inspection_maps')->assertExists(dirname($map->background_path).'/thumbnail.webp');

        $this->actingAs($user)
            ->get(route('inspection-location-maps.background', $map))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');

        $this->actingAs($user)
            ->get(route('inspection-location-maps.background', [$map, 'thumbnail']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame($organization->id, $map->organization_id);
    }

    public function test_map_rejects_stale_update_version(): void
    {
        [$organization, $user, $inspection, $category] = $this->context();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create();

        $this->actingAs($user)->put(route('inspection-location-maps.update', $map), [
            'title' => 'Atualizado',
            'lock_version' => 2,
        ])->assertSessionHasErrors('lock_version');

        $this->assertNotSame('Atualizado', $map->fresh()->title);
        $this->assertSame($organization->id, $map->organization_id);
    }

    public function test_processor_normalizes_image_sources_idempotently(): void
    {
        Storage::fake('inspection_maps');
        [, $user, $inspection, $category] = $this->context();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create();
        $webp = new \Imagick;
        $webp->newImage(120, 80, new \ImagickPixel('#cbd5e1'));
        $webp->setImageFormat('webp');
        $webpContents = $webp->getImageBlob();
        $webp->clear();
        $webp->destroy();
        $sources = [
            UploadedFile::fake()->image('mapa.png', 120, 80),
            UploadedFile::fake()->image('mapa.jpg', 120, 80),
            UploadedFile::fake()->createWithContent('mapa.webp', $webpContents),
        ];

        foreach ($sources as $source) {
            $map->refresh();
            $this->actingAs($user)->post(route('inspection-location-maps.source', $map), [
                'file' => $source,
                'lock_version' => $map->lock_version,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $map->refresh();
            $this->assertSame(InspectionLocationMapProcessingStatus::Ready, $map->processing_status);
            $this->assertSame('image/webp', $map->background_mime_type);
            Storage::disk('inspection_maps')->assertExists($map->background_path);
            Storage::disk('inspection_maps')->assertExists(dirname($map->background_path).'/thumbnail.webp');
            $this->assertStringContainsString(
                '/derivatives/'.hash('sha256', $map->source_checksum).'/',
                $map->background_path,
            );
        }
    }

    public function test_source_upload_accepts_images_and_rejects_pdf_sources(): void
    {
        Storage::fake('inspection_maps');
        [, $user, $inspection, $category] = $this->context();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create();

        $this->actingAs($user)->post(route('inspection-location-maps.source', $map), [
            'file' => new UploadedFile(
                database_path('seeders/fixtures/view-first-demo-procedure.pdf'),
                'procedimento.pdf',
                'application/pdf',
                null,
                true,
            ),
            'lock_version' => $map->lock_version,
        ])->assertSessionHasErrors('file');

        $this->assertNull($map->fresh()->source_path);
    }

    public function test_deleting_map_redirects_to_location_list_and_soft_deletes_markers(): void
    {
        [, $user, $inspection, $category] = $this->context();
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'defect_category_id' => $category->id,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create();
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();

        $this->actingAs($user)
            ->delete(route('inspection-location-maps.destroy', $map))
            ->assertRedirect(route('inspections.locations', $inspection));

        $this->assertSoftDeleted('inspection_location_maps', ['id' => $map->id]);
        $this->assertSoftDeleted('inspection_location_markers', ['id' => $marker->id]);
        $this->assertNull(InspectionLocationMarker::withTrashed()->findOrFail($marker->id)->active_slot);

        $replacementMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create();
        $replacement = InspectionLocationMarker::factory()->forMapAndAssessment($replacementMap, $assessment)->create();
        $this->assertSame($assessment->id, $replacement->defect_assessment_id);
    }

    /** @return array{0:Organization,1:User,2:Inspection,3:DefectCategory} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'MAP']);

        return [$organization, $user, $inspection, $category];
    }
}
