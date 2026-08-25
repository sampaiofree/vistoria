<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionLocationMapSourceKind;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\AssessmentPhoto;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CopyInspectionLocationMapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_creates_new_records_resolves_current_assessment_and_preserves_history(): void
    {
        Storage::fake('inspection_maps');
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $equipment = Equipment::factory()->for($organization)->create();
        $previous = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $current = Inspection::factory()->reinspection($previous)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($current, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'TA']);

        $resolvedDefect = Defect::factory()->forEquipment($equipment, $previous)->create(['defect_category_id' => $category->id]);
        $pendingDefect = Defect::factory()->forEquipment($equipment, $previous)->create(['defect_category_id' => $category->id]);
        $previousResolved = DefectAssessment::factory()->forDefect($resolvedDefect, $previous)->create();
        $previousPending = DefectAssessment::factory()->forDefect($pendingDefect, $previous)->create();
        $currentAssessment = DefectAssessment::factory()->forDefect($resolvedDefect, $current)->create();
        $previousMap = InspectionLocationMap::factory()->forInspection($previous, $category)->create(['processing_status' => InspectionLocationMapProcessingStatus::Ready, 'title' => 'Mapa histórico']);
        $backgroundPath = sprintf(
            'organizations/%d/inspections/%s/maps/%s/derivatives/history/background.webp',
            $organization->id,
            $previous->public_id,
            $previousMap->public_id,
        );
        Storage::disk('inspection_maps')->put($backgroundPath, 'processed background');
        $previousMap->update([
            'background_disk' => 'inspection_maps',
            'background_path' => $backgroundPath,
            'background_mime_type' => 'image/webp',
            'background_size' => strlen('processed background'),
            'background_checksum' => hash('sha256', 'processed background'),
        ]);
        $resolvedMarker = InspectionLocationMarker::factory()->forMapAndAssessment($previousMap, $previousResolved)->create([
            'label' => 'Resolvida',
            'style' => ['stroke' => 'none', 'fill' => '#7C3AED', 'stroke_width' => 0.005, 'opacity' => 0.9, 'dashed' => false],
        ]);
        $pendingMarker = InspectionLocationMarker::factory()->forMapAndAssessment($previousMap, $previousPending)->create(['label' => 'Pendente', 'position' => 2]);
        $photo = AssessmentPhoto::factory()->create(['organization_id' => $organization->id, 'inspection_id' => $previous->id, 'defect_assessment_id' => $previousResolved->id]);
        $resolvedMarker->photos()->attach($photo->id, ['organization_id' => $organization->id, 'inspection_id' => $previous->id, 'position' => 1]);

        $this->actingAs($user)
            ->post(route('inspections.location-maps.copy-previous', $current))
            ->assertRedirect(route('inspections.locations', $current));

        $copiedMap = InspectionLocationMap::query()->where('inspection_id', $current->id)->firstOrFail();
        $copiedMarkers = $copiedMap->markers()->orderBy('position')->get();

        $this->assertNotSame($previousMap->public_id, $copiedMap->public_id);
        $this->assertSame(InspectionLocationMapSourceKind::Upload, $copiedMap->source_kind);
        $this->assertNull($copiedMap->equipment_document_id);
        $this->assertNotNull($copiedMap->source_path);
        Storage::disk('inspection_maps')->assertExists($copiedMap->source_path);
        $this->assertNotSame($resolvedMarker->public_id, $copiedMarkers[0]->public_id);
        $this->assertSame($currentAssessment->id, $copiedMarkers[0]->defect_assessment_id);
        $this->assertSame($resolvedMarker->style, $copiedMarkers[0]->style);
        $this->assertNull($copiedMarkers[1]->defect_assessment_id);
        $this->assertCount(0, $copiedMarkers[0]->photos);
        $this->assertDatabaseHas('inspection_location_maps', ['id' => $previousMap->id, 'inspection_id' => $previous->id]);
        $this->assertDatabaseHas('inspection_location_markers', ['id' => $pendingMarker->id, 'defect_assessment_id' => $previousPending->id]);
        $this->assertCount(1, $resolvedMarker->fresh()->photos);

        $this->actingAs($user)
            ->post(route('inspections.location-maps.copy-previous', $current))
            ->assertSessionHasErrors('maps');
        $this->assertSame(1, InspectionLocationMap::query()->where('inspection_id', $current->id)->count());
        $this->assertSame(2, InspectionLocationMarker::query()->where('inspection_id', $current->id)->count());
    }
}
