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
        $this->assessment($inspection, $equipment, DefectCategory::StructuralRecovery, 'IE-2', 2, '99');
        $this->assessment($inspection, $equipment, DefectCategory::StructuralRecovery, 'IE-3', 3, '12', false);
        $other = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $this->assessment($other, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '100');

        $summary = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $tac = collect($summary['categories'])->firstWhere('code', 'TAC');
        $ta2 = collect($tac['rows'])->firstWhere('classification_code', 'TA-2');
        $civil = collect($summary['categories'])->firstWhere('code', 'CV');
        $cv3 = collect($civil['rows'])->firstWhere('classification_code', 'CV-3');
        $rec = collect($summary['categories'])->firstWhere('code', 'REC');
        $tel = collect($summary['categories'])->firstWhere('code', 'TEL');

        $this->assertSame(2, $ta2['defect_count']);
        $this->assertSame(18.5, $ta2['quantity']['value']);
        $this->assertSame('#FFC000', $ta2['color']);
        $this->assertSame('18,50 m²', $tac['total']['label']);
        $this->assertSame('18,50', $tac['report_total']['display']);
        $this->assertSame('kg', $rec['report_total']['unit']);
        $this->assertSame('99,00', $rec['report_total']['display']);
        $this->assertSame('IE-2', $rec['most_critical']);
        $this->assertSame('#FFC000', $rec['most_critical_color']);
        $this->assertSame('IE-0', $rec['report_rows'][0]['classification_code']);
        $this->assertSame('CV-3', $civil['most_critical']);
        $this->assertSame('1,50', $cv3['quantity']['label']);
        $this->assertNull($civil['report_total']['value']);
        $this->assertSame('CV-0', $civil['report_rows'][0]['classification_code']);
        $this->assertTrue($civil['report_rows'][0]['is_placeholder']);
        $this->assertSame('#000000', $civil['report_rows'][0]['color']);
        $this->assertSame(0, $civil['report_rows'][0]['defect_count']);
        $this->assertNull($civil['report_rows'][0]['quantity']['value']);
        $this->assertNull($civil['report_rows'][0]['sap_m2_number']);
        $this->assertSame('—', $tel['total']['label']);
        $this->assertNull($tel['report_total']['value']);
        $this->assertSame('TE-0', $tel['report_rows'][0]['classification_code']);
        $this->assertSame('EQ Histórico', $summary['equipment']['name']);
        $this->assertSame([
            'area' => 'Área A',
            'subarea' => 'Subárea A',
            'installation_location' => 'Pátio',
            'abc_code' => 'A',
            'inspection_date' => '11/05/2026',
            'equipment' => 'Descrição histórica',
            'tag' => 'TAG-01',
            'work_order' => '3500762191',
        ], $summary['header']);

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

    public function test_classifications_tab_exposes_the_editable_summary_after_defects(): void
    {
        [$actor, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '1');

        $this->actingAs($actor)
            ->get(route('inspections.classifications', $inspection))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('active_tab', 'classifications')
                ->where('tabs.3.key', 'classifications')
                ->where('content.classification_summary.can_edit_m2', true)
                ->where('content.classification_summary.categories.0.rows.1.classification_code', 'TA-2'));
    }

    public function test_it_uses_the_inspection_date_and_safe_placeholders_when_the_start_timestamp_is_unavailable(): void
    {
        [, $inspection] = $this->scenario();
        $inspection->update([
            'started_at' => null,
            'inspected_on' => '2026-05-12',
            'service_order' => null,
            'context_snapshot' => ['equipment' => [
                'area_name' => null,
                'subarea_name' => '',
                'installation_location' => null,
                'abc_code' => '',
                'description' => null,
                'tag' => '',
            ]],
        ]);

        $summary = app(BuildInspectionClassificationSummary::class)->build($inspection->fresh());

        $this->assertSame([
            'area' => '—',
            'subarea' => '—',
            'installation_location' => '—',
            'abc_code' => '—',
            'inspection_date' => '12/05/2026',
            'equipment' => '—',
            'tag' => '—',
            'work_order' => '—',
        ], $summary['header']);
    }

    /** @return array{User, Inspection, Equipment} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $actor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create([
            'name' => 'Equipamento atual',
            'description' => 'Descrição atual',
        ]);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
            'started_at' => '2026-05-11 10:30:00',
            'inspected_on' => '2026-05-10',
            'service_order' => '3500762191',
            'context_snapshot' => ['equipment' => ['name' => 'EQ Histórico', 'description' => 'Descrição histórica', 'tag' => 'TAG-01', 'area_name' => 'Área A', 'subarea_name' => 'Subárea A', 'installation_location' => 'Pátio', 'abc_code' => 'A']],
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
