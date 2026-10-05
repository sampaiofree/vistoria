<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

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

final class PendingDefectNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_defect_opens_its_details_in_the_current_inspection_without_creating_an_assessment(): void
    {
        [$inspector, , $inspection, $defect, $previousAssessment] = $this->context();
        $url = route('inspections.defects.show', [$inspection, $defect]);

        $this->actingAs($inspector)->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.children', fn ($groups): bool => collect($groups)
                    ->flatMap(fn (array $group) => $group['children'] ?? [])
                    ->firstWhere('key', 'defect-'.$defect->public_id)['href'] === $url));

        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Defects/Show')
            ->where('defect.code', $defect->code)
            ->where('defect.current_assessment', null)
            ->where('defect.previous_assessment.id', $previousAssessment->id)
            ->where('pending_assessment.store_url', route('inspections.defects.assessments.store', [$inspection, $defect]))
            ->where('pending_assessment.condition', 'reinspected')
            ->where('inspection_navigation.inspection.public_id', $inspection->public_id)
            ->where('inspection_navigation.items.2.active', true)
            ->where('back_url', route('inspections.show', $inspection))
            ->where('inspection_url', route('inspections.show', $inspection))
            ->has('assessments', 1));

        $this->assertSame(1, DefectAssessment::query()->where('defect_id', $defect->id)->count());
    }

    public function test_viewer_can_open_pending_details_without_an_assessment_action(): void
    {
        [, $reviewer, $inspection, $defect, $previousAssessment] = $this->context();

        $this->actingAs($reviewer)
            ->get(route('inspections.defects.show', [$inspection, $defect]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Defects/Show')
                ->where('defect.previous_assessment.id', $previousAssessment->id)
                ->where('pending_assessment.store_url', null)
                ->where('defect.related_action_url', null));
    }

    public function test_new_defect_without_an_assessment_has_no_previous_history(): void
    {
        [$inspector, , $inspection] = $this->context();
        $newDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create();

        $this->actingAs($inspector)
            ->get(route('inspections.defects.show', [$inspection, $newDefect]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Defects/Show')
                ->where('defect.previous_assessment', null)
                ->where('pending_assessment.condition', 'new')
                ->has('assessments', 0));
    }

    public function test_assessed_and_historical_defects_keep_their_existing_destinations(): void
    {
        [$inspector, , $inspection, $defect, $previousAssessment] = $this->context();
        $currentAssessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create();

        $this->actingAs($inspector)->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.children', fn ($groups): bool => collect($groups)
                    ->flatMap(fn (array $group) => $group['children'] ?? [])
                    ->firstWhere('key', 'defect-'.$defect->public_id)['href'] === route('defect-assessments.show', $currentAssessment)));

        $this->get(route('inspections.defects.show', [$inspection, $defect]))
            ->assertRedirect(route('defect-assessments.show', $currentAssessment));
        $this->get(route('defects.show', $defect))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Defects/Show')
                ->where('defect.current_assessment.id', $currentAssessment->id)
                ->where('pending_assessment', null));

        $currentAssessment->delete();
        $inspection->update(['reinspection_scope_version' => 1]);
        $inspection->defectScopes()->create([
            'organization_id' => $inspection->organization_id,
            'defect_id' => $defect->id,
            'source_assessment_id' => $previousAssessment->id,
            'requires_reinspection' => false,
        ]);

        $historicalUrl = route('inspections.defects.historical', [$inspection, $defect]);
        $this->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.items.2.children', fn ($groups): bool => collect($groups)
                    ->flatMap(fn (array $group) => $group['children'] ?? [])
                    ->firstWhere('key', 'defect-'.$defect->public_id)['href'] === $historicalUrl));

        $this->get(route('inspections.defects.show', [$inspection, $defect]))
            ->assertRedirect($historicalUrl);
    }

    public function test_defect_outside_the_inspection_scope_cannot_open_the_contextual_details(): void
    {
        [$inspector, , $inspection] = $this->context();
        $otherEquipment = Equipment::factory()->for($inspection->organization)->create();
        $otherInspection = Inspection::factory()->forEquipment($otherEquipment)->create();
        $unrelatedDefect = Defect::factory()->forEquipment($otherEquipment, $otherInspection)->create();

        $this->actingAs($inspector)
            ->get(route('inspections.defects.show', [$inspection, $unrelatedDefect]))
            ->assertNotFound();

        $futureInspection = Inspection::factory()->reinspection($inspection)->create();
        $futureDefect = Defect::factory()->forEquipment($inspection->equipment, $futureInspection)->create();
        $this->get(route('inspections.defects.show', [$inspection, $futureDefect]))
            ->assertNotFound();
    }

    /** @return array{User, User, Inspection, Defect, DefectAssessment} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $reviewer = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $equipment = Equipment::factory()->for($organization)->create();
        $previousInspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'number' => 'INS-001',
        ]);
        $inspection = Inspection::factory()->reinspection($previousInspection)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'INS-002',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)
            ->create(['responsibility' => InspectionResponsibility::Preparer]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)
            ->create(['responsibility' => InspectionResponsibility::Approver]);
        $defect = Defect::factory()->forEquipment($equipment, $previousInspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $previousInspection)->complete()->create();

        return [$inspector, $reviewer, $inspection, $defect, $assessment];
    }
}
