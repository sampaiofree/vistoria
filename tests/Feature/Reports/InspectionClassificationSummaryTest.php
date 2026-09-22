<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Inspections\UpdateInspectionClassificationM2Links;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionClassificationSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_groups_only_published_assessments_from_the_current_inspection_and_uses_snapshots(): void
    {
        [$actor, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '10.25');
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '8.25');
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-3', 3, '1.5');
        $this->assessment($inspection, $equipment, DefectCategory::RoofCladding, 'TE-1', 1, null);
        $this->assessment($inspection, $equipment, DefectCategory::StructuralRecovery, 'IE-2', 2, '99', false);
        $other = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $this->assessment($other, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '100');

        $summary = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $tac = collect($summary['categories'])->firstWhere('code', 'TAC');
        $ta2 = collect($tac['rows'])->firstWhere('classification_code', 'TA-2');
        $civil = collect($summary['categories'])->firstWhere('code', 'CV');
        $tel = collect($summary['categories'])->firstWhere('code', 'TEL');

        $this->assertSame(2, $ta2['defect_count']);
        $this->assertSame(18.5, $ta2['quantity']['value']);
        $this->assertSame('18,50 m²', $tac['total']['label']);
        $this->assertSame('CV-3', $civil['most_critical']);
        $this->assertSame('1,50', $civil['total']['label']);
        $this->assertSame('—', $tel['total']['label']);
        $this->assertSame('EQ Histórico', $summary['equipment']['name']);

        app(UpdateInspectionClassificationM2Links::class)->handle($actor, $inspection, ['links' => [
            ['category' => 'TAC', 'classification_code' => 'TA-2', 'sap_number' => '0011503853'],
            ['category' => 'CV', 'classification_code' => 'CV-3', 'sap_number' => '0011503853'],
        ]]);
        $updated = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $updatedTac = collect($updated['categories'])->firstWhere('code', 'TAC');
        $this->assertSame('0011503853', collect($updatedTac['rows'])->firstWhere('classification_code', 'TA-2')['sap_m2_number']);
    }

    public function test_only_the_assigned_inspector_can_edit_m2_while_the_inspection_is_open(): void
    {
        [$actor, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '1');
        $payload = ['links' => [['category' => 'TAC', 'classification_code' => 'TA-2', 'sap_number' => '11503853']]];
        $this->actingAs($actor)->put(route('inspections.classification-m2-links.update', $inspection), $payload)->assertRedirect();

        $inspection->update(['status' => InspectionStatus::AwaitingReview]);
        $this->actingAs($actor)->put(route('inspections.classification-m2-links.update', $inspection), $payload)->assertForbidden();
    }

    /** @return array{User, Inspection, Equipment} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $actor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create(['name' => 'Equipamento atual']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
            'context_snapshot' => ['equipment' => ['name' => 'EQ Histórico', 'tag' => 'TAG-01', 'area_name' => 'Área A', 'subarea_name' => 'Subárea A', 'installation_location' => 'Pátio', 'abc_code' => 'A']],
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create(['responsibility' => InspectionResponsibility::Preparer]);

        return [$actor, $inspection, $equipment];
    }

    private function assessment(Inspection $inspection, Equipment $equipment, DefectCategory $category, string $code, int $priority, ?string $quantity, bool $complete = true): void
    {
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => $category]);
        DefectAssessment::factory()->forDefect($defect, $inspection)->create([
            'condition' => DefectAssessmentCondition::New,
            'status' => $complete ? 'complete' : 'draft',
            'classification_code' => $complete ? $code : null,
            'classification_priority' => $priority,
            'classification_snapshot' => ['code' => $code, 'name' => 'Histórica '.$code, 'severity_rank' => $priority],
            'quantity_snapshot' => $quantity === null ? null : ['total' => $quantity],
        ]);
    }
}
