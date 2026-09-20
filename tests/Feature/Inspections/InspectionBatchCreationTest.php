<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionBatchCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_planner_previews_and_confirms_a_batch_with_the_expected_responsibles(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::Member,
            'operational_role' => OperationalRole::Planner,
        ]);
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $firstEquipment = Equipment::factory()->for($organization)->create();
        $secondEquipment = Equipment::factory()->for($organization)->create();

        $response = $this->actingAs($planner)->post(route('inspections.store'), [
            'inspections' => [
                $this->record($firstEquipment, $inspector, 'OS-001'),
                $this->record($secondEquipment, $inspector, 'OS-002'),
            ],
        ]);

        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
        $token = $query['preview'] ?? null;
        $this->assertIsString($token);

        $this->actingAs($planner)
            ->get(route('inspections.create', ['preview' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Create')
                ->where('preview.token', $token)
                ->has('preview.inspections', 2)
                ->has('inspectors', 1)
                ->where('inspectors.0.id', $inspector->id));

        $this->actingAs($planner)
            ->post(route('inspections.confirm'), ['token' => $token])
            ->assertRedirect(route('inspections.index'));

        $this->actingAs($planner)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Index')
                ->where('inspections.data.0.stage_responsibles.planner', $planner->name)
                ->where('inspections.data.0.stage_responsibles.inspector', $inspector->name)
                ->where('inspections.data.0.stage_responsibles.reviewer', null)
                ->where('inspections.data.0.stage_responsibles.releaser', null)
                ->missing('inspections.data.0.primary_responsible'));

        $this->assertDatabaseCount('inspections', 2);
        $inspection = Inspection::query()->where('equipment_id', $firstEquipment->id)->firstOrFail();
        $this->assertSame(InspectionStatus::Planned, $inspection->status);
        $this->assertSame('OS-001', $inspection->service_order);
        $this->assertNull($inspection->atmospheric_classification);
        $this->assertSame('2026-10-10', $inspection->planned_start_on?->toDateString());
        $this->assertSame('2026-10-12', $inspection->planned_end_on?->toDateString());
        $this->assertDatabaseHas('inspection_responsibles', [
            'inspection_id' => $inspection->id,
            'user_id' => $planner->id,
            'responsibility' => InspectionResponsibility::Preparer->value,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('inspection_responsibles', [
            'inspection_id' => $inspection->id,
            'user_id' => $inspector->id,
            'responsibility' => InspectionResponsibility::Reviewer->value,
            'is_primary' => true,
        ]);
    }

    public function test_only_an_active_planner_can_access_batch_creation(): void
    {
        $organization = Organization::factory()->create();
        $users = [
            User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin, 'operational_role' => null]),
            User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]),
            User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]),
            User::factory()->for($organization)->create(['operational_role' => OperationalRole::Releaser]),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get(route('inspections.create'))->assertForbidden();
        }

        $inactivePlanner = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Planner,
            'status' => UserStatus::Inactive,
        ]);
        $this->assertFalse($inactivePlanner->can('create', Inspection::class));
    }

    public function test_preview_rejects_invalid_or_repeated_rows_without_creating_inspections(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();

        $this->actingAs($planner)
            ->from(route('inspections.create'))
            ->post(route('inspections.store'), [
                'inspections' => [
                    $this->record($equipment, $inspector),
                    $this->record($equipment, $inspector, 'OS-002'),
                ],
            ])
            ->assertRedirect(route('inspections.create'))
            ->assertSessionHasErrors([
                'inspections.1.equipment_id',
            ]);

        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_planner_searches_eligible_equipment_remotely_without_receiving_the_full_catalog(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $matchingEquipment = Equipment::factory()->for($organization)->create([
            'maintenance_item_code' => 'ITEM-1000',
            'tag' => 'TAG-ALFA',
            'normalized_tag' => 'TAG-ALFA',
            'description' => 'Bomba de alimentação',
        ]);
        Equipment::factory()->for($organization)->inactive()->create([
            'maintenance_item_code' => 'ITEM-1001',
            'tag' => 'TAG-INATIVA',
            'normalized_tag' => 'TAG-INATIVA',
        ]);
        Equipment::factory()->create([
            'maintenance_item_code' => 'ITEM-1002',
            'tag' => 'TAG-OUTRA-EMPRESA',
            'normalized_tag' => 'TAG-OUTRA-EMPRESA',
        ]);

        $this->actingAs($planner)
            ->getJson(route('inspections.equipment-options', ['search' => 'Bomba']))
            ->assertOk()
            ->assertJsonCount(1, 'equipment')
            ->assertJsonPath('equipment.0.id', $matchingEquipment->id)
            ->assertJsonPath('equipment.0.maintenance_item_code', 'ITEM-1000');

        $this->actingAs($planner)
            ->getJson(route('inspections.equipment-options', ['search' => 'T']))
            ->assertOk()
            ->assertJsonCount(0, 'equipment');
    }

    public function test_fresh_batch_creation_does_not_include_the_equipment_catalog_in_the_page_payload(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        Equipment::factory()->count(3)->for($organization)->create();

        $this->actingAs($planner)
            ->get(route('inspections.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Create')
                ->has('selected_equipment', 0)
                ->where('equipment_search_url', route('inspections.equipment-options'))
                ->missing('equipment'));
    }

    public function test_list_uses_the_first_stage_responsible_when_a_legacy_inspection_has_no_primary(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();

        InspectionResponsible::factory()
            ->forInspection($inspection, $planner)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => false,
            ]);

        $this->actingAs($planner)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspections.data.0.stage_responsibles.planner', $planner->name)
                ->where('inspections.data.0.stage_responsibles.inspector', null)
                ->where('inspections.data.0.stage_responsibles.reviewer', null)
                ->where('inspections.data.0.stage_responsibles.releaser', null));
    }

    /** @return array<string, int|string> */
    private function record(Equipment $equipment, User $inspector, string $serviceOrder = 'OS-001'): array
    {
        return [
            'equipment_id' => $equipment->id,
            'service_order' => $serviceOrder,
            'planned_start_on' => '2026-10-10',
            'planned_end_on' => '2026-10-12',
            'inspector_id' => $inspector->id,
        ];
    }
}
