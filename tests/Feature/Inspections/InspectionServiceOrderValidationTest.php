<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionServiceOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_creation_requires_exactly_ten_digits_and_preserves_leading_zeroes(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $payload = [
            'equipment_id' => $equipment->id,
            'inspector_id' => $inspector->id,
            'planned_start_on' => '2026-10-10',
            'planned_end_on' => '2026-10-12',
        ];

        foreach ([null, '', '123456789', '12345678901', '12345A7890', '12345-7890'] as $invalid) {
            $this->actingAs($planner)->post(route('inspections.store'), [
                'inspections' => [[...$payload, 'service_order' => $invalid]],
            ])->assertSessionHasErrors('inspections.0.service_order');
        }

        $otherEquipment = Equipment::factory()->for($organization)->create();
        $this->actingAs($planner)->post(route('inspections.store'), [
            'inspections' => [
                [...$payload, 'service_order' => '0000000001'],
                [...$payload, 'equipment_id' => $otherEquipment->id, 'service_order' => '123456789'],
            ],
        ])->assertSessionHasErrors('inspections.1.service_order');
        $this->assertDatabaseCount('inspections', 0);

        $this->actingAs($planner)->post(route('inspections.store'), [
            'inspections' => [[...$payload, 'service_order' => '0000000001']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('0000000001', Inspection::query()->sole()->service_order);
    }

    public function test_planning_update_preserves_legacy_order_until_it_is_changed(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Planned,
            'service_order' => ' OS-LEGACY ',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $planner)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
            'is_primary' => true,
        ]);
        $payload = [
            'equipment_id' => $equipment->id,
            'inspector_id' => $inspector->id,
            'planned_start_on' => '2026-10-10',
            'planned_end_on' => '2026-10-12',
        ];

        $this->actingAs($planner)->put(route('inspections.update', $inspection), [
            ...$payload, 'service_order' => ' OS-LEGACY ',
        ])->assertSessionHasNoErrors();
        $this->assertSame(' OS-LEGACY ', $inspection->fresh()->service_order);
        $this->assertSame('2026-10-12', $inspection->fresh()->planned_end_on?->toDateString());

        foreach ([null, '', '123456789', '12345678901', '12345A7890', '12345-7890'] as $invalid) {
            $this->actingAs($planner)->put(route('inspections.update', $inspection), [
                ...$payload, 'service_order' => $invalid,
            ])->assertSessionHasErrors('service_order');
        }

        $this->assertSame(' OS-LEGACY ', $inspection->fresh()->service_order);

        $inspection->update(['service_order' => null]);
        $this->actingAs($planner)->put(route('inspections.update', $inspection), [
            ...$payload, 'planned_end_on' => '2026-10-13', 'service_order' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($inspection->fresh()->service_order);
        $this->assertSame('2026-10-13', $inspection->fresh()->planned_end_on?->toDateString());

        $this->actingAs($planner)->put(route('inspections.update', $inspection), [
            ...$payload, 'service_order' => '0000000001',
        ])->assertSessionHasNoErrors();
        $this->assertSame('0000000001', $inspection->fresh()->service_order);
    }

    public function test_report_metadata_preserves_legacy_order_until_it_is_changed(): void
    {
        $organization = Organization::factory()->create();
        $reviewer = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Reviewer]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create(['status' => InspectionStatus::InReview, 'service_order' => ' OS-LEGACY ']);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create([
            'responsibility' => InspectionResponsibility::Approver,
        ]);
        $payload = [
            'emission_type' => EquipmentRevisionEmissionType::ForApproval->value,
            'report_date' => null,
            'designer_i_report_number' => 'PROJ-001',
            'first_page_text_template' => 'Título revisado',
            'report_equipment_name' => 'Equipamento do relatório',
        ];

        $this->actingAs($reviewer)->put(route('inspections.report-metadata.update', $inspection), [
            ...$payload, 'service_order' => ' OS-LEGACY ',
        ])->assertSessionHasNoErrors();
        $this->assertSame(' OS-LEGACY ', $inspection->fresh()->service_order);
        $this->assertSame('Título revisado', $inspection->fresh()->first_page_text_template);

        foreach ([null, '', '123456789', '12345678901', '12345A7890', '12345-7890'] as $invalid) {
            $this->actingAs($reviewer)->put(route('inspections.report-metadata.update', $inspection), [
                ...$payload, 'service_order' => $invalid,
            ])->assertSessionHasErrors('service_order');
        }

        $this->assertSame(' OS-LEGACY ', $inspection->fresh()->service_order);

        $inspection->update(['service_order' => null]);
        $this->actingAs($reviewer)->put(route('inspections.report-metadata.update', $inspection), [
            ...$payload, 'first_page_text_template' => 'Outro título', 'service_order' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($inspection->fresh()->service_order);
        $this->assertSame('Outro título', $inspection->fresh()->first_page_text_template);

        $this->actingAs($reviewer)->put(route('inspections.report-metadata.update', $inspection), [
            ...$payload, 'service_order' => '0000000001',
        ])->assertSessionHasNoErrors();
        $this->assertSame('0000000001', $inspection->fresh()->service_order);
    }
}
