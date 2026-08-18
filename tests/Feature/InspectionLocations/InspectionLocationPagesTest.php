<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
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
use App\Services\InspectionLocations\InspectionLocationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionLocationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_index_uses_real_flat_read_model(): void
    {
        [$organization, $user, $inspection] = $this->context();
        $civil = DefectCategory::query()->where('organization_id', $organization->id)->where('code', 'CV')->firstOrFail();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'TA', 'position' => 2]);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $secondDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $located = DefectAssessment::factory()->forDefect($defect, $inspection)->create();
        $unlocated = DefectAssessment::factory()->forDefect($secondDefect, $inspection)->create();
        $firstMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create(['title' => 'Mapa 2', 'position' => 2]);
        $secondMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create(['title' => 'Mapa 1', 'position' => 1]);
        InspectionLocationMarker::factory()->forMapAndAssessment($secondMap, $located)->create();

        $otherOrganization = Organization::factory()->create();
        $otherInspection = Inspection::factory()->forEquipment(Equipment::factory()->for($otherOrganization)->create())->create();
        $otherCategory = DefectCategory::query()->where('organization_id', $otherOrganization->id)->firstOrFail();
        InspectionLocationMap::factory()->forInspection($otherInspection, $otherCategory)->create(['title' => 'Mapa estrangeiro']);

        $payload = app(InspectionLocationPresenter::class)->present($inspection, $user);
        $group = collect($payload['categories'])->firstWhere('category.id', $category->id);

        $this->assertSame($civil->id, $payload['categories'][0]['category']['id']);
        $this->assertSame(['Mapa 1', 'Mapa 2'], collect($group['maps'])->pluck('title')->all());
        $this->assertSame([$unlocated->id], collect($group['unlocated_assessments'])->pluck('id')->all());
        $this->assertFalse(collect($payload['categories'])->flatMap(fn (array $item): array => $item['maps'])->contains('title', 'Mapa estrangeiro'));
        $this->assertTrue($group['capabilities']['create']);
        $this->assertSame(['Mapa 1', 'Mapa 2'], collect($payload['maps'])->pluck('title')->all());
        $this->assertSame([$unlocated->id], collect($payload['unlocated_assessments'])->pluck('id')->all());
        $this->assertSame('TA', $payload['maps'][0]['category']['code']);

        $this->actingAs($user)
            ->get(route('inspections.locations', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspectionLocationMaps/Index')
                ->where('active_tab', 'locations')
                ->has('categories', 2)
                ->has('maps', 2)
                ->has('unlocated_assessments', 1));
    }

    public function test_create_and_edit_pages_are_available_to_responsible_user(): void
    {
        [, $user, $inspection] = $this->context();
        $category = DefectCategory::query()->where('organization_id', $inspection->organization_id)->firstOrFail();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create();

        $this->actingAs($user)
            ->get(route('inspections.location-maps.create', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('InspectionLocationMaps/Create')->has('categories'));

        $this->actingAs($user)
            ->get(route('inspection-location-maps.edit', $map))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspectionLocationMaps/Edit')
                ->where('map.public_id', $map->public_id));
    }

    public function test_editor_exposes_the_same_canonical_numbers_used_by_the_report(): void
    {
        [$organization, $user, $inspection] = $this->context();
        $category = DefectCategory::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'TAC',
            'name' => 'TAC',
        ]);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'defect_category_id' => $category->id,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_path' => 'maps/background.webp',
        ]);
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();
        $second = AssessmentPhoto::factory()->for($inspection)->for($assessment, 'assessment')->ready()->create([
            'organization_id' => $organization->id,
            'position' => 2,
        ]);
        $first = AssessmentPhoto::factory()->for($inspection)->for($assessment, 'assessment')->ready()->create([
            'organization_id' => $organization->id,
            'position' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('inspection-location-maps.editor', $map))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessments.0.photos.0.public_id', $first->public_id)
                ->where('assessments.0.photos.0.report_number', 5)
                ->where('assessments.0.photos.1.public_id', $second->public_id)
                ->where('assessments.0.photos.1.report_number', 6)
                ->where('assessments.0.photo_numbers', [5, 6])
                ->where('assessments.0.photo_legend', 'FOTOS: 5 E 6'));
    }

    /** @return array{0:Organization,1:User,2:Inspection} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);

        return [$organization, $user, $inspection];
    }
}
