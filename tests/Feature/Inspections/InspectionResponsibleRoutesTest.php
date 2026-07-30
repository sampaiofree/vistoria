<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionResponsibleRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_mark_primary_and_remove_responsibles_through_real_routes(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $inspection = Inspection::factory()
            ->create(['organization_id' => $organization->id]);
        $firstUser = User::factory()->for($organization)->create();
        $secondUser = User::factory()->for($organization)->create();

        $this->actingAs($admin)
            ->post(route('inspections.responsibles.store', $inspection), [
                'user_id' => $firstUser->id,
                'responsibility' => InspectionResponsibility::Inspector->value,
                'is_primary' => true,
            ])
            ->assertRedirect();

        $firstResponsible = InspectionResponsible::query()->firstOrFail();
        $this->assertTrue($firstResponsible->is_primary);
        $this->assertSame($admin->id, $firstResponsible->assigned_by);

        $this->actingAs($admin)
            ->post(route('inspections.responsibles.store', $inspection), [
                'user_id' => $secondUser->id,
                'responsibility' => InspectionResponsibility::Inspector->value,
            ])
            ->assertRedirect();

        $secondResponsible = InspectionResponsible::query()
            ->where('user_id', $secondUser->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('inspections.responsibles.update', [$inspection, $secondResponsible]))
            ->assertRedirect();

        $this->assertFalse($firstResponsible->refresh()->is_primary);
        $this->assertTrue($secondResponsible->refresh()->is_primary);

        $this->actingAs($admin)
            ->delete(route('inspections.responsibles.destroy', [$inspection, $firstResponsible]))
            ->assertRedirect();

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
}
