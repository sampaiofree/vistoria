<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\ApproveInspection;
use App\Actions\Inspections\CancelInspection;
use App\Actions\Inspections\CompleteInspectionReview;
use App\Actions\Inspections\MarkInspectionReportGenerated;
use App\Actions\Inspections\ReleaseInspection;
use App\Actions\Inspections\ReturnInspectionForCorrection;
use App\Actions\Inspections\StartInspection;
use App\Actions\Inspections\SubmitInspectionForReview;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InspectionTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        app(TenantContext::class)->set($this->organization);
    }

    public function test_complete_valid_lifecycle_sets_audit_fields_timestamps_and_history(): void
    {
        $inspection = $this->inspection();
        $inspector = $this->responsible($inspection, InspectionResponsibility::Inspector);
        $preparer = $this->responsible($inspection, InspectionResponsibility::Preparer);
        $reviewer = $this->responsible($inspection, InspectionResponsibility::Reviewer);
        $approver = $this->responsible($inspection, InspectionResponsibility::Approver);
        $releaser = $this->responsible($inspection, InspectionResponsibility::Releaser);

        $inspection = app(StartInspection::class)->handle($inspection, $inspector);
        $this->assertNotNull($inspection->started_at);
        $this->assertNotNull($inspection->inspected_on);
        $inspection = app(SubmitInspectionForReview::class)->handle($inspection, $preparer);
        $this->assertNotNull($inspection->field_completed_at);
        $inspection = app(CompleteInspectionReview::class)->handle($inspection, $reviewer);
        $this->assertNotNull($inspection->reviewed_at);
        $inspection = app(ApproveInspection::class)->handle($inspection, $approver);
        $this->assertNotNull($inspection->approved_at);
        $inspection = app(MarkInspectionReportGenerated::class)->handle($inspection, $approver);
        $this->assertNotNull($inspection->report_generated_at);
        $inspection = app(ReleaseInspection::class)->handle($inspection, $releaser);

        $this->assertSame(InspectionStatus::Released, $inspection->status);
        $this->assertNotNull($inspection->released_at);
        $this->assertSame($releaser->id, $inspection->updated_by);
        $this->assertCount(6, $inspection->statusHistories);
        $this->assertSame(InspectionStatus::Planned, $inspection->statusHistories->first()->from_status);
        $this->assertSame(InspectionStatus::Released, $inspection->statusHistories->last()->to_status);
        $this->assertNotNull($inspection->statusHistories->last()->created_at);
    }

    public function test_correction_paths_require_the_responsible_for_each_stage_and_preserve_first_completion(): void
    {
        $inspection = $this->inspection(InspectionStatus::AwaitingReview, ['field_completed_at' => now()->subDay()]);
        $reviewer = $this->responsible($inspection, InspectionResponsibility::Reviewer);
        $preparer = $this->responsible($inspection, InspectionResponsibility::Preparer);
        $approver = $this->responsible($inspection, InspectionResponsibility::Approver);
        $completedAt = $inspection->field_completed_at;

        $inspection = app(ReturnInspectionForCorrection::class)->handle($inspection, $reviewer, 'Ajustar medições');
        $this->assertSame('Ajustar medições', $inspection->statusHistories()->latest('id')->first()->reason);
        $inspection = app(SubmitInspectionForReview::class)->handle($inspection, $preparer);
        $this->assertTrue($completedAt->equalTo($inspection->field_completed_at));
        $inspection = app(CompleteInspectionReview::class)->handle($inspection, $reviewer);
        $inspection = app(ReturnInspectionForCorrection::class)->handle($inspection, $approver, 'Revisar conclusão');

        $this->assertSame(InspectionStatus::InCorrection, $inspection->status);
    }

    public function test_each_action_rejects_an_invalid_source_state_without_writing_history(): void
    {
        $actor = User::factory()->create(['organization_id' => $this->organization->id]);
        $cases = [
            [StartInspection::class, InspectionStatus::InProgress, []],
            [SubmitInspectionForReview::class, InspectionStatus::Planned, []],
            [ReturnInspectionForCorrection::class, InspectionStatus::InProgress, ['motivo']],
            [CompleteInspectionReview::class, InspectionStatus::Planned, []],
            [ApproveInspection::class, InspectionStatus::Planned, []],
            [MarkInspectionReportGenerated::class, InspectionStatus::Planned, []],
            [ReleaseInspection::class, InspectionStatus::Planned, []],
            [CancelInspection::class, InspectionStatus::Approved, ['motivo']],
        ];

        foreach ($cases as [$action, $status, $arguments]) {
            $inspection = $this->inspection($status);
            try {
                app($action)->handle($inspection, $actor, ...$arguments);
                $this->fail("{$action} aceitou um estado de origem inválido.");
            } catch (ValidationException) {
                $this->assertDatabaseMissing('inspection_status_histories', ['inspection_id' => $inspection->id]);
            }
        }
    }

    public function test_missing_assignments_and_wrong_responsibles_are_rejected(): void
    {
        $actor = User::factory()->create(['organization_id' => $this->organization->id]);

        foreach ([
            [StartInspection::class, InspectionStatus::Planned],
            [SubmitInspectionForReview::class, InspectionStatus::InProgress],
            [CompleteInspectionReview::class, InspectionStatus::AwaitingReview],
            [ApproveInspection::class, InspectionStatus::AwaitingApproval],
            [ReleaseInspection::class, InspectionStatus::ReportGenerated],
        ] as [$action, $status]) {
            $this->expectActionValidation(fn () => app($action)->handle($this->inspection($status), $actor));
        }
    }

    public function test_inactive_historically_assigned_user_cannot_act_or_satisfy_a_prerequisite(): void
    {
        $inspection = $this->inspection();
        $inactiveInspector = $this->responsible($inspection, InspectionResponsibility::Inspector, inactive: true);

        $this->expectActionValidation(fn () => app(StartInspection::class)->handle($inspection, $inactiveInspector));

        $activeActor = $this->responsible($inspection, InspectionResponsibility::Preparer);
        $inspection->update(['status' => InspectionStatus::InProgress]);
        $this->responsible($inspection, InspectionResponsibility::Reviewer, inactive: true);
        $this->expectActionValidation(fn () => app(SubmitInspectionForReview::class)->handle($inspection, $activeActor));
    }

    public function test_return_and_cancel_require_non_blank_reasons(): void
    {
        $reviewInspection = $this->inspection(InspectionStatus::AwaitingReview);
        $reviewer = $this->responsible($reviewInspection, InspectionResponsibility::Reviewer);
        $this->expectActionValidation(fn () => app(ReturnInspectionForCorrection::class)->handle($reviewInspection, $reviewer, '  '));

        $cancelInspection = $this->inspection();
        $admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => 'company_admin',
        ]);
        $this->expectActionValidation(fn () => app(CancelInspection::class)->handle($cancelInspection, $admin, ''));

        $canceled = app(CancelInspection::class)->handle($cancelInspection, $admin, 'Ordem cancelada');
        $this->assertNotNull($canceled->canceled_at);
        $this->assertSame('Ordem cancelada', $canceled->statusHistories()->first()->reason);
    }

    private function inspection(InspectionStatus $status = InspectionStatus::Planned, array $attributes = []): Inspection
    {
        return Inspection::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'status' => $status,
        ], $attributes));
    }

    private function responsible(Inspection $inspection, InspectionResponsibility $role, bool $inactive = false): User
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => $inactive ? 'inactive' : 'active',
        ]);
        InspectionResponsible::query()->create([
            'organization_id' => $this->organization->id,
            'inspection_id' => $inspection->id,
            'user_id' => $user->id,
            'responsibility' => $role,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    private function expectActionValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('A transição deveria ter sido rejeitada.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
