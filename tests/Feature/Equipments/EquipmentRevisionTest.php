<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EquipmentRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_revision_and_equipment_page_exposes_it(): void
    {
        [$organization, $admin, $equipment, $users] = $this->scenario();

        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), $this->payload($users, [
                'emission_type' => 'c',
                'revision_date' => '2025-06-23',
            ]))
            ->assertRedirect(route('equipments.show', $equipment));

        $revision = EquipmentRevision::query()->firstOrFail();

        $this->assertSame($organization->id, $revision->organization_id);
        $this->assertSame(EquipmentRevisionEmissionType::ForKnowledge, $revision->emission_type);
        $this->assertSame('2025-06-23', $revision->revision_date->toDateString());
        $this->assertSame($users['preparer']->name, $revision->preparer_name);

        $this->actingAs($admin)
            ->get(route('equipments.show', $equipment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.manage_revisions', true)
                ->has('history_entries', 1)
                ->where('history_entries.0.revision_number', 1)
                ->where('history_entries.0.description', 'Inspeção')
                ->where('history_entries.0.source', 'manual')
                ->where('history_entries.0.emission_type', 'C')
                ->where('history_entries.0.date', '23/06/2025')
                ->has('revision_emission_types', 9)
                ->has('revision_users', 5)
                ->where('revision_store_url', route('equipments.revisions.store', $equipment)));
    }

    public function test_revision_numbers_follow_date_and_creation_order_and_recalculate_after_edit_and_delete(): void
    {
        [, $admin, $equipment, $users] = $this->scenario();

        $first = $this->createRevision($admin, $equipment, $users, '2025-06-23');
        $sameDay = $this->createRevision($admin, $equipment, $users, '2025-06-23');
        $latest = $this->createRevision($admin, $equipment, $users, '2026-05-11');

        $this->actingAs($admin)
            ->get(route('equipments.show', $equipment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('history_entries.0.public_id', $latest->public_id)
                ->where('history_entries.0.revision_number', 3)
                ->where('history_entries.1.public_id', $sameDay->public_id)
                ->where('history_entries.1.revision_number', 2)
                ->where('history_entries.2.public_id', $first->public_id)
                ->where('history_entries.2.revision_number', 1)
                ->where('history_entries.1.description', 'Reinspeção'));

        $this->actingAs($admin)
            ->put(route('equipment-revisions.update', $latest), $this->payload($users, [
                'revision_date' => '2025-01-13',
            ]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('equipments.show', $equipment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('history_entries.2.public_id', $latest->public_id)
                ->where('history_entries.2.revision_number', 1));

        $this->actingAs($admin)
            ->delete(route('equipment-revisions.destroy', $sameDay))
            ->assertRedirect();

        $this->assertSoftDeleted('equipment_revisions', ['id' => $sameDay->id]);
        $this->assertSame(2, EquipmentRevision::query()->where('equipment_id', $equipment->id)->count());
    }

    public function test_history_entries_combine_manual_and_system_records_with_official_numbering(): void
    {
        [, $admin, $equipment, $users] = $this->scenario();

        $firstManual = $this->createRevision($admin, $equipment, $users, '2024-01-01');
        $released = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2025-000001',
            'status' => InspectionStatus::Released,
            'report_generated_at' => '2025-01-02 10:00:00',
            'report_date' => '2025-01-02',
            'released_at' => '2025-01-02 11:00:00',
        ]);
        $pending = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2026-000001',
            'status' => InspectionStatus::ReportGenerated,
            'report_generated_at' => '2026-01-02 10:00:00',
            'report_date' => '2026-01-02',
            'created_at' => '2026-01-02 10:00:00',
        ]);
        $canceled = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2026-000002',
            'status' => InspectionStatus::Canceled,
            'scheduled_for' => '2026-02-02',
            'canceled_at' => '2026-02-02 10:00:00',
            'created_at' => '2026-02-02 10:00:00',
        ]);
        $latestManual = $this->createRevision($admin, $equipment, $users, '2026-03-01');

        $this->actingAs($admin)
            ->get(route('equipments.show', $equipment))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history_entries', 5)
                ->where('history_entries.0.public_id', $latestManual->public_id)
                ->where('history_entries.0.source', 'manual')
                ->where('history_entries.0.revision_number', 3)
                ->where('history_entries.1.public_id', $canceled->public_id)
                ->where('history_entries.1.source', 'system')
                ->where('history_entries.1.revision_number', null)
                ->where('history_entries.2.public_id', $pending->public_id)
                ->where('history_entries.2.revision_number', null)
                ->where('history_entries.3.public_id', $released->public_id)
                ->where('history_entries.3.revision_number', 2)
                ->where('history_entries.3.description', 'Reinspeção')
                ->where('history_entries.4.public_id', $firstManual->public_id)
                ->where('history_entries.4.revision_number', 1)
                ->where('history_entries.4.description', 'Inspeção'));
    }

    public function test_suspended_user_can_be_kept_and_name_snapshot_is_not_changed(): void
    {
        [, $admin, $equipment, $users] = $this->scenario();
        $revision = $this->createRevision($admin, $equipment, $users, '2025-06-23');
        $originalName = $revision->preparer_name;

        $users['preparer']->update([
            'name' => 'Nome atualizado',
            'status' => UserStatus::Suspended,
        ]);

        $this->actingAs($admin)
            ->put(route('equipment-revisions.update', $revision), $this->payload($users))
            ->assertRedirect();

        $this->assertSame($originalName, $revision->refresh()->preparer_name);
        $this->assertSame(UserStatus::Suspended, $users['preparer']->refresh()->status);
    }

    public function test_changing_a_responsible_updates_the_link_and_frozen_name(): void
    {
        [$organization, $admin, $equipment, $users] = $this->scenario();
        $revision = $this->createRevision($admin, $equipment, $users, '2025-06-23');
        $replacement = User::factory()->for($organization)->create(['name' => 'Novo Preparador']);

        $this->actingAs($admin)
            ->put(route('equipment-revisions.update', $revision), $this->payload($users, [
                'preparer_id' => $replacement->id,
            ]))
            ->assertRedirect();

        $revision->refresh();
        $this->assertSame($replacement->id, $revision->preparer_id);
        $this->assertSame('Novo Preparador', $revision->preparer_name);
    }

    public function test_member_cannot_manage_revisions_and_foreign_users_are_rejected(): void
    {
        [$organization, $admin, $equipment, $users] = $this->scenario();
        $member = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member->value]);
        $otherUser = User::factory()->create();

        $this->actingAs($member)
            ->post(route('equipments.revisions.store', $equipment), $this->payload($users))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('equipments.show', $equipment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.manage_revisions', false)
                ->has('history_entries', 0)
                ->has('revision_users', 0));

        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), $this->payload($users, [
                'reviewer_id' => $otherUser->id,
            ]))
            ->assertSessionHasErrors('reviewer_id');
    }

    public function test_required_fields_and_invalid_emission_type_are_validated(): void
    {
        [, $admin, $equipment] = $this->scenario();

        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), [])
            ->assertSessionHasErrors([
                'emission_type',
                'revision_date',
                'preparer_id',
                'reviewer_id',
                'approver_id',
                'releaser_id',
            ]);
    }

    /** @return array{0:Organization, 1:User, 2:Equipment, 3:array<string, User>} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $users = [
            'preparer' => User::factory()->for($organization)->create(['name' => 'Preparador Histórico']),
            'reviewer' => User::factory()->for($organization)->create(['name' => 'Verificador Histórico']),
            'approver' => User::factory()->for($organization)->create(['name' => 'Aprovador Histórico']),
            'releaser' => User::factory()->for($organization)->create(['name' => 'Liberador Histórico']),
        ];

        return [$organization, $admin, $equipment, $users];
    }

    private function createRevision(User $admin, Equipment $equipment, array $users, string $date): EquipmentRevision
    {
        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), $this->payload($users, [
                'revision_date' => $date,
            ]))
            ->assertRedirect();

        return EquipmentRevision::query()->latest('id')->firstOrFail();
    }

    /** @param array<string, User> $users */
    private function payload(array $users, array $overrides = []): array
    {
        return array_merge([
            'emission_type' => 'C',
            'revision_date' => '2025-01-13',
            'preparer_id' => $users['preparer']->id,
            'reviewer_id' => $users['reviewer']->id,
            'approver_id' => $users['approver']->id,
            'releaser_id' => $users['releaser']->id,
        ], $overrides);
    }
}
