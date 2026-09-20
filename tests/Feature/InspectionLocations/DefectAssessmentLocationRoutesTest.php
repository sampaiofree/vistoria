<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Jobs\ProcessInspectionLocationMap;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentLocation;
use App\Models\DefectLocationMap;
use App\Models\DefectLocationMapVersion;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class DefectAssessmentLocationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_creates_one_logical_map_and_a_version_for_the_assessment(): void
    {
        Storage::fake('inspection_maps');
        Queue::fake();
        [$user, , $assessment] = $this->context();

        $this->actingAs($user)
            ->post(route('defect-assessments.location-map.store', $assessment), [
                'file' => UploadedFile::fake()->image('mapa.png', 1200, 800),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assessment->refresh();
        $version = $assessment->locationMapVersion;
        $this->assertNotNull($version);
        $this->assertSame($assessment->defect_id, $version->map->defect_id);
        $this->assertSame(1, $version->version);
        Storage::disk('inspection_maps')->assertExists($version->source_path);
        Queue::assertPushed(ProcessInspectionLocationMap::class, fn (ProcessInspectionLocationMap $job): bool => $job->mapVersionId === $version->id);

        $this->actingAs($user)
            ->post(route('defect-assessments.location-map.store', $assessment), [
                'file' => UploadedFile::fake()->image('mapa-2.jpg', 1000, 700),
            ])
            ->assertRedirect();

        $assessment->refresh();
        $this->assertSame(1, DefectLocationMap::query()->where('defect_id', $assessment->defect_id)->count());
        $this->assertSame(2, $assessment->locationMapVersion->version);
        $this->assertSame(1, DefectLocationMapVersion::query()->count(), 'A versão substituída sem referência deve ser eliminada.');
    }

    public function test_replacing_a_published_map_demotes_the_assessment_and_invalidates_confirmation_but_keeps_historical_version(): void
    {
        Storage::fake('inspection_maps');
        Queue::fake();
        [$user, $inspection, $assessment] = $this->context();
        $oldVersion = $this->locateAssessment($assessment);
        $previousInspection = Inspection::factory()->forEquipment($inspection->equipment)->create();
        $historical = DefectAssessment::factory()->forDefect($assessment->defect, $previousInspection)->complete()->create([
            'defect_location_map_version_id' => $oldVersion->id,
        ]);
        $assessment->update([
            'status' => DefectAssessmentStatus::Complete,
            'assessed_at' => now(),
            'defect_snapshot' => ['historical' => true],
        ]);

        $this->actingAs($user)
            ->post(route('defect-assessments.location-map.store', $assessment), [
                'file' => UploadedFile::fake()->image('substituto.png', 900, 600),
            ])
            ->assertRedirect();

        $assessment->refresh()->load(['location', 'locationMapVersion']);
        $this->assertSame(DefectAssessmentStatus::Draft, $assessment->status);
        $this->assertNull($assessment->assessed_at);
        $this->assertFalse($assessment->location->isConfirmed());
        $this->assertNotSame($oldVersion->id, $assessment->defect_location_map_version_id);
        $this->assertDatabaseHas('defect_location_map_versions', ['id' => $oldVersion->id]);
        $this->assertSame($oldVersion->id, $historical->refresh()->defect_location_map_version_id);
    }

    public function test_location_save_confirms_multiple_regions_and_rejects_client_style_and_stale_writes(): void
    {
        [$user, , $assessment] = $this->context();
        $this->locateAssessment($assessment, false);
        $geometry = ['version' => 1, 'shapes' => [
            ['type' => 'point', 'x' => 0.2, 'y' => 0.3],
            ['type' => 'rectangle', 'x' => 0.4, 'y' => 0.4, 'width' => 0.2, 'height' => 0.1],
        ]];

        $this->actingAs($user)
            ->put(route('defect-assessments.location.update', $assessment), [
                'geometry' => $geometry,
                'label' => 'Face norte',
                'lock_version' => 1,
                'color' => '#FF0000',
            ])
            ->assertSessionHasErrors('color');

        $this->actingAs($user)
            ->put(route('defect-assessments.location.update', $assessment), [
                'geometry' => $geometry,
                'label' => 'Face norte',
                'lock_version' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $location = $assessment->location()->firstOrFail();
        $this->assertTrue($location->isConfirmed());
        $this->assertCount(2, $location->geometry['shapes']);
        $this->assertSame(2, $location->lock_version);

        $this->actingAs($user)
            ->put(route('defect-assessments.location.update', $assessment), [
                'geometry' => $geometry,
                'lock_version' => 1,
            ])
            ->assertSessionHasErrors('lock_version');
    }

    public function test_private_background_is_tenant_scoped_and_served_with_private_headers(): void
    {
        Storage::fake('inspection_maps');
        [$user, , $assessment] = $this->context();
        $version = $this->locateAssessment($assessment);
        Storage::disk('inspection_maps')->put($version->background_path, 'private-image');
        Storage::disk('inspection_maps')->put(dirname($version->background_path).'/thumbnail.webp', 'thumbnail');

        $this->actingAs($user)
            ->get(route('defect-location-map-versions.background', $version))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, private');

        $foreign = User::factory()->create();
        $this->actingAs($foreign)
            ->get(route('defect-location-map-versions.background', $version))
            ->assertNotFound();
    }

    public function test_reinspection_inherits_the_version_and_geometry_but_requires_new_confirmation(): void
    {
        [$user, $inspection, $assessment] = $this->context();
        $assessment->update(['status' => DefectAssessmentStatus::Complete, 'assessed_at' => now()]);
        $version = $this->locateAssessment($assessment);
        $nextInspection = Inspection::factory()->reinspection($inspection)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($nextInspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);

        $this->actingAs($user)
            ->post(route('inspections.defects.assessments.store', [$nextInspection, $assessment->defect]), [
                'condition' => DefectAssessmentCondition::Reinspected->value,
                'assessment_action' => DefectAssessmentStatus::Draft->value,
                'comment' => 'Reinspeção pendente de confirmação.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $next = DefectAssessment::query()->where('inspection_id', $nextInspection->id)->firstOrFail();
        $this->assertSame($version->id, $next->defect_location_map_version_id);
        $this->assertSame($assessment->location->geometry, $next->location->geometry);
        $this->assertNull($next->location->confirmed_at);
        $this->assertSame(DefectAssessmentStatus::Draft, $next->status);
    }

    public function test_processor_ignores_stale_jobs_retries_failures_and_never_switches_versions(): void
    {
        Storage::fake('inspection_maps');
        [$user, , $assessment] = $this->context();
        $map = DefectLocationMap::factory()->forDefect($assessment->defect)->create();
        $version = DefectLocationMapVersion::factory()->forMapAndAssessment($map, $assessment)->create([
            'source_disk' => 'inspection_maps',
            'source_mime_type' => 'image/png',
            'source_size' => 12,
            'source_uploaded_by' => $user->id,
        ]);
        $path = sprintf(
            'organizations/%d/defects/%s/maps/%s/versions/%s/source.bin',
            $assessment->organization_id,
            $assessment->defect->public_id,
            $map->public_id,
            $version->public_id,
        );
        Storage::disk('inspection_maps')->put($path, 'not-an-image');
        $checksum = hash('sha256', 'not-an-image');
        $version->update(['source_path' => $path, 'source_checksum' => $checksum]);
        $assessment->update(['defect_location_map_version_id' => $version->id]);

        (new ProcessInspectionLocationMap($version->id, str_repeat('0', 64)))
            ->handle(app(InspectionLocationAssetGuard::class));
        $this->assertSame('pending', $version->refresh()->processing_status->value);

        $job = new ProcessInspectionLocationMap($version->id, $checksum);
        try {
            $job->handle(app(InspectionLocationAssetGuard::class));
            $this->fail('Uma origem inválida deveria ser reenfileirada como falha temporária.');
        } catch (Throwable) {
            $this->assertSame('pending', $version->refresh()->processing_status->value);
            $this->assertNotNull($version->processing_error);
        }

        $job->failed(new RuntimeException('Falha definitiva simulada.'));
        $this->assertSame('failed', $version->refresh()->processing_status->value);
        $this->assertNull($version->source_path);
        Storage::disk('inspection_maps')->assertMissing($path);
        $this->assertSame($version->id, $assessment->refresh()->defect_location_map_version_id);
    }

    public function test_database_enforces_one_map_per_defect_and_one_location_per_assessment(): void
    {
        [, , $assessment] = $this->context();
        $map = DefectLocationMap::factory()->forDefect($assessment->defect)->create();

        try {
            DefectLocationMap::factory()->forDefect($assessment->defect)->create();
            $this->fail('Um segundo mapa lógico para a mesma avaria deveria ser rejeitado.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $version = DefectLocationMapVersion::factory()->forMapAndAssessment($map, $assessment)->ready()->create();
        $assessment->update(['defect_location_map_version_id' => $version->id]);
        DefectAssessmentLocation::factory()->forAssessment($assessment)->create();

        $this->expectException(QueryException::class);
        DefectAssessmentLocation::factory()->forAssessment($assessment)->create();
    }

    /** @return array{User,Inspection,DefectAssessment} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create();

        return [$user, $inspection, $assessment];
    }
}
