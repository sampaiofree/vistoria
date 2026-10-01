<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionTechnicalReferencesCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_forward_transition_requires_both_references(): void
    {
        foreach ([
            [InspectionStatus::InProgress, 'inspector', 'inspections.submit-for-planning', InspectionStatus::AwaitingM2],
            [InspectionStatus::InCorrection, 'inspector', 'inspections.submit-for-planning', InspectionStatus::AwaitingM2],
            [InspectionStatus::AwaitingM2, 'planner', 'inspections.submit-for-review', InspectionStatus::AwaitingReview],
            [InspectionStatus::InReview, 'reviewer', 'inspections.approve', InspectionStatus::AwaitingRelease],
            [InspectionStatus::AwaitingRelease, 'releaser', 'inspections.release', InspectionStatus::Released],
        ] as [$status, $role, $route, $nextStatus]) {
            [$inspection, $team] = $this->scenario($status);
            $url = route($route, $inspection);

            $this->actingAs($team[$role])->post($url)->assertSessionHasErrors('inspection');
            $this->assertStringContainsString('DESENHO GERAL', session('errors')->first('inspection'));
            $this->assertStringContainsString('PROC. INSPEÇÃO', session('errors')->first('inspection'));
            $this->assertSame($status, $inspection->fresh()->status);

            $inspection->update(['general_drawing' => 'D-10']);
            $this->actingAs($team[$role])->post($url)->assertSessionHasErrors('inspection');
            $this->assertStringNotContainsString('DESENHO GERAL', session('errors')->first('inspection'));
            $this->assertStringContainsString('PROC. INSPEÇÃO', session('errors')->first('inspection'));
            $this->assertSame($status, $inspection->fresh()->status);

            $inspection->update(['procedure_number' => 'P-10']);
            $this->actingAs($team[$role])->post($url)->assertSessionHasNoErrors();
            $this->assertSame($nextStatus, $inspection->fresh()->status);
        }
    }

    public function test_whitespace_is_missing_and_a_reviewer_cannot_approve_after_clearing_a_reference(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::InReview);
        $inspection->update(['general_drawing' => 'D-20', 'procedure_number' => 'P-20']);

        $this->actingAs($team['reviewer'])->put(route('inspections.technical-references.update', $inspection), [
            'general_drawing' => '  ',
            'procedure_number' => 'P-20',
        ])->assertSessionHasNoErrors();
        $this->assertNull($inspection->fresh()->general_drawing);

        $this->actingAs($team['reviewer'])->post(route('inspections.approve', $inspection))
            ->assertSessionHasErrors('inspection');
        $this->assertStringContainsString('DESENHO GERAL', session('errors')->first('inspection'));
        $this->assertSame(InspectionStatus::InReview, $inspection->fresh()->status);

        $inspection->update(['general_drawing' => " \t ", 'procedure_number' => "\n "]);
        $this->actingAs($team['reviewer'])->post(route('inspections.approve', $inspection))
            ->assertSessionHasErrors('inspection');
        $this->assertStringContainsString('DESENHO GERAL', session('errors')->first('inspection'));
        $this->assertStringContainsString('PROC. INSPEÇÃO', session('errors')->first('inspection'));
    }

    /** @return array{Inspection, array<string, User>} */
    private function scenario(InspectionStatus $status): array
    {
        $organization = Organization::factory()->create();
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => $status, 'general_notes' => 'Aspectos gerais preenchidos.']);
        $team = [];

        foreach ([
            'planner' => [OperationalRole::Planner, InspectionResponsibility::Preparer],
            'inspector' => [OperationalRole::Inspector, InspectionResponsibility::Reviewer],
            'reviewer' => [OperationalRole::Reviewer, InspectionResponsibility::Approver],
            'releaser' => [OperationalRole::Releaser, InspectionResponsibility::Releaser],
        ] as $name => [$role, $responsibility]) {
            $team[$name] = User::factory()->for($organization)->create(['operational_role' => $role]);
            InspectionResponsible::factory()->forInspection($inspection, $team[$name])->create(['responsibility' => $responsibility]);
        }

        foreach ([1, 2] as $position) {
            $block = InspectionOverviewBlock::factory()->forInspection($inspection, $position)->create();
            foreach ([1, 2] as $slot) {
                InspectionOverviewPhoto::factory()->forBlock($block, $slot)->ready()->create();
            }
        }

        return [$inspection, $team];
    }
}
