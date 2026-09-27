<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\ManageInspectionCorrectionRequest;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
use App\Models\InspectionResponsible;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InspectionCorrectionRequestClosureTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('flows')]
    public function test_repeated_closure_preserves_the_first_closure_and_history(InspectionCorrectionRequestFlow $flow): void
    {
        $this->freezeSecond();
        [$request, $requester, $assessment] = $this->scenario($flow);
        $pageUrl = route('defect-assessments.show', $assessment);
        $closeUrl = route('inspection-correction-requests.close', $request);

        $this->actingAs($requester)->get($pageUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->where('correction_requests.items.0.close_url', $closeUrl)
                ->has('correction_requests.history', 0));

        $this->from($pageUrl)->patch($closeUrl)
            ->assertRedirect($pageUrl)->assertSessionHasNoErrors();

        $closed = $request->fresh();
        $this->assertSame(InspectionCorrectionRequestStatus::Closed, $closed->status);
        $this->assertSame($requester->id, $closed->closed_by);
        $this->assertTrue($closed->closed_at->equalTo(now()));
        $original = $closed->getRawOriginal();

        $this->travel(1)->minutes();
        $this->patch($closeUrl)->assertRedirect($pageUrl)->assertSessionHasNoErrors();
        $this->assertSame($original, $request->fresh()->getRawOriginal());

        // An action called with a stale, addressed model must also reread the closure.
        $this->assertSame(InspectionCorrectionRequestStatus::Addressed, $request->status);
        $tenant = app(TenantContext::class);
        $tenant->set($requester->organization);
        try {
            app(ManageInspectionCorrectionRequest::class)->close($requester, $request);
        } finally {
            $tenant->clear();
        }
        $this->assertSame($original, $request->fresh()->getRawOriginal());
        $this->assertDatabaseCount('inspection_correction_requests', 1);

        $this->get($pageUrl)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('correction_requests.items', 0)
                ->has('correction_requests.history', 1)
                ->where('correction_requests.history.0.status', InspectionCorrectionRequestStatus::Closed->value)
                ->where('correction_requests.history.0.close_url', null)
                ->where('correction_requests.history.0.replace_url', null));

        $this->post(route('inspection-correction-requests.replace', $request), [
            'request_message' => 'Este chamado encerrado não pode ser substituído.',
        ])->assertForbidden();
        $this->assertSame($original, $request->fresh()->getRawOriginal());
        $this->assertDatabaseCount('inspection_correction_requests', 1);
    }

    public function test_closure_retries_still_require_the_assigned_requester_and_same_organization(): void
    {
        [$request, $requester] = $this->scenario();
        $url = route('inspection-correction-requests.close', $request);
        $this->actingAs($requester)->patch($url)->assertSessionHasNoErrors();
        $original = $request->fresh()->getRawOriginal();

        $unassigned = User::factory()->create([
            'organization_id' => $request->organization_id,
            'operational_role' => OperationalRole::Reviewer,
        ]);
        $this->actingAs($unassigned)->patch($url)->assertForbidden();

        $requester->update(['operational_role' => OperationalRole::Inspector]);
        $this->actingAs($requester)->patch($url)->assertForbidden();
        $requester->update(['operational_role' => OperationalRole::Reviewer, 'account_type' => UserAccountType::CompanyAdmin]);
        $this->actingAs($requester)->patch($url)->assertForbidden();

        $otherOrganizationReviewer = User::factory()->create(['operational_role' => OperationalRole::Reviewer]);
        $this->actingAs($otherOrganizationReviewer)->patch($url)->assertNotFound();
        $this->assertSame($original, $request->fresh()->getRawOriginal());
    }

    #[DataProvider('flows')]
    public function test_closure_retries_still_require_the_requester_stage(InspectionCorrectionRequestFlow $flow): void
    {
        [$request, $requester] = $this->scenario($flow);
        $url = route('inspection-correction-requests.close', $request);
        $this->actingAs($requester)->patch($url)->assertSessionHasNoErrors();
        $original = $request->fresh()->getRawOriginal();

        $request->inspection->update(['status' => InspectionStatus::InCorrection]);
        $this->patch($url)->assertForbidden();
        $this->assertSame($original, $request->fresh()->getRawOriginal());
    }

    public function test_unanswered_or_superseded_requests_cannot_be_closed(): void
    {
        [$request, $requester] = $this->scenario();
        foreach ([InspectionCorrectionRequestStatus::Marked, InspectionCorrectionRequestStatus::Requested, InspectionCorrectionRequestStatus::Superseded] as $status) {
            $request->update(['status' => $status]);
            $this->actingAs($requester)->patch(route('inspection-correction-requests.close', $request))->assertForbidden();
            $this->assertSame($status, $request->fresh()->status);
            $this->assertNull($request->fresh()->closed_at);
        }
    }

    public static function flows(): array
    {
        return [
            'reviewer' => [InspectionCorrectionRequestFlow::ReviewerToInspector],
            'planner' => [InspectionCorrectionRequestFlow::PlannerToInspector],
            'releaser' => [InspectionCorrectionRequestFlow::ReleaserToReviewer],
        ];
    }

    /** @return array{InspectionCorrectionRequest, User, DefectAssessment} */
    private function scenario(InspectionCorrectionRequestFlow $flow = InspectionCorrectionRequestFlow::ReviewerToInspector): array
    {
        [$status, $role, $responsibility] = match ($flow) {
            InspectionCorrectionRequestFlow::ReviewerToInspector => [InspectionStatus::InReview, OperationalRole::Reviewer, InspectionResponsibility::Approver],
            InspectionCorrectionRequestFlow::PlannerToInspector => [InspectionStatus::AwaitingM2, OperationalRole::Planner, InspectionResponsibility::Preparer],
            InspectionCorrectionRequestFlow::ReleaserToReviewer => [InspectionStatus::AwaitingRelease, OperationalRole::Releaser, InspectionResponsibility::Releaser],
        };
        $inspection = Inspection::factory()->create(['status' => $status]);
        $requester = User::factory()->create(['organization_id' => $inspection->organization_id, 'operational_role' => $role]);
        InspectionResponsible::factory()->forInspection($inspection, $requester)->create(['responsibility' => $responsibility]);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();
        $request = InspectionCorrectionRequest::query()->create([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
            'flow' => $flow,
            'status' => InspectionCorrectionRequestStatus::Addressed,
            'request_message' => 'Atualize a recomendação técnica da avaria.',
            'response_message' => 'Recomendação técnica atualizada.',
            'created_by' => $requester->id,
            'addressed_at' => now()->subHour(),
        ]);

        return [$request, $requester, $assessment];
    }
}
