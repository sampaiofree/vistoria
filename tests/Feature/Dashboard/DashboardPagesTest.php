<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\InspectionStatusHistory;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_sees_operational_dashboard_with_deferred_blocks(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);

        $equipment = Equipment::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $planned = $this->createInspectionWithResponsibility(
            $equipment,
            $user,
            InspectionStatus::Planned,
            InspectionResponsibility::Inspector,
            'INS-2026-000001',
            now()->subDays(2),
            now()->subHours(5),
        );

        $review = $this->createInspectionWithResponsibility(
            $equipment,
            $user,
            InspectionStatus::AwaitingReview,
            InspectionResponsibility::Reviewer,
            'INS-2026-000002',
            now()->addDays(1),
            now()->subHours(4),
        );

        $correction = $this->createInspectionWithResponsibility(
            $equipment,
            $user,
            InspectionStatus::InCorrection,
            InspectionResponsibility::Preparer,
            'INS-2026-000003',
            now()->addDays(3),
            now()->subHours(3),
        );

        $approval = $this->createInspectionWithResponsibility(
            $equipment,
            $user,
            InspectionStatus::AwaitingApproval,
            InspectionResponsibility::Approver,
            'INS-2026-000004',
            now()->addDays(4),
            now()->subHours(2),
        );

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($organization, $planned, $review, $correction, $approval, $user): void {
                $page
                    ->component('Dashboard/Index')
                    ->where('mode', 'operational')
                    ->where('organization.name', $organization->name)
                    ->where('can.create_inspection', true)
                    ->where('can.view_company_summary', true)
                    ->has('auth.logout_url')
                    ->has('navigation', 4)
                    ->has('links.dashboard')
                    ->has('links.inspections_index')
                    ->has('links.inspections_create')
                    ->has('links.priority.overdue')
                    ->missing('priority_counts')
                    ->missing('my_inspections')
                    ->missing('workflow_summary')
                    ->missing('recent_activities')
                    ->loadDeferredProps(function (Assert $deferred) use ($planned, $review, $correction, $approval, $user): void {
                        $deferred
                            ->where('priority_counts.overdue', 1)
                            ->where('priority_counts.awaiting_review', 1)
                            ->where('priority_counts.in_correction', 1)
                            ->where('priority_counts.awaiting_approval', 1)
                            ->has('my_inspections', 4)
                            ->where('my_inspections.0.number', $planned->number)
                            ->where('my_inspections.0.schedule.label', '2 dias atrasada')
                            ->where('my_inspections.0.next_action.label', 'Iniciar inspeção')
                            ->where('my_inspections.1.number', $correction->number)
                            ->where('my_inspections.2.number', $review->number)
                            ->where('my_inspections.3.number', $approval->number)
                            ->has('workflow_summary', 8)
                            ->where('workflow_summary.0.label', 'Planejadas')
                            ->where('workflow_summary.0.count', 1)
                            ->where('workflow_summary.2.label', 'Revisão')
                            ->where('workflow_summary.2.count', 1)
                            ->where('workflow_summary.3.label', 'Correção')
                            ->where('workflow_summary.3.count', 1)
                            ->where('workflow_summary.4.label', 'Aprovação')
                            ->where('workflow_summary.4.count', 1)
                            ->has('recent_activities', 4)
                            ->where('recent_activities.0.status', InspectionStatus::AwaitingApproval->value)
                            ->where('recent_activities.0.actor', $user->name);
                    });
            });
    }

    public function test_super_admin_gets_global_dashboard_shell(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page
                    ->component('Dashboard/Index')
                    ->where('mode', 'global')
                    ->where('organization', null)
                    ->where('can.create_inspection', false)
                    ->where('can.view_company_summary', false)
                    ->has('navigation', 1)
                    ->where('priority_counts', null)
                    ->has('my_inspections', 0)
                    ->has('workflow_summary', 0)
                    ->has('recent_activities', 0);
            });
    }

    public function test_member_dashboard_and_drill_down_links_keep_personal_responsibility_scope(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create([
            'organization_id' => $organization->id,
            'account_type' => UserAccountType::Member->value,
        ]);
        $otherUser = User::factory()->create([
            'organization_id' => $organization->id,
            'account_type' => UserAccountType::Member->value,
        ]);

        $reviewAsReviewer = $this->createInspectionWithResponsibility(
            Equipment::factory()->create(['organization_id' => $organization->id]),
            $member,
            InspectionStatus::AwaitingReview,
            InspectionResponsibility::Reviewer,
            'INS-2026-000101',
            now()->addDay(),
            now()->subMinutes(10),
        );

        $reviewAsInspector = $this->createInspectionWithResponsibility(
            Equipment::factory()->create(['organization_id' => $organization->id]),
            $member,
            InspectionStatus::AwaitingReview,
            InspectionResponsibility::Inspector,
            'INS-2026-000102',
            now()->addDays(2),
            now()->subMinutes(20),
        );

        $this->createInspectionWithResponsibility(
            Equipment::factory()->create(['organization_id' => $organization->id]),
            $otherUser,
            InspectionStatus::AwaitingReview,
            InspectionResponsibility::Reviewer,
            'INS-2026-000103',
            now()->addDays(3),
            now()->subMinutes(30),
        );

        $otherOrganization = Organization::factory()->create();
        $otherOrganizationUser = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'account_type' => UserAccountType::Member->value,
        ]);

        $this->createInspectionWithResponsibility(
            Equipment::factory()->create(['organization_id' => $otherOrganization->id]),
            $otherOrganizationUser,
            InspectionStatus::AwaitingReview,
            InspectionResponsibility::Reviewer,
            'INS-2026-000104',
            now()->addDays(4),
            now()->subMinutes(40),
        );

        $priorityUrl = route('inspections.index', [
            'status' => InspectionStatus::AwaitingReview->value,
            'responsible' => $member->getKey(),
            'responsibility' => InspectionResponsibility::Reviewer->value,
        ]);
        $workflowUrl = route('inspections.index', [
            'status' => InspectionStatus::AwaitingReview->value,
            'responsible' => $member->getKey(),
        ]);

        $response = $this->actingAs($member)->get('/dashboard');

        $response->assertOk()->assertInertia(function (Assert $page) use (
            $member,
            $priorityUrl,
            $reviewAsInspector,
            $reviewAsReviewer,
            $workflowUrl,
        ): void {
            $page
                ->component('Dashboard/Index')
                ->where('mode', 'operational')
                ->where('can.create_inspection', false)
                ->where('can.view_company_summary', false)
                ->where('links.inspections_index', route('inspections.index', [
                    'responsible' => $member->getKey(),
                ]))
                ->where('links.priority.awaiting_review', $priorityUrl)
                ->where('links.workflow.awaiting_review', $workflowUrl)
                ->loadDeferredProps('dashboard-priority-counts', function (Assert $deferred): void {
                    $deferred
                        ->where('priority_counts.awaiting_review', 1)
                        ->missing('my_inspections')
                        ->missing('workflow_summary')
                        ->missing('recent_activities');
                })
                ->loadDeferredProps('dashboard-my-inspections', function (Assert $deferred) use (
                    $reviewAsInspector,
                    $reviewAsReviewer,
                ): void {
                    $deferred
                        ->has('my_inspections', 2)
                        ->where('my_inspections.0.number', $reviewAsReviewer->number)
                        ->where('my_inspections.0.next_action.label', 'Abrir para revisar')
                        ->where('my_inspections.1.number', $reviewAsInspector->number)
                        ->where('my_inspections.1.next_action.label', 'Acompanhar revisão')
                        ->missing('priority_counts')
                        ->missing('workflow_summary')
                        ->missing('recent_activities');
                })
                ->loadDeferredProps('dashboard-workflow-summary', function (Assert $deferred) use ($workflowUrl): void {
                    $deferred
                        ->where('workflow_summary.2.count', 2)
                        ->where('workflow_summary.2.href', $workflowUrl)
                        ->missing('priority_counts')
                        ->missing('my_inspections')
                        ->missing('recent_activities');
                });
        });

        $this->actingAs($member)
            ->get($priorityUrl)
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($reviewAsReviewer): void {
                $page
                    ->component('Inspections/Index')
                    ->where('filters.responsibility', InspectionResponsibility::Reviewer->value)
                    ->where('inspections.total', 1)
                    ->where('inspections.data.0.number', $reviewAsReviewer->number);
            });

        $this->actingAs($member)
            ->get($workflowUrl)
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page
                    ->component('Inspections/Index')
                    ->where('inspections.total', 2);
            });
    }

    public function test_dashboard_uses_organization_timezone_and_excludes_final_inspections_from_attention_list(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-30 12:00:00', 'UTC'));

        $organization = Organization::factory()->create([
            'timezone' => 'Pacific/Kiritimati',
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);

        $planned = $this->createInspectionWithResponsibility(
            Equipment::factory()->create(['organization_id' => $organization->id]),
            $user,
            InspectionStatus::Planned,
            InspectionResponsibility::Inspector,
            'INS-2026-000201',
            CarbonImmutable::parse('2026-07-30'),
            now()->subMinutes(10),
        );
        $approved = $this->createInspectionWithResponsibility(
            Equipment::factory()->create(['organization_id' => $organization->id]),
            $user,
            InspectionStatus::Approved,
            InspectionResponsibility::Approver,
            'INS-2026-000202',
            CarbonImmutable::parse('2026-07-01'),
            now()->subMinutes(20),
        );

        foreach ([InspectionStatus::Released, InspectionStatus::Canceled] as $index => $status) {
            $this->createInspectionWithResponsibility(
                Equipment::factory()->create(['organization_id' => $organization->id]),
                $user,
                $status,
                InspectionResponsibility::Inspector,
                sprintf('INS-2026-00020%d', $index + 3),
                CarbonImmutable::parse('2026-06-01'),
                now()->subMinutes(30 + $index),
            );
        }

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($approved, $planned): void {
                $page->loadDeferredProps('dashboard-my-inspections', function (Assert $deferred) use (
                    $approved,
                    $planned,
                ): void {
                    $deferred
                        ->has('my_inspections', 2)
                        ->where('my_inspections.0.number', $planned->number)
                        ->where('my_inspections.0.schedule.label', '1 dia atrasada')
                        ->where('my_inspections.0.schedule.is_overdue', true)
                        ->where('my_inspections.1.number', $approved->number)
                        ->where('my_inspections.1.schedule.label', 'Data programada')
                        ->where('my_inspections.1.schedule.is_overdue', false);
                });
            });
    }

    private function createInspectionWithResponsibility(
        Equipment $equipment,
        User $user,
        InspectionStatus $status,
        InspectionResponsibility $responsibility,
        string $number,
        \DateTimeInterface $scheduledFor,
        \DateTimeInterface $historyDate,
    ): Inspection {
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'organization_id' => $equipment->organization_id,
            'number' => $number,
            'status' => $status,
            'scheduled_for' => $scheduledFor,
            'created_by' => $user->getKey(),
        ]);

        InspectionResponsible::factory()
            ->forInspection($inspection, $user)
            ->primary()
            ->create([
                'responsibility' => $responsibility,
                'assigned_by' => $user->getKey(),
            ]);

        InspectionStatusHistory::factory()
            ->forInspection($inspection, $user, $status)
            ->create([
                'created_at' => $historyDate,
            ]);

        return $inspection;
    }
}
