<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionResponsibleRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_replaces_the_responsible_for_a_role_through_real_routes(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $inspection = Inspection::factory()
            ->create(['organization_id' => $organization->id, 'status' => InspectionStatus::AwaitingReview]);
        $firstUser = User::factory()->for($organization)->create();
        $secondUser = User::factory()->for($organization)->create();

        $this->actingAs($admin)
            ->post(route('inspections.responsibles.store', $inspection), [
                'user_id' => $firstUser->id,
                'responsibility' => InspectionResponsibility::Preparer->value,
            ])
            ->assertRedirect();

        $firstResponsible = InspectionResponsible::query()->firstOrFail();
        $this->assertTrue($firstResponsible->is_primary);
        $this->assertSame($admin->id, $firstResponsible->assigned_by);

        $this->actingAs($admin)
            ->post(route('inspections.responsibles.store', $inspection), [
                'user_id' => $secondUser->id,
                'responsibility' => InspectionResponsibility::Preparer->value,
            ])
            ->assertRedirect();

        $secondResponsible = InspectionResponsible::query()
            ->where('user_id', $secondUser->id)
            ->firstOrFail();

        $this->assertTrue($secondResponsible->refresh()->is_primary);
        $this->assertDatabaseMissing('inspection_responsibles', ['id' => $firstResponsible->id]);
        $this->assertDatabaseHas('inspection_responsibles', ['id' => $secondResponsible->id]);
    }

    public function test_member_cannot_manage_responsibles(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::Member->value,
            ]);
        $inspection = Inspection::factory()
            ->create(['organization_id' => $organization->id]);
        $user = User::factory()->for($organization)->create();

        $this->actingAs($member)
            ->post(route('inspections.responsibles.store', $inspection), [
                'user_id' => $user->id,
                'responsibility' => InspectionResponsibility::Reviewer->value,
            ])
            ->assertForbidden();

        $responsible = InspectionResponsible::factory()
            ->forInspection($inspection, $user)
            ->create([
                'organization_id' => $organization->id,
                'responsibility' => InspectionResponsibility::Reviewer,
            ]);

        $this->actingAs($member)
            ->patch(route('inspections.responsibles.update', [$inspection, $responsible]))
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('inspections.responsibles.destroy', [$inspection, $responsible]))
            ->assertForbidden();
    }

    public function test_removed_inspector_role_is_rejected_by_the_assignment_route(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()->create(['organization_id' => $organization->id, 'status' => InspectionStatus::AwaitingReview]);
        $user = User::factory()->for($organization)->create();

        $this->actingAs($admin)
            ->post(route('inspections.responsibles.store', $inspection), [
                'user_id' => $user->id,
                'responsibility' => 'inspector',
            ])
            ->assertSessionHasErrors('responsibility');

        $this->assertDatabaseCount('inspection_responsibles', 0);
    }

    public function test_team_page_offers_only_the_four_official_responsibilities(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $inspection = Inspection::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->get(route('inspections.team', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Team')
                ->has('assignment_options.roles', 4)
                ->where('assignment_options.roles.0.value', InspectionResponsibility::Preparer->value)
                ->where('assignment_options.roles.1.value', InspectionResponsibility::Reviewer->value)
                ->where('assignment_options.roles.1.label', 'Verificador')
                ->where('assignment_options.roles.2.value', InspectionResponsibility::Approver->value)
                ->where('assignment_options.roles.3.value', InspectionResponsibility::Releaser->value));
    }
}
