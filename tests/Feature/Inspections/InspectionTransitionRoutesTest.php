<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionTransitionRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_operational_roles_can_run_the_approved_transition_flow(): void
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $reviewer = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Reviewer,
        ]);
        $releaser = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Releaser,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();

        $this->assignResponsibility($inspection, $inspector, InspectionResponsibility::Reviewer);
        $this->assignResponsibility($inspection, $reviewer, InspectionResponsibility::Approver);
        $this->assignResponsibility($inspection, $releaser, InspectionResponsibility::Releaser);

        $this->actingAs($inspector)
            ->post(route('inspections.start', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::InProgress, $inspection->status);
        $this->assertNotNull($inspection->started_at);
        $this->assertNotNull($inspection->inspected_on);

        $this->actingAs($inspector)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->status);
        $this->assertNotNull($inspection->field_completed_at);

        $this->actingAs($reviewer)
            ->post(route('inspections.start-review', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::InReview, $inspection->status);

        $this->actingAs($reviewer)
            ->post(route('inspections.approve', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::AwaitingRelease, $inspection->status);
        $this->assertNotNull($inspection->approved_at);

        $this->actingAs($releaser)
            ->post(route('inspections.release', $inspection))
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame(InspectionStatus::Released, $inspection->status);
        $this->assertNotNull($inspection->released_at);
        $this->assertSame(5, $inspection->statusHistories()->count());
    }

    public function test_start_requires_an_assigned_inspector_and_an_active_equipment(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);

        $inspectionWithoutPreparer = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create();

        $this->actingAs($actor)
            ->post(route('inspections.start', $inspectionWithoutPreparer))
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
            InspectionResponsibility::Preparer,
        );

        $this->actingAs($actor)
            ->post(route('inspections.start', $inspectionWithInactiveEquipment))
            ->assertForbidden();
    }

    public function test_assigned_inspector_can_start_regardless_of_technical_responsibility(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create();

        $this->assignResponsibility($inspection, $actor, InspectionResponsibility::Reviewer);

        $this->actingAs($actor)
            ->post(route('inspections.start', $inspection))
            ->assertRedirect();

        $this->assertSame(InspectionStatus::InProgress, $inspection->fresh()->status);
    }

    public function test_assigned_non_inspector_cannot_start_an_inspection(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Planner,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create();

        $this->assignResponsibility($inspection, $actor, InspectionResponsibility::Reviewer);

        $this->actingAs($actor)
            ->post(route('inspections.start', $inspection))
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

    public function test_in_progress_transitions_require_an_assigned_inspector(): void
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $admin = User::factory()->for($organization)->create([
            'account_type' => \App\Enums\UserAccountType::CompanyAdmin,
            'operational_role' => OperationalRole::Planner,
        ]);
        $unassignedInspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InProgress]);

        $this->assignResponsibility($inspection, $inspector, InspectionResponsibility::Reviewer);
        $this->assignResponsibility($inspection, $admin, InspectionResponsibility::Preparer);

        $this->assertFalse($admin->can('submitForReview', $inspection));
        $this->assertFalse($unassignedInspector->can('submitForReview', $inspection));

        foreach ([$admin, $unassignedInspector] as $user) {
            $this->actingAs($user)
                ->post(route('inspections.submit-for-review', $inspection))
                ->assertForbidden();
        }

        $this->actingAs($inspector)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertRedirect();
        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->fresh()->status);

        $inspection->refresh()->update(['status' => InspectionStatus::InProgress]);

        $this->assertFalse($admin->can('cancel', $inspection));
        $this->actingAs($admin)
            ->post(route('inspections.cancel', $inspection), ['justification' => 'Não autorizado.'])
            ->assertForbidden();

        $this->actingAs($inspector)
            ->post(route('inspections.cancel', $inspection), ['justification' => 'Cancelamento necessário.'])
            ->assertRedirect();
        $this->assertSame(InspectionStatus::Canceled, $inspection->fresh()->status);
    }

    public function test_return_and_cancel_require_justification(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Reviewer,
        ]);

        $inspectionForCorrection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview]);

        $this->assignResponsibility($inspectionForCorrection, $actor, InspectionResponsibility::Approver);

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

        $this->assignResponsibility($inspectionToCancel, $actor, InspectionResponsibility::Approver);

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

    public function test_releaser_can_return_an_inspection_for_a_new_review_with_justification(): void
    {
        $organization = Organization::factory()->create();
        $releaser = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Releaser,
        ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::AwaitingRelease]);

        $this->assignResponsibility($inspection, $releaser, InspectionResponsibility::Releaser);

        $this->actingAs($releaser)
            ->post(route('inspections.return-for-review', $inspection), [])
            ->assertSessionHasErrors('justification');

        $this->actingAs($releaser)
            ->post(route('inspections.return-for-review', $inspection), [
                'justification' => 'A revisão precisa corrigir a consistência do relatório.',
            ])
            ->assertRedirect();

        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->fresh()->status);
    }

    private function assignResponsibility(
        Inspection $inspection,
        User $user,
        InspectionResponsibility $responsibility,
        bool $isPrimary = true,
    ): InspectionResponsible {
        return InspectionResponsible::factory()
            ->forInspection($inspection, $user)
            ->create([
                'responsibility' => $responsibility,
                'is_primary' => $isPrimary,
            ]);
    }
}
