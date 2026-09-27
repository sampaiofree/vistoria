<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\SubmitInspectionForReview;
use App\Actions\Inspections\UpdateInspectionClassificationM2Links;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
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
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionPlannerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_workflow_requires_planner_even_without_notes(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::Planned);
        $this->actingAs($team['inspector'])->post(route('inspections.start', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-review', $inspection))->assertForbidden();
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasNoErrors();
        $this->assertSame(InspectionStatus::AwaitingM2, $inspection->fresh()->status);
        $this->assertNotNull($inspection->fresh()->field_completed_at);
        $this->actingAs($team['reviewer'])->post(route('inspections.start-review', $inspection))->assertForbidden();
        $this->actingAs($team['planner'])->get(route('inspections.classifications', $inspection))
            ->assertInertia(fn (Assert $page) => $page->where('content.classification_summary.can_edit_m2', true));
        $this->actingAs($team['planner'])->get(route('dashboard'))->assertOk();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['reviewer'])->post(route('inspections.start-review', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['reviewer'])->post(route('inspections.approve', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['releaser'])->post(route('inspections.release', $inspection))->assertSessionHasNoErrors();
        $this->assertSame(InspectionStatus::Released, $inspection->fresh()->status);
    }

    public function test_m2_is_only_required_when_planner_submits_and_partial_saves_are_allowed(): void
    {
        [$inspection, $team] = $this->scenario();
        $this->assessment($inspection);
        $this->actingAs($team['inspector'])->put(route('inspections.classification-m2-links.update', $inspection), $this->notes())->assertForbidden();
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->put(route('inspections.classification-m2-links.update', $inspection), $this->notes(''))->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasErrors('inspection');
        $this->actingAs($team['planner'])->put(route('inspections.classification-m2-links.update', $inspection), $this->notes())->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->fresh()->status);
    }

    public function test_special_fields_are_required_only_on_forward_handoff_and_accept_empty_m2_array(): void
    {
        [$inspection, $team] = $this->scenario();
        $assessment = $this->assessment($inspection, true);
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasNoErrors();
        $payload = ['links' => [], 'special_rows' => [[
            'assessment_public_id' => $assessment->public_id, 'service' => 'Ensaio', 'priority' => '', 'note' => '',
        ]]];
        $this->actingAs($team['planner'])->put(route('inspections.classification-m2-links.update', $inspection), $payload)->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasErrors('inspection');
        $payload['special_rows'][0]['priority'] = 'Alta';
        $payload['special_rows'][0]['note'] = '000123';
        $this->actingAs($team['planner'])->put(route('inspections.classification-m2-links.update', $inspection), $payload)->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
    }

    public function test_planner_can_return_defect_and_must_close_own_request_after_response(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::AwaitingM2);
        $assessment = $this->assessment($inspection);
        $this->actingAs($team['planner'])->post(route('defect-assessment-correction-requests.store', $assessment), ['request_message' => 'Confira a avaria.'])->assertSessionHasNoErrors();
        $request = InspectionCorrectionRequest::query()->sole();
        $this->assertSame(InspectionCorrectionRequestFlow::PlannerToInspector, $request->flow);
        $this->actingAs($team['planner'])->post(route('inspections.return-for-correction', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasErrors('inspection');
        $assessment->update(['status' => 'draft']);
        $this->actingAs($team['inspector'])->patch(route('inspection-correction-requests.address', $request), ['response_message' => 'Corrigido'])->assertSessionHasErrors('request');
        $assessment->update(['status' => 'complete']);
        $this->actingAs($team['inspector'])->patch(route('inspection-correction-requests.address', $request), ['response_message' => 'Corrigido'])->assertSessionHasNoErrors();
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->put(route('inspections.classification-m2-links.update', $inspection), $this->notes())->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasErrors('inspection');
        $this->actingAs($team['planner'])->patch(route('inspection-correction-requests.close', $request))->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
    }

    public function test_reviewer_requests_wait_for_reviewer_while_passing_through_planner(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::InReview);
        $this->actingAs($team['reviewer'])->post(route('inspections.return-for-correction', $inspection), ['justification' => 'Confira o relatório.'])->assertSessionHasNoErrors();
        $request = InspectionCorrectionRequest::query()->sole();
        $this->actingAs($team['inspector'])->patch(route('inspection-correction-requests.address', $request), ['response_message' => 'Conferido'])->assertSessionHasNoErrors();
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->patch(route('inspection-correction-requests.close', $request))->assertForbidden();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['reviewer'])->post(route('inspections.start-review', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['reviewer'])->post(route('inspections.approve', $inspection))->assertSessionHasErrors('inspection');
        $this->actingAs($team['reviewer'])->patch(route('inspection-correction-requests.close', $request))->assertSessionHasNoErrors();
        $this->actingAs($team['reviewer'])->post(route('inspections.approve', $inspection))->assertSessionHasNoErrors();
    }

    public function test_reviewer_and_releaser_revalidate_notes_in_existing_inspections(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::InReview);
        $this->assessment($inspection, true);
        $this->actingAs($team['reviewer'])->post(route('inspections.approve', $inspection))->assertSessionHasErrors('inspection');
        $inspection->update(['status' => InspectionStatus::AwaitingRelease]);
        $this->actingAs($team['releaser'])->post(route('inspections.release', $inspection))->assertSessionHasErrors('inspection');
        $this->actingAs($team['releaser'])->post(route('inspections.return-for-review', $inspection), ['justification' => 'Regularizar notas'])->assertSessionHasNoErrors();
        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->fresh()->status);
    }

    public function test_inactive_or_wrong_role_recipient_blocks_handoff_and_admin_can_replace(): void
    {
        [$inspection, $team] = $this->scenario();
        $team['planner']->update(['status' => 'inactive']);
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasErrors('inspection');
        $replacement = User::factory()->create(['organization_id' => $inspection->organization_id, 'operational_role' => OperationalRole::Planner]);
        $admin = User::factory()->create(['organization_id' => $inspection->organization_id, 'account_type' => UserAccountType::CompanyAdmin]);
        $this->actingAs($admin)->post(route('inspections.responsibles.store', $inspection), ['user_id' => $replacement->id, 'responsibility' => 'preparer'])->assertSessionHasNoErrors();
        $this->actingAs($team['inspector'])->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('inspections.classification-m2-links.update', $inspection), ['links' => []])->assertForbidden();
        $this->actingAs($replacement)->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
    }

    public function test_stale_page_and_action_cannot_save_after_transition_and_changes_are_audited(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::AwaitingM2);
        $this->assessment($inspection);
        $stale = $inspection->fresh();
        $this->actingAs($team['planner'])->put(route('inspections.classification-m2-links.update', $inspection), $this->notes())->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->put(route('inspections.classification-header.update', $inspection), ['general_drawing' => 'D-10', 'procedure_number' => 'P-1', 'inspected_on' => '2026-05-10'])->assertSessionHasNoErrors();
        $audit = $inspection->statusHistories()->where('reason', 'Classificação/M2: cabeçalho atualizado.')->sole();
        $this->assertSame($team['planner']->id, $audit->changed_by);
        $this->assertSame('D-10', $audit->metadata['after']['general_drawing']);
        $this->assertNotSame($audit->metadata['before'], $audit->metadata['after']);
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($team['planner'])->put(route('inspections.classification-m2-links.update', $inspection), $this->notes('999'))->assertForbidden();
        app(TenantContext::class)->set($inspection->organization);
        $this->expectException(AuthorizationException::class);
        app(UpdateInspectionClassificationM2Links::class)->handle($team['planner'], $stale, $this->notes('999'));
    }

    public function test_changed_classification_requires_new_note_and_keeps_previous_link(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::InReview);
        $assessment = $this->assessment($inspection);
        $this->actingAs($team['reviewer'])->put(route('inspections.classification-m2-links.update', $inspection), $this->notes())->assertSessionHasNoErrors();
        $assessment->update(['classification_code' => 'TA-3']);
        $this->actingAs($team['reviewer'])->post(route('inspections.approve', $inspection))->assertSessionHasErrors('inspection');
        $summary = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $row = collect($summary['categories'][0]['rows'])->firstWhere('classification_code', 'TA-2');
        $this->assertSame(0, $row['defect_count']);
        $this->assertNull($row['sap_m2_number']);
        $this->assertSame(1, $inspection->classificationM2Links()->count());
    }

    private function notes(string $number = '000123'): array
    {
        return ['links' => [['category' => 'TAC', 'classification_code' => 'TA-2', 'sap_number' => $number]]];
    }

    public function test_planner_cannot_edit_defects_and_unassigned_or_foreign_planner_cannot_edit_notes(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::AwaitingM2);
        $assessment = $this->assessment($inspection);
        $this->assertFalse($team['planner']->can('manageFieldContent', $inspection));
        $this->actingAs($team['planner'])->patch(route('defect-assessments.status.update', $assessment), ['status' => 'draft'])->assertForbidden();
        $outsider = User::factory()->create(['organization_id' => $inspection->organization_id, 'operational_role' => OperationalRole::Planner]);
        $this->actingAs($outsider)->put(route('inspections.classification-m2-links.update', $inspection), $this->notes())->assertForbidden();
        $foreign = User::factory()->for(Organization::factory())->create(['operational_role' => OperationalRole::Planner]);
        $this->assertFalse($foreign->can('manageClassificationM2', $inspection));
        $this->assertSame(0, $inspection->classificationM2Links()->count());
    }

    public function test_duplicate_handoff_and_stale_authority_do_not_change_history_or_notes(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::AwaitingM2);
        $stale = $inspection->fresh();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
        $count = $inspection->statusHistories()->count();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertForbidden();
        $this->actingAs($team['planner'])->put(route('inspections.classification-header.update', $inspection), ['general_drawing' => 'Late save', 'procedure_number' => null, 'inspected_on' => null])->assertForbidden();
        $this->assertSame($count, $inspection->statusHistories()->count());
        app(TenantContext::class)->set($inspection->organization);
        $this->expectException(ValidationException::class);
        app(SubmitInspectionForReview::class)->handle($stale, $team['planner']);
    }

    public function test_wrong_role_recipient_and_missing_recipient_do_not_partially_send_corrections(): void
    {
        [$inspection, $team] = $this->scenario(InspectionStatus::AwaitingM2);
        $team['inspector']->update(['operational_role' => OperationalRole::Planner]);
        $this->actingAs($team['planner'])->post(route('inspections.return-for-correction', $inspection), ['justification' => 'Rever avarias'])->assertSessionHasErrors('inspection');
        $this->assertSame(0, InspectionCorrectionRequest::query()->count());
        $this->assertSame(InspectionStatus::AwaitingM2, $inspection->fresh()->status);
        $inspection->responsibles()->where('responsibility', 'approver')->delete();
        $this->actingAs($team['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasErrors('inspection');
        $this->assertSame(0, $inspection->statusHistories()->count());
    }

    private function assessment(Inspection $inspection, bool $special = false): DefectAssessment
    {
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'category' => $special ? DefectCategory::SolidaryStructures : DefectCategory::AnticorrosiveTreatment,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create([
            'condition' => DefectAssessmentCondition::New,
            'classification_code' => $special ? null : 'TA-2',
            'classification_priority' => $special ? null : 2,
        ]);
        $this->satisfyAssessmentPublicationRequirements($assessment);

        return $assessment;
    }

    private function scenario(InspectionStatus $status = InspectionStatus::InProgress): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => $status, 'general_notes' => 'Aspectos gerais preenchidos.']);
        $team = [];
        foreach (['planner' => InspectionResponsibility::Preparer, 'inspector' => InspectionResponsibility::Reviewer, 'reviewer' => InspectionResponsibility::Approver, 'releaser' => InspectionResponsibility::Releaser] as $role => $responsibility) {
            $team[$role] = User::factory()->for($organization)->create(['operational_role' => $role]);
            InspectionResponsible::factory()->forInspection($inspection, $team[$role])->create(['responsibility' => $responsibility]);
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
