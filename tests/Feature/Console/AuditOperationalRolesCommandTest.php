<?php

namespace Tests\Feature\Console;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditOperationalRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_missing_roles_and_incompatible_open_assignments_without_writing_data(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => null,
        ]);
        $wrongRole = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Planner,
        ]);
        $this->assertDatabaseHas('user_operational_roles', ['user_id' => $wrongRole->id, 'role' => 'planner']);
        $inspection = Inspection::factory()->for($organization)->create(['status' => InspectionStatus::InReview]);
        $assignment = InspectionResponsible::factory()->forInspection($inspection, $wrongRole)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);

        $this->artisan('app:audit-operational-roles')
            ->expectsOutput('Contas internas sem função: 1')
            ->expectsOutput('Responsabilidades abertas incompatíveis: 1')
            ->assertExitCode(1);

        $this->assertNull($admin->fresh()->operational_role);
        $this->assertDatabaseHas('inspection_responsibles', ['id' => $assignment->id, 'user_id' => $wrongRole->id]);
    }

    public function test_it_ignores_finalized_assignments_and_accepts_matching_roles(): void
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $open = Inspection::factory()->for($organization)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($open, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);

        $final = Inspection::factory()->for($organization)->create(['status' => InspectionStatus::Released]);
        InspectionResponsible::factory()->forInspection($final, $inspector)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);

        $this->artisan('app:audit-operational-roles')
            ->expectsOutput('Contas internas sem função: 0')
            ->expectsOutput('Responsabilidades abertas incompatíveis: 0')
            ->assertExitCode(0);
    }
}
