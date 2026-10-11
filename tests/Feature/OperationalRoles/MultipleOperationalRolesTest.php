<?php

namespace Tests\Feature\OperationalRoles;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Actions\Inspections\AssignInspectionResponsible;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MultipleOperationalRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_filter_multiple_roles_but_cannot_edit_another_admin(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => OperationalRole::Planner,
        ]);

        $this->actingAs($actor)->post(route('settings.users.store'), [
            'name' => 'Nova Administradora',
            'email' => 'nova-adm@example.test',
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_roles' => ['planner', 'inspector', 'reviewer', 'releaser'],
        ])->assertSessionHasNoErrors();

        $peer = User::query()->where('email', 'nova-adm@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(['planner', 'inspector', 'reviewer', 'releaser'], $peer->operationalRoleValues());
        $this->get(route('settings.users.index', ['operational_role' => 'releaser']))
            ->assertInertia(fn ($page) => $page->has('users.data', 1)
                ->where('users.data.0.public_id', $peer->public_id)
                ->where('users.data.0.edit_url', null));

        $this->get(route('settings.users.edit', $peer))->assertForbidden();
        $this->put(route('settings.users.update', $peer), [
            'name' => 'Alterado', 'email' => $peer->email,
            'account_type' => 'company_admin', 'operational_roles' => ['planner'],
        ])->assertForbidden();
        $this->patch(route('settings.users.status', $peer), ['status' => 'inactive'])->assertForbidden();
        $this->post(route('settings.users.reset-password', $peer))->assertForbidden();
        $this->assertEqualsCanonicalizing(['planner', 'inspector', 'reviewer', 'releaser'], $peer->fresh()->operationalRoleValues());

        $this->put(route('settings.users.update', $actor), [
            'name' => $actor->name, 'email' => $actor->email,
            'account_type' => 'company_admin', 'operational_roles' => ['planner', 'inspector'],
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['planner', 'inspector'], $actor->fresh()->operationalRoleValues());
    }

    public function test_removing_a_role_requires_replacing_its_open_assignment(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin, 'operational_role' => OperationalRole::Planner,
        ]);
        $member = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $member->syncOperationalRoles(['planner', 'inspector']);
        $inspection = Inspection::factory()->for($organization)->create(['status' => InspectionStatus::InProgress]);
        $assignment = InspectionResponsible::factory()->forInspection($inspection, $member)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);
        $payload = [
            'name' => $member->name, 'email' => $member->email,
            'account_type' => 'member', 'operational_roles' => ['planner'],
        ];

        $this->actingAs($admin)->put(route('settings.users.update', $member), $payload)
            ->assertSessionHasErrors('operational_roles');
        $this->assertTrue($member->fresh()->hasOperationalRole(OperationalRole::Inspector));

        $assignment->delete();
        $this->put(route('settings.users.update', $member), $payload)->assertSessionHasNoErrors();
        $this->assertSame(['planner'], $member->fresh()->operationalRoleValues());
    }

    public function test_dual_self_assignment_is_atomic_and_idempotent(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $actor->syncOperationalRoles(['reviewer', 'releaser']);
        $competitor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Releaser]);
        $inspection = Inspection::factory()->for($organization)->create(['status' => InspectionStatus::AwaitingReview]);
        $url = route('inspections.self-assign', $inspection);

        $this->actingAs($actor)->post($url)->assertSessionHasErrors('responsibilities');
        $this->post($url, ['responsibilities' => ['approver', 'releaser']])->assertSessionHasNoErrors();
        $this->assertSame(2, $inspection->responsibles()->count());
        $this->assertSame(2, $inspection->statusHistories()->count());
        $this->post($url, ['responsibilities' => ['approver', 'releaser']])->assertSessionHasNoErrors();
        $this->assertSame(2, $inspection->statusHistories()->count());

        $other = Inspection::factory()->for($organization)->create(['status' => InspectionStatus::AwaitingReview]);
        InspectionResponsible::factory()->forInspection($other, $competitor)->create([
            'responsibility' => InspectionResponsibility::Releaser,
        ]);
        $this->post(route('inspections.self-assign', $other), [
            'responsibilities' => ['approver', 'releaser'],
        ])->assertSessionHas('error');
        $this->assertFalse($other->hasResponsibility(InspectionResponsibility::Approver));
        $this->assertSame(0, $other->statusHistories()->count());
        $this->post(route('inspections.self-assign', $other), [
            'responsibilities' => ['approver'],
        ])->assertSessionHasNoErrors();
        $this->assertSame($actor->id, $other->responsibles()->where('responsibility', 'approver')->sole()->user_id);
    }

    public function test_one_administrator_can_hold_all_four_responsibilities_in_one_inspection(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin, 'operational_role' => OperationalRole::Planner,
        ]);
        $admin->syncOperationalRoles(['planner', 'inspector', 'reviewer', 'releaser']);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Planned]);
        $assign = app(AssignInspectionResponsible::class);
        app(TenantContext::class)->set($organization);
        try {
            foreach (InspectionResponsibility::cases() as $responsibility) {
                $assign->handle($inspection, $admin, $responsibility, $admin);
            }
            $this->assertSame(4, $inspection->responsibles()->count());
            $this->assertTrue($admin->can('updatePlanned', $inspection));
            $this->assertTrue($admin->can('start', $inspection));
            $inspection->update(['status' => InspectionStatus::InProgress]);
            $this->assertTrue($admin->can('manageReportContent', $inspection));
            $inspection->update(['status' => InspectionStatus::AwaitingM2]);
            $this->assertTrue($admin->can('submitForReview', $inspection));
            $inspection->update(['status' => InspectionStatus::InReview]);
            $this->assertTrue($admin->can('approve', $inspection));
            $inspection->update(['status' => InspectionStatus::AwaitingRelease]);
            $this->assertTrue($admin->can('release', $inspection));
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
