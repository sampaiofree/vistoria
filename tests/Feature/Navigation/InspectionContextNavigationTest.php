<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionContextNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_navigation_is_not_exposed(): void
    {
        [$user, $inspection] = $this->context();

        $this->actingAs($user)
            ->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items', fn ($items): bool => collect($items)->doesntContain('key', 'locations')));
    }

    public function test_assessment_editor_keeps_defects_branch_active(): void
    {
        [$user, , $assessment] = $this->context();
        $this->locateAssessment($assessment);

        $this->actingAs($user)
            ->get(route('defect-assessments.location.editor', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.active', true));

        $this->actingAs($user)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.active', true));
    }

    public function test_classification_page_is_available_in_the_inspection_sidebar_after_defects(): void
    {
        [$user, $inspection] = $this->context();

        $this->actingAs($user)
            ->get(route('inspections.classifications', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.3.key', 'classifications')
                ->where('inspection_navigation.items.3.label', 'Nota M2')
                ->where('inspection_navigation.items.3.active', true));
    }

    public function test_global_pages_do_not_receive_an_inspection_context(): void
    {
        [$user] = $this->context();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('inspection_navigation', null));
    }

    /** @return array{User,Inspection,DefectAssessment} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create([
            'tag' => 'U03-06VT002',
            'normalized_tag' => 'U03-06VT002',
        ]);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
            'started_at' => now(),
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'category' => DefectCategory::Civil,
            'code' => 'VT002-CV-001',
            'sequence_number' => 1,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create();

        return [$user, $inspection, $assessment];
    }
}
