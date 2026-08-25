<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
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
        [$organization, $admin, $equipment] = $this->scenario();

        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), $this->payload([
                'emission_type' => 'c',
                'revision_date' => '2025-06-23',
                'preparer_name' => 'Preparador externo',
                'reviewer_name' => 'Verificador externo',
                'approver_name' => 'Aprovador externo',
                'releaser_name' => 'Liberador externo',
            ]))
            ->assertRedirect(route('equipments.show', $equipment));

        $revision = EquipmentRevision::query()->firstOrFail();

        $this->assertSame($organization->id, $revision->organization_id);
        $this->assertSame(EquipmentRevisionEmissionType::ForKnowledge, $revision->emission_type);
        $this->assertSame('2025-06-23', $revision->revision_date->toDateString());
        $this->assertSame('Preparador externo', $revision->preparer_name);

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
                ->where('history_entries.0.preparer_name', 'Preparador externo')
                ->has('revision_emission_types', 9)
                ->where('revision_store_url', route('equipments.revisions.store', $equipment)));
    }

    public function test_revision_numbers_follow_date_and_creation_order_and_recalculate_after_edit_and_delete(): void
    {
        [, $admin, $equipment] = $this->scenario();

        $first = $this->createRevision($admin, $equipment, '2025-06-23');
        $sameDay = $this->createRevision($admin, $equipment, '2025-06-23');
        $latest = $this->createRevision($admin, $equipment, '2026-05-11');

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
            ->put(route('equipment-revisions.update', $latest), $this->payload([
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
        [, $admin, $equipment] = $this->scenario();

        $firstManual = $this->createRevision($admin, $equipment, '2024-01-01');
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
        $latestManual = $this->createRevision($admin, $equipment, '2026-03-01');

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

    public function test_editing_a_responsible_updates_the_free_text_name(): void
    {
        [, $admin, $equipment] = $this->scenario();
        $revision = $this->createRevision($admin, $equipment, '2025-06-23');

        $this->actingAs($admin)
            ->put(route('equipment-revisions.update', $revision), $this->payload([
                'preparer_name' => 'Novo Preparador manual',
            ]))
            ->assertRedirect();

        $this->assertSame('Novo Preparador manual', $revision->refresh()->preparer_name);
    }

    public function test_member_cannot_manage_revisions(): void
    {
        [$organization, , $equipment] = $this->scenario();
        $member = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member->value]);

        $this->actingAs($member)
            ->post(route('equipments.revisions.store', $equipment), $this->payload())
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('equipments.show', $equipment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.manage_revisions', false)
                ->has('history_entries', 0));
    }

    public function test_required_fields_and_invalid_emission_type_are_validated(): void
    {
        [, $admin, $equipment] = $this->scenario();

        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), [])
            ->assertSessionHasErrors([
                'emission_type',
                'revision_date',
                'preparer_name',
                'reviewer_name',
                'approver_name',
                'releaser_name',
            ]);
    }

    public function test_responsible_names_are_trimmed_and_limited_to_180_characters(): void
    {
        [, $admin, $equipment] = $this->scenario();

        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), $this->payload([
                'preparer_name' => str_repeat('A', 181),
            ]))
            ->assertSessionHasErrors('preparer_name');

        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), $this->payload([
                'preparer_name' => '  Preparador manual  ',
            ]))
            ->assertRedirect();

        $this->assertSame('Preparador manual', EquipmentRevision::query()->firstOrFail()->preparer_name);
    }

    /** @return array{0:Organization, 1:User, 2:Equipment} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();

        return [$organization, $admin, $equipment];
    }

    private function createRevision(User $admin, Equipment $equipment, string $date): EquipmentRevision
    {
        $this->actingAs($admin)
            ->post(route('equipments.revisions.store', $equipment), $this->payload([
                'revision_date' => $date,
            ]))
            ->assertRedirect();

        return EquipmentRevision::query()->latest('id')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'emission_type' => 'C',
            'revision_date' => '2025-01-13',
            'preparer_name' => 'Preparador Histórico',
            'reviewer_name' => 'Verificador Histórico',
            'approver_name' => 'Aprovador Histórico',
            'releaser_name' => 'Liberador Histórico',
        ], $overrides);
    }
}
