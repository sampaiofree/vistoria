<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\CreateInspection;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionReportRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_is_reserved_sequentially_and_cancellation_keeps_the_gap(): void
    {
        [$organization, $planner, $equipment] = $this->scenario();
        app(TenantContext::class)->set($organization);
        $create = app(CreateInspection::class);
        $first = $create->handle($planner, $equipment, $this->payload());
        $first->update(['status' => InspectionStatus::Canceled]);
        $second = $create->handle($planner, $equipment, $this->payload());
        $otherEquipment = Equipment::factory()->for($organization)->create();
        $other = $create->handle($planner, $otherEquipment, $this->payload());

        $this->assertSame(0, $first->report_revision);
        $this->assertSame(1, $second->report_revision);
        $this->assertSame(0, $other->report_revision);
    }

    public function test_assigned_inspector_or_reviewer_can_change_a_revision_during_their_workflow_stage(): void
    {
        [$organization, $planner, $equipment] = $this->scenario();
        app(TenantContext::class)->set($organization);
        $inspection = app(CreateInspection::class)->handle($planner, $equipment, $this->payload());
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $inspection->update(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);

        $this->actingAs($inspector)
            ->put(route('inspections.report-revision.update', $inspection), ['report_revision' => 4])
            ->assertRedirect(route('inspections.show', $inspection));
        $this->assertSame(4, $inspection->fresh()->report_revision);

        $other = Inspection::factory()->forEquipment($equipment)->create(['report_revision' => 5]);
        $this->actingAs($inspector)
            ->put(route('inspections.report-revision.update', $inspection), ['report_revision' => $other->report_revision])
            ->assertSessionHasErrors('report_revision');

        $inspection->update(['status' => InspectionStatus::AwaitingReview]);
        $this->actingAs($inspector)
            ->put(route('inspections.report-revision.update', $inspection), ['report_revision' => 6])
            ->assertForbidden();

        $reviewer = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create([
            'responsibility' => InspectionResponsibility::Approver,
            'is_primary' => true,
        ]);
        $inspection->update(['status' => InspectionStatus::InReview]);

        $this->actingAs($reviewer)
            ->put(route('inspections.report-revision.update', $inspection), ['report_revision' => 6])
            ->assertRedirect(route('inspections.show', $inspection));
        $this->assertSame(6, $inspection->fresh()->report_revision);
    }

    /** @return array{Organization, User, Equipment} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $equipment = Equipment::factory()->for($organization)->create();

        return [$organization, $planner, $equipment];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['planned_start_on' => '2026-10-10', 'planned_end_on' => '2026-10-11'];
    }
}
