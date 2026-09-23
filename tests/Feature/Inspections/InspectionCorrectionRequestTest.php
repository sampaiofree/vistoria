<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionCorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_marks_a_defect_and_inspector_must_address_it_before_resubmitting(): void
    {
        [$inspection, $reviewer, $inspector] = $this->inspectionWithReviewerAndInspector(InspectionStatus::InReview);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create();

        $this->actingAs($reviewer)
            ->post(route('defect-assessment-correction-requests.store', $assessment), [
                'request_message' => 'Publique a avaliação e complete a evidência desta avaria.',
            ])
            ->assertRedirect();

        $request = InspectionCorrectionRequest::query()->sole();
        $this->assertSame(InspectionCorrectionRequestStatus::Marked, $request->status);

        $this->actingAs($reviewer)
            ->get(route('inspections.defects', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.items.0.correction_requests.items.0.status', InspectionCorrectionRequestStatus::Marked->value)
                ->where('content.items.0.has_pending_correction_for_current_user', true)
                ->where('content.filters.pending_for_current_user_count', 1)
                ->has('content.items.0.correction_requests.history', 0));

        $this->actingAs($reviewer)
            ->get(route('defect-assessments.show', $assessment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('correction_requests.items.0.status', InspectionCorrectionRequestStatus::Marked->value)
                ->has('correction_requests.history', 0));

        $this->actingAs($reviewer)
            ->post(route('inspections.return-for-correction', $inspection))
            ->assertRedirect();

        $this->assertSame(InspectionStatus::InCorrection, $inspection->fresh()->status);
        $request->refresh();
        $this->assertSame(InspectionCorrectionRequestStatus::Requested, $request->status);
        $this->assertNotNull($request->sent_at);

        $this->actingAs($inspector)
            ->get(route('inspections.defects', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.items.0.has_pending_correction_for_current_user', true)
                ->where('content.filters.pending_for_current_user_count', 1));

        $this->actingAs($inspector)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertSessionHasErrors('inspection');

        $this->actingAs($inspector)
            ->patch(route('inspection-correction-requests.address', $request), [
                'response_message' => 'Ainda está em rascunho.',
            ])
            ->assertSessionHasErrors('request');

        $assessment->update(['status' => DefectAssessmentStatus::Complete, 'assessed_at' => now()]);

        $this->actingAs($inspector)
            ->patch(route('inspection-correction-requests.address', $request), [
                'response_message' => 'Avaliação publicada e evidência revisada.',
            ])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(InspectionCorrectionRequestStatus::Addressed, $request->status);
        $this->assertSame('Avaliação publicada e evidência revisada.', $request->response_message);

        $this->actingAs($inspector)
            ->patch(route('inspection-correction-requests.mark-pending', $request))
            ->assertRedirect();

        $this->assertSame(InspectionCorrectionRequestStatus::Requested, $request->fresh()->status);
    }

    public function test_general_message_is_enough_to_return_for_correction_and_keeps_a_chronological_record(): void
    {
        [$inspection, $reviewer] = $this->inspectionWithReviewerAndInspector(InspectionStatus::InReview);

        $this->actingAs($reviewer)
            ->post(route('inspections.return-for-correction', $inspection), [
                'justification' => 'Revisar a consistência geral das conclusões técnicas.',
            ])
            ->assertRedirect();

        $request = InspectionCorrectionRequest::query()->sole();
        $this->assertNull($request->defect_assessment_id);
        $this->assertSame(InspectionCorrectionRequestStatus::Requested, $request->status);
        $this->assertSame('Revisar a consistência geral das conclusões técnicas.', $request->request_message);

        $this->assertDatabaseHas('inspection_correction_requests', [
            'inspection_id' => $inspection->id,
            'status' => InspectionCorrectionRequestStatus::Requested->value,
        ]);
    }

    public function test_correction_request_payload_separates_open_requests_from_history_for_defects_and_general_requests(): void
    {
        [$inspection, $reviewer] = $this->inspectionWithReviewerAndInspector(InspectionStatus::InReview);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();

        foreach (InspectionCorrectionRequestStatus::cases() as $status) {
            InspectionCorrectionRequest::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'defect_assessment_id' => $assessment->id,
                'flow' => InspectionCorrectionRequestFlow::ReviewerToInspector,
                'status' => $status,
                'request_message' => "Solicitação da avaria {$status->value}.",
                'created_by' => $reviewer->id,
                'updated_by' => $reviewer->id,
            ]);
            InspectionCorrectionRequest::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'flow' => InspectionCorrectionRequestFlow::ReviewerToInspector,
                'status' => $status,
                'request_message' => "Solicitação geral {$status->value}.",
                'created_by' => $reviewer->id,
                'updated_by' => $reviewer->id,
            ]);
        }

        $this->actingAs($reviewer)
            ->get(route('inspections.defects', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->has('content.items.0.correction_requests.items', 3)
                ->has('content.items.0.correction_requests.history', 2)
                ->where('content.items.0.correction_requests.counts.open', 3)
                ->where('content.items.0.correction_requests.counts.history', 2));

        $this->actingAs($reviewer)
            ->get(route('inspections.show', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->has('content.general_correction_requests.items', 3)
                ->has('content.general_correction_requests.history', 2)
                ->where('content.general_correction_requests.counts.open', 3)
                ->where('content.general_correction_requests.counts.history', 2));
    }

    public function test_reviewer_can_edit_or_remove_only_a_marked_defect_request(): void
    {
        [$inspection, $reviewer] = $this->inspectionWithReviewerAndInspector(InspectionStatus::InReview);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();

        $this->actingAs($reviewer)
            ->post(route('defect-assessment-correction-requests.store', $assessment), [
                'request_message' => 'Atualize a recomendação técnica desta avaria.',
            ])
            ->assertRedirect();

        $request = InspectionCorrectionRequest::query()->sole();

        $this->actingAs($reviewer)
            ->patch(route('inspection-correction-requests.update', $request), [
                'request_message' => 'Atualize a recomendação técnica e a fotografia desta avaria.',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Atualize a recomendação técnica e a fotografia desta avaria.',
            $request->fresh()->request_message,
        );

        $this->actingAs($reviewer)
            ->delete(route('inspection-correction-requests.destroy', $request))
            ->assertRedirect();

        $this->assertDatabaseMissing('inspection_correction_requests', ['id' => $request->id]);
    }

    public function test_new_adjustment_supersedes_the_addressed_request_and_preserves_its_history(): void
    {
        [$inspection, $reviewer, $inspector] = $this->inspectionWithReviewerAndInspector(InspectionStatus::InReview);

        $this->actingAs($reviewer)
            ->post(route('inspections.return-for-correction', $inspection), [
                'justification' => 'Ajustar a conclusão geral antes da liberação.',
            ])
            ->assertRedirect();

        $first = InspectionCorrectionRequest::query()->sole();
        $this->actingAs($inspector)
            ->patch(route('inspection-correction-requests.address', $first), [
                'response_message' => 'Conclusão geral atualizada.',
            ])
            ->assertRedirect();
        $this->actingAs($inspector)->post(route('inspections.submit-for-review', $inspection))->assertRedirect();
        $this->actingAs($reviewer)->post(route('inspections.start-review', $inspection))->assertRedirect();

        $this->actingAs($reviewer)
            ->post(route('inspection-correction-requests.replace', $first), [
                'request_message' => 'Ajustar também a justificativa da conclusão geral.',
            ])
            ->assertRedirect();

        $first->refresh();
        $replacement = InspectionCorrectionRequest::query()->where('previous_request_id', $first->id)->sole();
        $this->assertSame(InspectionCorrectionRequestStatus::Superseded, $first->status);
        $this->assertSame(InspectionCorrectionRequestStatus::Marked, $replacement->status);
        $this->assertSame($first->id, $replacement->previous_request_id);

        $this->actingAs($reviewer)
            ->post(route('inspections.return-for-correction', $inspection))
            ->assertRedirect();

        $this->assertSame(InspectionCorrectionRequestStatus::Requested, $replacement->fresh()->status);
        $this->assertSame(InspectionStatus::InCorrection, $inspection->fresh()->status);
    }

    public function test_requests_must_be_closed_before_release_and_only_assigned_roles_can_act(): void
    {
        [$inspection, $reviewer, $inspector] = $this->inspectionWithReviewerAndInspector(InspectionStatus::InReview);
        $otherReviewer = User::factory()->for($inspection->organization)->create([
            'operational_role' => OperationalRole::Reviewer,
        ]);

        $this->actingAs($reviewer)
            ->post(route('inspections.return-for-correction', $inspection), [
                'justification' => 'Ajustar o texto do resumo técnico antes da liberação.',
            ])
            ->assertRedirect();

        $request = InspectionCorrectionRequest::query()->sole();

        $this->actingAs($otherReviewer)
            ->patch(route('inspection-correction-requests.address', $request))
            ->assertForbidden();

        $this->actingAs($inspector)
            ->patch(route('inspection-correction-requests.address', $request), [
                'response_message' => 'Resumo técnico ajustado.',
            ])
            ->assertRedirect();

        $this->actingAs($inspector)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertRedirect();

        $this->actingAs($reviewer)
            ->post(route('inspections.start-review', $inspection))
            ->assertRedirect();

        $this->actingAs($reviewer)
            ->post(route('inspections.approve', $inspection))
            ->assertSessionHasErrors('inspection');

        $this->actingAs($reviewer)
            ->post(route('inspections.return-for-correction', $inspection), [
                'justification' => 'Ainda há uma solicitação atendida para conferência.',
            ])
            ->assertSessionHasErrors('inspection');

        $this->actingAs($reviewer)
            ->patch(route('inspection-correction-requests.close', $request))
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(InspectionCorrectionRequestStatus::Closed, $request->status);

        $this->actingAs($reviewer)
            ->post(route('inspections.approve', $inspection))
            ->assertRedirect();

        $this->assertSame(InspectionStatus::AwaitingRelease, $inspection->fresh()->status);
    }

    public function test_releaser_can_request_a_review_and_must_close_each_addressed_request_before_release(): void
    {
        [$inspection, $reviewer, $inspector, $releaser] = $this->inspectionWithReleaseTeam(InspectionStatus::AwaitingRelease);
        $inspection->update(['approved_at' => now()]);

        $this->actingAs($releaser)
            ->post(route('inspections.return-for-review', $inspection), [
                'justification' => 'Revisar a consistência das conclusões antes da liberação.',
            ])
            ->assertRedirect();

        $parent = InspectionCorrectionRequest::query()->sole();
        $this->assertSame(InspectionCorrectionRequestFlow::ReleaserToReviewer, $parent->flow);
        $this->assertSame(InspectionCorrectionRequestStatus::Requested, $parent->status);
        $this->assertNull($inspection->fresh()->approved_at);

        $this->actingAs($reviewer)->post(route('inspections.start-review', $inspection))->assertRedirect();
        $this->actingAs($reviewer)
            ->patch(route('inspection-correction-requests.address', $parent), [
                'response_message' => 'Conclusões revisadas e consistência confirmada.',
            ])
            ->assertRedirect();
        $this->assertSame(InspectionCorrectionRequestStatus::Addressed, $parent->fresh()->status);

        $this->actingAs($reviewer)->post(route('inspections.approve', $inspection))->assertRedirect();
        $this->assertSame(InspectionStatus::AwaitingRelease, $inspection->fresh()->status);

        $this->actingAs($releaser)->post(route('inspections.release', $inspection))->assertSessionHasErrors('inspection');
        $this->actingAs($releaser)->patch(route('inspection-correction-requests.close', $parent))->assertRedirect();
        $this->actingAs($releaser)->post(route('inspections.release', $inspection))->assertRedirect();
        $this->assertSame(InspectionStatus::Released, $inspection->fresh()->status);
    }

    public function test_releaser_can_mark_an_assessment_without_a_general_message_before_returning_for_review(): void
    {
        [$inspection, , , $releaser] = $this->inspectionWithReleaseTeam(InspectionStatus::AwaitingRelease);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();

        $this->actingAs($releaser)
            ->post(route('defect-assessment-correction-requests.store', $assessment), [
                'request_message' => 'Confira a consistência técnica desta avaria antes de liberar.',
            ])
            ->assertRedirect();

        $request = InspectionCorrectionRequest::query()->sole();
        $this->assertSame(InspectionCorrectionRequestFlow::ReleaserToReviewer, $request->flow);
        $this->assertSame(InspectionCorrectionRequestStatus::Marked, $request->status);

        $this->actingAs($releaser)
            ->post(route('inspections.return-for-review', $inspection))
            ->assertRedirect();

        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->fresh()->status);
        $this->assertSame(InspectionCorrectionRequestStatus::Requested, $request->fresh()->status);
    }

    public function test_reviewer_can_delegate_a_releaser_request_to_inspector_and_must_validate_the_child_before_responding(): void
    {
        [$inspection, $reviewer, $inspector, $releaser] = $this->inspectionWithReleaseTeam(InspectionStatus::AwaitingRelease);

        $this->actingAs($releaser)
            ->post(route('inspections.return-for-review', $inspection), [
                'justification' => 'Ajustar o texto técnico para a liberação.',
            ])
            ->assertRedirect();
        $parent = InspectionCorrectionRequest::query()->sole();

        $this->actingAs($reviewer)->post(route('inspections.start-review', $inspection))->assertRedirect();
        $this->actingAs($reviewer)
            ->post(route('inspection-correction-requests.children.store', $parent), [
                'request_message' => 'Atualize o texto técnico e confira as evidências.',
            ])
            ->assertRedirect();

        $child = InspectionCorrectionRequest::query()->where('parent_request_id', $parent->id)->sole();
        $this->assertSame(InspectionCorrectionRequestFlow::ReviewerToInspector, $child->flow);
        $this->assertSame($parent->id, $child->parent_request_id);

        $this->actingAs($reviewer)
            ->patch(route('inspection-correction-requests.address', $parent), ['response_message' => 'Ainda em análise.'])
            ->assertSessionHasErrors('request');
        $this->actingAs($reviewer)->post(route('inspections.return-for-correction', $inspection))->assertRedirect();
        $this->assertSame(InspectionStatus::InCorrection, $inspection->fresh()->status);

        $this->actingAs($inspector)
            ->patch(route('inspection-correction-requests.address', $child), ['response_message' => 'Texto e evidências atualizados.'])
            ->assertRedirect();
        $this->actingAs($inspector)->post(route('inspections.submit-for-review', $inspection))->assertRedirect();
        $this->actingAs($reviewer)->post(route('inspections.start-review', $inspection))->assertRedirect();

        $this->actingAs($reviewer)->patch(route('inspection-correction-requests.close', $child))->assertRedirect();
        $this->actingAs($reviewer)
            ->patch(route('inspection-correction-requests.address', $parent), ['response_message' => 'Ajuste validado pelo Revisor.'])
            ->assertRedirect();
    }

    /** @return array{Inspection, User, User} */
    private function inspectionWithReviewerAndInspector(InspectionStatus $status): array
    {
        $organization = Organization::factory()->create();
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => $status]);
        $reviewer = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Reviewer,
        ]);
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);

        $this->assign($inspection, $reviewer, InspectionResponsibility::Approver);
        $this->assign($inspection, $inspector, InspectionResponsibility::Reviewer);
        $this->completeOverview($inspection);

        return [$inspection, $reviewer, $inspector];
    }

    private function assign(Inspection $inspection, User $user, InspectionResponsibility $responsibility): void
    {
        InspectionResponsible::factory()->forInspection($inspection, $user)->create([
            'responsibility' => $responsibility,
            'is_primary' => true,
        ]);
    }

    /** @return array{Inspection, User, User, User} */
    private function inspectionWithReleaseTeam(InspectionStatus $status): array
    {
        $organization = Organization::factory()->create();
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => $status]);
        $reviewer = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $releaser = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Releaser]);

        $this->assign($inspection, $reviewer, InspectionResponsibility::Approver);
        $this->assign($inspection, $inspector, InspectionResponsibility::Reviewer);
        $this->assign($inspection, $releaser, InspectionResponsibility::Releaser);
        $this->completeOverview($inspection);

        return [$inspection, $reviewer, $inspector, $releaser];
    }

    private function completeOverview(Inspection $inspection): void
    {
        foreach ([1, 2] as $position) {
            $block = InspectionOverviewBlock::factory()->forInspection($inspection, $position)->create();

            foreach ([1, 2] as $slot) {
                InspectionOverviewPhoto::factory()->forBlock($block, $slot)->ready()->create();
            }
        }

        $inspection->update(['general_notes' => 'Aspectos gerais do equipamento preenchidos.']);
    }
}
