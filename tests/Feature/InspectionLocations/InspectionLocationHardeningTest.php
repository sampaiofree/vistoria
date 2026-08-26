<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Actions\InspectionLocations\CreateInspectionLocationMap;
use App\Actions\InspectionLocations\DeleteInspectionLocationMap;
use App\Actions\InspectionLocations\StoreInspectionLocationMapSource;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionLocationMapSourceKind;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Jobs\ProcessInspectionLocationMap;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use App\Services\InspectionLocations\InspectionLocationSourceValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

final class InspectionLocationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_tenant_cannot_access_map_pages_or_private_asset(): void
    {
        Storage::fake('inspection_maps');
        [, , , , $map] = $this->context();
        $this->storeBackground($map, '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'image/svg+xml');
        $otherOrganization = Organization::factory()->create();
        $foreignUser = User::factory()->for($otherOrganization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $this->actingAs($foreignUser)->get(route('inspection-location-maps.edit', $map))->assertNotFound();
        $this->actingAs($foreignUser)->get(route('inspection-location-maps.editor', $map))->assertNotFound();
        $this->actingAs($foreignUser)->get(route('inspection-location-maps.background', $map))->assertNotFound();
        $this->actingAs($foreignUser)->put(route('inspection-location-maps.update', $map), [
            'title' => 'Tentativa estrangeira',
            'lock_version' => $map->lock_version,
        ])->assertForbidden();

        $this->assertNotSame('Tentativa estrangeira', $map->fresh()->title);
    }

    public function test_asset_route_blocks_path_traversal_and_sandboxes_inline_svg(): void
    {
        Storage::fake('inspection_maps');
        [, $user, , , $map] = $this->context();
        $map->update([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_disk' => 'inspection_maps',
            'background_path' => '../../.env',
            'background_mime_type' => 'image/svg+xml',
        ]);

        $this->actingAs($user)->get(route('inspection-location-maps.background', $map))->assertNotFound();

        $this->storeBackground($map, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml');
        $this->actingAs($user)
            ->get(route('inspection-location-maps.background', $map))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_source_validator_rejects_excessive_decompressed_dimensions_and_invalid_crop(): void
    {
        config()->set('inspection_locations.limits.image_pixels', 100);
        $validator = app(InspectionLocationSourceValidator::class);

        try {
            $validator->validate(UploadedFile::fake()->image('large.png', 120, 80), ['source_page' => 1]);
            $this->fail('A imagem deveria exceder o limite de pixels.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        config()->set('inspection_locations.limits.image_pixels', 80_000_000);
        try {
            $validator->validate(UploadedFile::fake()->image('crop.png', 120, 80), [
                'source_page' => 1,
                'source_crop' => ['x' => 0.8, 'y' => 0.1, 'width' => 0.4, 'height' => 0.2],
            ]);
            $this->fail('O recorte deveria ultrapassar a imagem.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source_crop', $exception->errors());
        }
    }

    public function test_processor_ignores_stale_jobs_and_sanitizes_failures(): void
    {
        Storage::fake('inspection_maps');
        [, , , , $map] = $this->context();
        $sourcePath = $this->mapDirectory($map).'/source.png';
        $map->update([
            'source_kind' => InspectionLocationMapSourceKind::Upload,
            'source_disk' => 'inspection_maps',
            'source_path' => $sourcePath,
            'source_mime_type' => 'image/png',
            'source_size' => 100,
            'source_checksum' => 'new-checksum',
            'processing_status' => InspectionLocationMapProcessingStatus::Pending,
        ]);

        (new ProcessInspectionLocationMap($map->id, 'old-checksum'))->handle(app(InspectionLocationAssetGuard::class));
        $this->assertSame(InspectionLocationMapProcessingStatus::Pending, $map->fresh()->processing_status);

        $job = new ProcessInspectionLocationMap($map->id, 'new-checksum');
        try {
            $job->handle(app(InspectionLocationAssetGuard::class));
            $this->fail('O processamento deveria falhar sem o arquivo de origem.');
        } catch (\RuntimeException $exception) {
            $job->failed($exception);
        }
        $map->refresh();
        $this->assertSame(InspectionLocationMapProcessingStatus::Failed, $map->processing_status);
        $this->assertSame(config('inspection_locations.processing.error_message'), $map->processing_error);
        $this->assertStringNotContainsString($sourcePath, $map->processing_error);

        $map->update(['processing_status' => InspectionLocationMapProcessingStatus::Ready]);
        (new ProcessInspectionLocationMap($map->id, 'new-checksum'))->failed(new \RuntimeException('late failure'));
        $this->assertSame(InspectionLocationMapProcessingStatus::Ready, $map->fresh()->processing_status);
    }

    public function test_map_processing_uses_three_automatic_attempts(): void
    {
        $job = new ProcessInspectionLocationMap(123, 'checksum');

        $this->assertSame(3, $job->tries);
        $this->assertSame(180, $job->timeout);
        $this->assertSame([10, 60, 300], $job->backoff());
        $this->assertSame(210, config('horizon.defaults.supervisor-images.timeout'));
        $this->assertSame(3000, config('inspection_locations.processing.max_output_dimension'));
        $this->assertNull(config('inspection_locations.processing.time_seconds'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_map_processing_does_not_change_the_imagick_time_resource_limit(): void
    {
        Storage::fake('inspection_maps');
        [, $user, , , $map] = $this->context();
        $upload = UploadedFile::fake()->image('mapa.jpg', 400, 300);
        $sourcePath = $this->mapDirectory($map).'/source.jpg';
        Storage::disk('inspection_maps')->put($sourcePath, $upload->getContent());
        $checksum = hash('sha256', $upload->getContent());
        $map->update([
            'source_kind' => InspectionLocationMapSourceKind::Upload,
            'source_disk' => 'inspection_maps',
            'source_path' => $sourcePath,
            'source_mime_type' => 'image/jpeg',
            'source_size' => $upload->getSize(),
            'source_checksum' => $checksum,
            'source_uploaded_by' => $user->id,
            'processing_status' => InspectionLocationMapProcessingStatus::Pending,
        ]);

        $image = new \Imagick;
        $initialLimit = $image->getResourceLimit(\Imagick::RESOURCETYPE_TIME);

        (new ProcessInspectionLocationMap($map->id, $checksum))->handle(app(InspectionLocationAssetGuard::class));

        $this->assertSame($initialLimit, $image->getResourceLimit(\Imagick::RESOURCETYPE_TIME));
        $image->clear();
        $image->destroy();
    }

    public function test_successful_map_processing_removes_uploaded_source_and_keeps_derivatives(): void
    {
        Storage::fake('inspection_maps');
        [, $user, , , $map] = $this->context();
        config()->set('inspection_locations.processing.max_output_dimension', 300);
        $upload = UploadedFile::fake()->image('mapa.jpg', 400, 300);
        $sourcePath = $this->mapDirectory($map).'/source.jpg';
        Storage::disk('inspection_maps')->put($sourcePath, $upload->getContent());
        $checksum = hash('sha256', $upload->getContent());
        $map->update([
            'source_kind' => InspectionLocationMapSourceKind::Upload,
            'source_disk' => 'inspection_maps',
            'source_path' => $sourcePath,
            'source_mime_type' => 'image/jpeg',
            'source_size' => $upload->getSize(),
            'source_checksum' => $checksum,
            'source_uploaded_by' => $user->id,
            'processing_status' => InspectionLocationMapProcessingStatus::Pending,
        ]);

        (new ProcessInspectionLocationMap($map->id, $checksum))->handle(app(InspectionLocationAssetGuard::class));

        $map->refresh();
        $this->assertSame(InspectionLocationMapProcessingStatus::Ready, $map->processing_status);
        $this->assertNull($map->source_path);
        $this->assertSame(300, max($map->background_width, $map->background_height));
        Storage::disk('inspection_maps')->assertMissing($sourcePath);
        Storage::disk('inspection_maps')->assertExists($map->background_path);
        Storage::disk('inspection_maps')->assertExists(dirname($map->background_path).'/thumbnail.webp');
    }

    public function test_replacing_an_uploaded_source_removes_the_previous_private_file(): void
    {
        Storage::fake('inspection_maps');
        Queue::fake();
        [, $user, , , $map] = $this->context();
        $previousPath = $this->mapDirectory($map).'/previous.png';
        Storage::disk('inspection_maps')->put($previousPath, 'previous source');
        $this->storeBackground($map, 'previous background', 'image/webp');
        $previousBackground = $map->fresh()->background_path;
        $previousThumbnail = $this->mapDirectory($map).'/thumbnail.webp';
        Storage::disk('inspection_maps')->put($previousThumbnail, 'previous thumbnail');
        $map->update([
            'source_kind' => InspectionLocationMapSourceKind::Upload,
            'source_disk' => 'inspection_maps',
            'source_path' => $previousPath,
            'source_mime_type' => 'image/png',
            'source_size' => 15,
            'source_checksum' => hash('sha256', 'previous source'),
        ]);

        $updated = app(StoreInspectionLocationMapSource::class)->handle(
            $user,
            $map,
            UploadedFile::fake()->image('replacement.png', 120, 80),
            ['lock_version' => $map->lock_version, 'source_page' => 1],
        );

        Storage::disk('inspection_maps')->assertMissing($previousPath);
        Storage::disk('inspection_maps')->assertMissing($previousBackground);
        Storage::disk('inspection_maps')->assertMissing($previousThumbnail);
        Storage::disk('inspection_maps')->assertExists($updated->source_path);
        Queue::assertPushed(ProcessInspectionLocationMap::class, 1);
    }

    public function test_capacity_and_mass_assignment_are_enforced(): void
    {
        [$organization, $user, $inspection, $category, $map] = $this->context();
        config()->set('inspection_locations.limits.maps_per_inspection', 1);

        try {
            app(CreateInspectionLocationMap::class)->handle($user, $inspection, [
                'title' => 'Mapa excedente',
                'defect_category_id' => $category->id,
            ]);
            $this->fail('O limite de mapas deveria ser aplicado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('maps', $exception->errors());
        }

        $this->actingAs($user)->put(route('inspection-location-maps.update', $map), [
            'title' => 'Título permitido',
            'lock_version' => $map->lock_version,
            'organization_id' => Organization::factory()->create()->id,
            'background_path' => '../../public/map.svg',
            'processing_status' => InspectionLocationMapProcessingStatus::Ready->value,
        ])->assertRedirect();

        $map->refresh();
        $this->assertSame($organization->id, $map->organization_id);
        $this->assertSame('Título permitido', $map->title);
        $this->assertNull($map->background_path);
    }

    public function test_map_and_marker_reorders_require_authority_and_complete_atomic_order(): void
    {
        [$organization, $user, $inspection, $category, $firstMap] = $this->context();
        $secondMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'position' => 2,
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);
        $unassigned = User::factory()->for($organization)->create();

        $this->actingAs($unassigned)->put(route('inspections.location-maps.reorder', $inspection), [
            'maps' => [
                ['public_id' => $secondMap->public_id, 'lock_version' => $secondMap->lock_version],
                ['public_id' => $firstMap->public_id, 'lock_version' => $firstMap->lock_version],
            ],
        ])->assertForbidden();

        $this->actingAs($user)->put(route('inspections.location-maps.reorder', $inspection), [
            'maps' => [['public_id' => $firstMap->public_id, 'lock_version' => $firstMap->lock_version]],
        ])->assertSessionHasErrors('maps');

        $this->actingAs($user)->put(route('inspections.location-maps.reorder', $inspection), [
            'maps' => [
                ['public_id' => $secondMap->public_id, 'lock_version' => $secondMap->lock_version],
                ['public_id' => $firstMap->public_id, 'lock_version' => $firstMap->lock_version],
            ],
        ])->assertRedirect();
        $this->assertSame(1, $secondMap->fresh()->position);
        $this->assertSame(2, $firstMap->fresh()->position);

        $assessment = $this->assessment($inspection, $category);
        $secondAssessment = $this->assessment($inspection, $category);
        $firstMarker = InspectionLocationMarker::factory()->forMapAndAssessment($firstMap, $assessment)->create(['position' => 1]);
        $secondMarker = InspectionLocationMarker::factory()->forMapAndAssessment($firstMap, $secondAssessment)->create(['position' => 2]);
        $firstMap->refresh();

        $this->actingAs($user)->put(route('inspection-location-maps.markers.reorder', $firstMap), [
            'marker_ids' => [$firstMarker->public_id],
            'map_lock_version' => $firstMap->lock_version,
        ])->assertSessionHasErrors('markers');

        $this->actingAs($user)->put(route('inspection-location-maps.markers.reorder', $firstMap), [
            'marker_ids' => [$secondMarker->public_id, $firstMarker->public_id],
            'map_lock_version' => $firstMap->lock_version,
        ])->assertRedirect();
        $this->assertSame(1, $secondMarker->fresh()->position);
        $this->assertSame(2, $firstMarker->fresh()->position);
    }

    public function test_map_deletion_removes_derivatives_immediately_and_keeps_shared_reference_document(): void
    {
        Storage::fake('inspection_maps');
        Storage::fake('equipment_documents');
        [, $user, $inspection, , $map] = $this->context();
        $document = EquipmentDocument::factory()->forEquipment($inspection->equipment)->create();
        Storage::disk('equipment_documents')->put($document->path, 'shared document');
        $this->storeBackground($map, 'private background', 'image/webp');
        $backgroundPath = $map->fresh()->background_path;
        $map->update([
            'equipment_document_id' => $document->id,
            'source_kind' => InspectionLocationMapSourceKind::ReferenceDocument,
            'source_disk' => $document->disk,
            'source_path' => $document->path,
            'source_mime_type' => $document->mime_type,
            'source_size' => $document->size,
            'source_checksum' => $document->checksum,
        ]);
        app(DeleteInspectionLocationMap::class)->handle($user, $map);

        Storage::disk('equipment_documents')->assertExists($document->path);
        Storage::disk('inspection_maps')->assertMissing($backgroundPath);
        $this->assertNotNull(InspectionLocationMap::withTrashed()->find($map->id));
        $publicPath = rtrim(public_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        foreach (['inspection_maps', 'equipment_documents'] as $disk) {
            $root = rtrim((string) config("filesystems.disks.{$disk}.root"), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $this->assertFalse(str_starts_with($root, $publicPath));
        }
        $this->assertDirectoryDoesNotExist(public_path('inspection-maps'));
    }

    public function test_map_deletion_removes_its_uploaded_source_immediately(): void
    {
        Storage::fake('inspection_maps');
        [, $user, , , $map] = $this->context();
        $sourcePath = $this->mapDirectory($map).'/source.png';
        Storage::disk('inspection_maps')->put($sourcePath, 'uploaded source');
        $this->storeBackground($map, 'private background', 'image/webp');
        $backgroundPath = $map->fresh()->background_path;
        $map->update([
            'source_kind' => InspectionLocationMapSourceKind::Upload,
            'source_disk' => 'inspection_maps',
            'source_path' => $sourcePath,
            'source_mime_type' => 'image/png',
            'source_size' => 15,
            'source_checksum' => hash('sha256', 'uploaded source'),
        ]);

        app(DeleteInspectionLocationMap::class)->handle($user, $map);

        Storage::disk('inspection_maps')->assertMissing($sourcePath);
        Storage::disk('inspection_maps')->assertMissing($backgroundPath);
        $this->assertSoftDeleted('inspection_location_maps', ['id' => $map->id]);
    }

    public function test_report_composition_has_bounded_queries_and_payload(): void
    {
        [, , $inspection, $category, $firstMap] = $this->context();
        $assessment = $this->assessment($inspection, $category);
        InspectionLocationMarker::factory()->forMapAndAssessment($firstMap, $assessment)->create();
        foreach (range(2, 10) as $position) {
            $mapAssessment = $this->assessment($inspection, $category);
            $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
                'position' => $position,
                'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            ]);
            InspectionLocationMarker::factory()->forMapAndAssessment($map, $mapAssessment)->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $report = app(InspectionLocationReportComposer::class)->compose($inspection);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(10, $report['map_count']);
        $this->assertLessThanOrEqual(12, $queryCount);
        $this->assertLessThan(500_000, strlen((string) json_encode($report, JSON_THROW_ON_ERROR)));
    }

    /** @return array{0:Organization,1:User,2:Inspection,3:DefectCategory,4:InspectionLocationMap} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create([
            'responsibility' => InspectionResponsibility::Preparer,
        ]);
        $category = DefectCategory::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'TA',
        ]);
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'position' => 1,
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);

        return [$organization, $user, $inspection, $category, $map];
    }

    private function assessment(Inspection $inspection, DefectCategory $category): DefectAssessment
    {
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'defect_category_id' => $category->id,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->create();
    }

    private function storeBackground(InspectionLocationMap $map, string $contents, string $mimeType): void
    {
        $path = $this->mapDirectory($map).'/background.svg';
        Storage::disk('inspection_maps')->put($path, $contents);
        $map->update([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_disk' => 'inspection_maps',
            'background_path' => $path,
            'background_mime_type' => $mimeType,
            'background_size' => strlen($contents),
            'background_width' => 100,
            'background_height' => 100,
            'background_checksum' => hash('sha256', $contents),
        ]);
    }

    private function mapDirectory(InspectionLocationMap $map): string
    {
        return sprintf(
            'organizations/%d/inspections/%s/maps/%s',
            $map->organization_id,
            $map->inspection->public_id,
            $map->public_id,
        );
    }
}
