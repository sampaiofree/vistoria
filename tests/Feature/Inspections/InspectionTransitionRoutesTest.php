<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\MarkInspectionReportGenerated;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionTransitionRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_assigned_to_every_role_can_run_the_full_transition_flow(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();

        foreach ([
            InspectionResponsibility::Inspector,
            InspectionResponsibility::Preparer,
            InspectionResponsibility::Reviewer,
            InspectionResponsibility::Approver,
            InspectionResponsibility::Releaser,
        ] as $responsibility) {
            $this->assignResponsibility($inspection, $actor, $responsibility);
        }

        $this->actingAs($actor)
            ->post(route('inspections.start', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::InProgress, $inspection->status);
        $this->assertNotNull($inspection->started_at);
        $this->assertNotNull($inspection->inspected_on);

        $this->actingAs($actor)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->status);
        $this->assertNotNull($inspection->field_completed_at);

        $this->actingAs($actor)
            ->post(route('inspections.complete-review', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::AwaitingApproval, $inspection->status);
        $this->assertNotNull($inspection->reviewed_at);

        $this->actingAs($actor)
            ->post(route('inspections.approve', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::Approved, $inspection->status);
        $this->assertNotNull($inspection->approved_at);

        app(TenantContext::class)->set($organization);
        app(MarkInspectionReportGenerated::class)->handle($inspection->refresh(), $actor);

        $inspection->refresh();
        $this->assertSame(InspectionStatus::ReportGenerated, $inspection->status);
        $this->assertNotNull($inspection->report_generated_at);

        $this->actingAs($actor)
            ->post(route('inspections.release', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::Released, $inspection->status);
        $this->assertNotNull($inspection->released_at);
        $this->assertSame(6, $inspection->statusHistories()->count());
    }

    public function test_start_is_blocked_without_inspector_role_or_with_inactive_equipment(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create();

        $inspectionWithoutInspector = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create();

        $this->actingAs($actor)
            ->post(route('inspections.start', $inspectionWithoutInspector))
            ->assertForbidden();

        $inactiveEquipment = Equipment::factory()
            ->inactive()
            ->create(['organization_id' => $organization->id]);
        $inspectionWithInactiveEquipment = Inspection::factory()
            ->forEquipment($inactiveEquipment)
            ->create();

        $this->assignResponsibility(
            $inspectionWithInactiveEquipment,
            $actor,
            InspectionResponsibility::Inspector,
        );

        $this->actingAs($actor)
            ->post(route('inspections.start', $inspectionWithInactiveEquipment))
            ->assertForbidden();
    }

    public function test_submit_for_review_is_blocked_without_reviewer_assignment(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create();
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InProgress]);

        $this->assignResponsibility($inspection, $actor, InspectionResponsibility::Preparer);

        $this->actingAs($actor)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertForbidden();
    }

    public function test_return_and_cancel_require_justification(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create();

        $inspectionForCorrection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::AwaitingReview]);

        $this->assignResponsibility($inspectionForCorrection, $actor, InspectionResponsibility::Reviewer);

        $this->actingAs($actor)
            ->post(route('inspections.return-for-correction', $inspectionForCorrection), [])
            ->assertSessionHasErrors('justification');

        $this->actingAs($actor)
            ->post(route('inspections.return-for-correction', $inspectionForCorrection), [
                'justification' => 'Revisar pontos de segurança adicionais.',
            ])
            ->assertRedirect();

        $inspectionForCorrection->refresh();
        $this->assertSame(InspectionStatus::InCorrection, $inspectionForCorrection->status);

        $inspectionToCancel = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create();

        $this->assignResponsibility($inspectionToCancel, $actor, InspectionResponsibility::Inspector);

        $this->actingAs($actor)
            ->post(route('inspections.cancel', $inspectionToCancel), [])
            ->assertSessionHasErrors('justification');

        $this->actingAs($actor)
            ->post(route('inspections.cancel', $inspectionToCancel), [
                'justification' => 'Cancelamento solicitado pelo time responsável.',
            ])
            ->assertRedirect();

        $inspectionToCancel->refresh();
        $this->assertSame(InspectionStatus::Canceled, $inspectionToCancel->status);
    }

    private function assignResponsibility(
        Inspection $inspection,
        User $user,
        InspectionResponsibility $responsibility,
        bool $isPrimary = false,
    ): InspectionResponsible {
        return InspectionResponsible::factory()
            ->forInspection($inspection, $user)
            ->create([
                'responsibility' => $responsibility,
                'is_primary' => $isPrimary,
            ]);
    }
}
