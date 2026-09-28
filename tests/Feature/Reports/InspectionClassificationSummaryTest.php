<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Inspections\UpdateInspectionClassificationM2Links;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentClassificationMethod;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\AssessmentPhoto;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\InspectionSpecialAssessmentNote;
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
        $this->assertSame('10/05/2029', $ta2['m2_due_date']);
        $this->assertSame('18,50 m²', $tac['total']['label']);
        $this->assertSame('18,50', $tac['report_total']['display']);
        $this->assertSame('kg', $rec['report_total']['unit']);
        $this->assertSame('99,00', $rec['report_total']['display']);
        $this->assertSame('IE-2', $rec['most_critical']);
        $this->assertSame('#FFC000', $rec['most_critical_color']);
        $this->assertSame('10/05/2028', collect($rec['rows'])->firstWhere('classification_code', 'IE-2')['m2_due_date']);
        $this->assertSame('IE-0', $rec['report_rows'][0]['classification_code']);
        $this->assertSame('CV-3', $civil['most_critical']);
        $this->assertSame('1,50 m³', $cv3['quantity']['label']);
        $this->assertSame('10/05/2029', $cv3['m2_due_date']);
        $this->assertSame(1.5, $civil['report_total']['value']);
        $this->assertSame('1,50', $civil['report_total']['display']);
        $this->assertSame('m³', $civil['report_total']['unit']);
        $this->assertSame('CV-0', $civil['report_rows'][0]['classification_code']);
        $this->assertTrue($civil['report_rows'][0]['is_placeholder']);
        $this->assertSame('#000000', $civil['report_rows'][0]['color']);
        $this->assertSame(0, $civil['report_rows'][0]['defect_count']);
        $this->assertNull($civil['report_rows'][0]['quantity']['value']);
        $this->assertNull($civil['report_rows'][0]['sap_m2_number']);
        $this->assertSame('—', $tel['total']['label']);
        $this->assertNull($tel['report_total']['value']);
        $this->assertSame('TE-0', $tel['report_rows'][0]['classification_code']);
        $this->assertNull(collect($tel['rows'])->firstWhere('classification_code', 'TE-1')['m2_due_date']);
        $this->assertSame('EQ Histórico', $summary['equipment']['name']);
        $this->assertSame([
            'area' => 'Área A',
            'subarea' => 'Subárea A',
            'installation_location' => 'Pátio',
            'abc_code' => 'A',
            'inspection_date' => '10/05/2026',
            'inspection_date_input' => '2026-05-10',
            'equipment' => null,
            'tag' => 'TAG-01',
            'work_order' => '3500762191',
            'general_drawing' => null,
            'procedure_number' => null,
            'criticality' => 'Grave',
            'criticality_color' => '#FF0000',
        ], $summary['header']);

        app(UpdateInspectionClassificationM2Links::class)->handle($actor, $inspection, ['links' => [
            ['category' => 'TAC', 'classification_code' => 'TA-2', 'sap_number' => '11503853'],
            ['category' => 'CV', 'classification_code' => 'CV-3', 'sap_number' => '11503853'],
        ]]);
        $updated = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $updatedTac = collect($updated['categories'])->firstWhere('code', 'TAC');
        $this->assertSame('11503853', collect($updatedTac['rows'])->firstWhere('classification_code', 'TA-2')['sap_m2_number']);

        $this->actingAs($actor)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('content.classification_summary.header.inspection_date', '10/05/2026')
                ->where('content.classification_summary.header.criticality', 'Grave')
                ->where('content.classification_summary.header.criticality_color', '#FF0000')
                ->where('content.classification_summary.header.general_drawing', null)
                ->where('content.classification_summary.categories.0.rows.1.m2_due_date', '10/05/2029')
                ->where('content.classification_summary.categories.0.rows.1.sap_m2_number', '11503853'));
    }

    public function test_only_the_assigned_planner_can_edit_m2_during_planning(): void
    {
        [$actor, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '1');
        $payload = ['links' => [['category' => 'TAC', 'classification_code' => 'TA-2', 'sap_number' => '11503853']]];
        $this->actingAs($actor)->put(route('inspections.classification-m2-links.update', $inspection), $payload)->assertRedirect();

        $inspection->update(['status' => InspectionStatus::AwaitingReview]);
        $this->actingAs($actor)->put(route('inspections.classification-m2-links.update', $inspection), $payload)->assertForbidden();
    }

    public function test_it_sums_civil_quantities_from_published_snapshots_and_ignores_missing_values(): void
    {
        [, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-1', 1, '1.25');
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-2', 2, '2.75');
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-3', 3, null);

        $civil = collect(app(BuildInspectionClassificationSummary::class)->build($inspection)['categories'])
            ->firstWhere('code', 'CV');

        $this->assertSame(4.0, $civil['total']['value']);
        $this->assertSame('4,00 m³', $civil['total']['label']);
        $this->assertSame(4.0, $civil['report_total']['value']);
        $this->assertSame('4,00', $civil['report_total']['display']);
        $this->assertSame('m³', $civil['report_total']['unit']);

        [, $inspectionWithoutQuantities, $equipmentWithoutQuantities] = $this->scenario();
        $this->assessment($inspectionWithoutQuantities, $equipmentWithoutQuantities, DefectCategory::Civil, 'CV-1', 1, null);
        $civilWithoutQuantities = collect(app(BuildInspectionClassificationSummary::class)->build($inspectionWithoutQuantities)['categories'])
            ->firstWhere('code', 'CV');

        $this->assertNull($civilWithoutQuantities['total']['value']);
        $this->assertNull($civilWithoutQuantities['report_total']['value']);
    }

    public function test_it_lists_and_updates_special_assessment_notes_for_ci_engineering_and_solidary_structures(): void
    {
        [$actor, $inspection, $equipment] = $this->scenario();
        $ci = $this->specialAssessment($inspection, $equipment, DefectCategory::Civil, 'VT-CI-001', 10, [
            'is_unsafe_condition' => true,
        ]);
        $engineering = $this->specialAssessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'VT-END-001', 20, [
            'classification_method' => DefectAssessmentClassificationMethod::EngineeringNote,
        ]);
        $solidary = $this->specialAssessment($inspection, $equipment, DefectCategory::SolidaryStructures, 'VT-ES-001', 30);
        $this->specialAssessment($inspection, $equipment, DefectCategory::Civil, 'VT-COMBO-001', 40, [
            'is_unsafe_condition' => true,
            'classification_method' => DefectAssessmentClassificationMethod::EngineeringNote,
        ]);
        $this->specialAssessment($inspection, $equipment, DefectCategory::Civil, 'VT-CANCEL-001', 50, [
            'is_unsafe_condition' => true,
            'condition' => DefectAssessmentCondition::Canceled,
        ]);
        $this->specialAssessment($inspection, $equipment, DefectCategory::Civil, 'VT-DRAFT-001', 60, [
            'is_unsafe_condition' => true,
            'status' => 'draft',
        ]);
        $otherInspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $this->specialAssessment($otherInspection, $equipment, DefectCategory::Civil, 'VT-OUTRA-001', 1, [
            'is_unsafe_condition' => true,
        ]);
        foreach ([1, 2] as $position) {
            AssessmentPhoto::factory()->ready()->create([
                'public_id' => '01J0000000000000000000000'.$position,
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'defect_assessment_id' => $solidary->id,
                'position' => $position,
            ]);
        }

        $rows = app(BuildInspectionClassificationSummary::class)->build($inspection)['special_assessment_rows'];

        $this->assertSame(['VT-CI-001', 'VT-END-001', 'VT-ES-001', 'VT-COMBO-001'], collect($rows)->pluck('code')->all());
        $this->assertSame('ANEXO B - 1 E 2', collect($rows)->firstWhere('assessment_public_id', $solidary->public_id)['photos']);

        $this->actingAs($actor)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('content.classification_summary.special_assessment_rows.2.code', 'VT-ES-001')
                ->where('content.classification_summary.special_assessment_rows.2.photos', 'ANEXO B - 1 E 2'));

        $this->actingAs($actor)
            ->put(route('inspections.classification-m2-links.update', $inspection), [
                'links' => [],
                'special_rows' => [[
                    'assessment_public_id' => $ci->public_id,
                    'service' => 'Recuperação local',
                    'priority' => 'Alta',
                    'note' => '11943390',
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inspection_special_assessment_notes', [
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $ci->id,
            'service' => 'Recuperação local',
            'priority' => 'Alta',
            'note' => '11943390',
        ]);
        $this->assertCount(1, InspectionSpecialAssessmentNote::query()->get());

        $this->actingAs($actor)
            ->put(route('inspections.classification-m2-links.update', $inspection), [
                'links' => [],
                'special_rows' => [[
                    'assessment_public_id' => $ci->public_id,
                    'service' => '',
                    'priority' => '',
                    'note' => '',
                ]],
            ])
            ->assertRedirect();
        $this->assertDatabaseMissing('inspection_special_assessment_notes', ['defect_assessment_id' => $ci->id]);

        $inspection->update(['status' => InspectionStatus::AwaitingReview]);
        $this->actingAs($actor)
            ->put(route('inspections.classification-m2-links.update', $inspection), [
                'links' => [],
                'special_rows' => [[
                    'assessment_public_id' => $engineering->public_id,
                    'service' => 'Não autorizado',
                    'priority' => 'Alta',
                    'note' => '1',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_it_calculates_m2_due_dates_from_the_inspection_date_and_classification_deadlines(): void
    {
        [, $inspection, $equipment] = $this->scenario();

        foreach ([
            [DefectCategory::StructuralRecovery, 'IE-1', 1, '10/05/2027'],
            [DefectCategory::StructuralRecovery, 'IE-2', 2, '10/05/2028'],
            [DefectCategory::StructuralRecovery, 'IE-3', 3, '10/05/2029'],
            [DefectCategory::Civil, 'CV-1', 1, '10/05/2027'],
            [DefectCategory::Civil, 'CV-2', 2, '10/05/2028'],
            [DefectCategory::Civil, 'CV-3', 3, '10/05/2029'],
            [DefectCategory::AnticorrosiveTreatment, 'TA-1', 1, '10/05/2027'],
            [DefectCategory::AnticorrosiveTreatment, 'TA-2', 2, '10/05/2029'],
            [DefectCategory::AnticorrosiveTreatment, 'TA-3', 3, '10/05/2031'],
        ] as [$category, $code, $priority, $dueDate]) {
            $this->assessment($inspection, $equipment, $category, $code, $priority, '1');
        }

        $categories = collect(app(BuildInspectionClassificationSummary::class)->build($inspection)['categories']);
        foreach ([
            ['REC', 'IE-1', '10/05/2027'], ['REC', 'IE-2', '10/05/2028'], ['REC', 'IE-3', '10/05/2029'],
            ['CV', 'CV-1', '10/05/2027'], ['CV', 'CV-2', '10/05/2028'], ['CV', 'CV-3', '10/05/2029'],
            ['TAC', 'TA-1', '10/05/2027'], ['TAC', 'TA-2', '10/05/2029'], ['TAC', 'TA-3', '10/05/2031'],
        ] as [$category, $code, $dueDate]) {
            $row = collect($categories->firstWhere('code', $category)['rows'])->firstWhere('classification_code', $code);
            $this->assertSame($dueDate, $row['m2_due_date']);
        }
    }

    public function test_it_leaves_m2_due_dates_empty_when_the_classification_or_inspection_date_has_no_deadline(): void
    {
        [, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::StructuralRecovery, 'IE-4', 4, '1');
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-5', 5, '1');
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-4', 4, '1');
        $this->assessment($inspection, $equipment, DefectCategory::RoofCladding, 'TE-1', 1, null);

        $categories = collect(app(BuildInspectionClassificationSummary::class)->build($inspection)['categories']);
        foreach ([['REC', 'IE-4'], ['CV', 'CV-5'], ['TAC', 'TA-4'], ['TEL', 'TE-1']] as [$category, $code]) {
            $row = collect($categories->firstWhere('code', $category)['rows'])->firstWhere('classification_code', $code);
            $this->assertNull($row['m2_due_date']);
        }

        $inspection->update(['inspected_on' => null]);
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-1', 1, '1');
        $row = collect(collect(app(BuildInspectionClassificationSummary::class)->build($inspection->fresh())['categories'])->firstWhere('code', 'CV')['rows'])
            ->firstWhere('classification_code', 'CV-1');
        $this->assertNull($row['m2_due_date']);
    }

    public function test_m2_due_dates_recalculate_when_the_inspection_date_changes_without_changing_the_m2_note(): void
    {
        [$actor, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-2', 2, '1');
        app(UpdateInspectionClassificationM2Links::class)->handle($actor, $inspection, ['links' => [
            ['category' => 'CV', 'classification_code' => 'CV-2', 'sap_number' => '11503853'],
        ]]);

        $before = collect(collect(app(BuildInspectionClassificationSummary::class)->build($inspection)['categories'])->firstWhere('code', 'CV')['rows'])
            ->firstWhere('classification_code', 'CV-2');
        $this->assertSame('10/05/2028', $before['m2_due_date']);
        $this->assertSame('11503853', $before['sap_m2_number']);

        $inspection->update(['inspected_on' => '2027-01-20']);
        $after = collect(collect(app(BuildInspectionClassificationSummary::class)->build($inspection->fresh())['categories'])->firstWhere('code', 'CV')['rows'])
            ->firstWhere('classification_code', 'CV-2');
        $this->assertSame('20/01/2029', $after['m2_due_date']);
        $this->assertSame('11503853', $after['sap_m2_number']);
    }

    public function test_criticality_uses_the_most_severe_non_tac_classification(): void
    {
        [, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-1', 1, '1');
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-4', 4, '1');
        $this->assessment($inspection, $equipment, DefectCategory::StructuralRecovery, 'IE-1', 1, '1');

        $header = app(BuildInspectionClassificationSummary::class)->build($inspection)['header'];
        $this->assertSame('Grave', $header['criticality']);
        $this->assertSame('#FF0000', $header['criticality_color']);
    }

    public function test_criticality_returns_the_same_label_for_non_tac_ties(): void
    {
        [, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-2', 2, '1');
        $this->assessment($inspection, $equipment, DefectCategory::StructuralRecovery, 'IE-2', 2, '1');

        $header = app(BuildInspectionClassificationSummary::class)->build($inspection)['header'];
        $this->assertSame('Alta', $header['criticality']);
        $this->assertSame('#FFC000', $header['criticality_color']);
    }

    public function test_criticality_uses_tac_only_when_there_are_no_other_classified_categories(): void
    {
        [, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::AnticorrosiveTreatment, 'TA-4', 4, '1');

        $header = app(BuildInspectionClassificationSummary::class)->build($inspection)['header'];
        $this->assertSame('Baixa', $header['criticality']);
        $this->assertSame('#92D050', $header['criticality_color']);
    }

    public function test_criticality_uses_the_historical_snapshot_priority_when_the_persisted_priority_is_missing(): void
    {
        [, $inspection, $equipment] = $this->scenario();
        $this->assessment($inspection, $equipment, DefectCategory::Civil, 'CV-3', 3, '1');
        DefectAssessment::query()->where('inspection_id', $inspection->id)->update(['classification_priority' => null]);

        $header = app(BuildInspectionClassificationSummary::class)->build($inspection)['header'];
        $this->assertSame('Média', $header['criticality']);
        $this->assertSame('#FFFF00', $header['criticality_color']);
    }

    public function test_criticality_uses_the_native_gut_result_palette_for_every_priority(): void
    {
        foreach ([
            1 => ['Grave', '#FF0000'],
            2 => ['Alta', '#FFC000'],
            3 => ['Média', '#FFFF00'],
            4 => ['Baixa', '#92D050'],
            5 => ['Muito Baixa', '#0070C0'],
        ] as $priority => [$label, $color]) {
            [, $inspection, $equipment] = $this->scenario();
            $this->assessment($inspection, $equipment, DefectCategory::Civil, "CV-{$priority}", $priority, '1');

            $header = app(BuildInspectionClassificationSummary::class)->build($inspection)['header'];
            $this->assertSame($label, $header['criticality']);
            $this->assertSame($color, $header['criticality_color']);
        }
    }

    public function test_the_classification_header_uses_maintenance_snapshots_and_persists_its_manual_fields(): void
    {
        [$actor, $inspection, $equipment] = $this->scenario();
        $equipment->update([
            'area_name' => 'Área alterada',
            'subarea_name' => 'Subárea alterada',
            'installation_location' => 'Outro local',
            'abc_code' => 'C',
            'tag' => 'TAG-ALTERADA',
        ]);

        $this->actingAs($actor)
            ->put(route('inspections.classification-header.update', $inspection), [
                'general_drawing' => 'U030600-S-551729',
                'procedure_number' => 'T000000-S-2PO006_R-04',
                'inspected_on' => '2026-05-15',
            ])
            ->assertRedirect();

        $inspection->refresh();
        $this->assertSame('U030600-S-551729', $inspection->general_drawing);
        $this->assertSame('T000000-S-2PO006_R-04', $inspection->procedure_number);
        $this->assertSame('2026-05-15', $inspection->inspected_on?->toDateString());

        $header = app(BuildInspectionClassificationSummary::class)->build($inspection)['header'];
        $this->assertSame('Área A', $header['area']);
        $this->assertSame('Subárea A', $header['subarea']);
        $this->assertSame('Pátio', $header['installation_location']);
        $this->assertSame('A', $header['abc_code']);
        $this->assertSame('TAG-01', $header['tag']);
        $this->assertSame('3500762191', $header['work_order']);
        $this->assertSame('15/05/2026', $header['inspection_date']);
        $this->assertSame('U030600-S-551729', $header['general_drawing']);
        $this->assertSame('T000000-S-2PO006_R-04', $header['procedure_number']);
        $this->assertNull($header['equipment']);
        $this->assertNull($header['criticality']);
        $this->assertNull($header['criticality_color']);
    }

    public function test_only_users_who_can_manage_m2_can_update_the_classification_header(): void
    {
        [$actor, $inspection] = $this->scenario();
        $payload = [
            'general_drawing' => 'U030600-S-551729',
            'procedure_number' => 'T000000-S-2PO006_R-04',
            'inspected_on' => '2026-05-15',
        ];

        $this->actingAs($actor)
            ->put(route('inspections.classification-header.update', $inspection), $payload)
            ->assertRedirect();

        $inspection->update(['status' => InspectionStatus::AwaitingReview]);
        $this->actingAs($actor)
            ->put(route('inspections.classification-header.update', $inspection), $payload)
            ->assertForbidden();
    }

    public function test_the_classification_header_validates_its_manual_fields(): void
    {
        [$actor, $inspection] = $this->scenario();

        $this->actingAs($actor)
            ->from(route('inspections.classifications', $inspection))
            ->put(route('inspections.classification-header.update', $inspection), [
                'general_drawing' => str_repeat('A', 151),
                'procedure_number' => str_repeat('B', 151),
                'inspected_on' => 'invalid-date',
            ])
            ->assertRedirect(route('inspections.classifications', $inspection))
            ->assertSessionHasErrors(['general_drawing', 'procedure_number', 'inspected_on']);
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
                ->where('content.classification_summary.header.area', 'Área A')
                ->where('content.classification_summary.header.equipment', null)
                ->where('content.classification_summary.header.criticality', 'Alta')
                ->where('content.classification_summary.header.criticality_color', '#FFC000')
                ->where('content.classification_summary.header_update_url', route('inspections.classification-header.update', $inspection))
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
            'inspection_date_input' => '2026-05-12',
            'equipment' => null,
            'tag' => '—',
            'work_order' => '—',
            'general_drawing' => null,
            'procedure_number' => null,
            'criticality' => null,
            'criticality_color' => null,
        ], $summary['header']);
    }

    /** @return array{User, Inspection, Equipment} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $actor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $equipment = Equipment::factory()->for($organization)->create([
            'name' => 'Equipamento atual',
            'description' => 'Descrição atual',
        ]);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::AwaitingM2,
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

    /** @param array<string, mixed> $attributes */
    private function specialAssessment(Inspection $inspection, Equipment $equipment, DefectCategory $category, string $code, int $sequence, array $attributes = []): DefectAssessment
    {
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'category' => $category,
            'code' => $code,
            'sequence_number' => $sequence,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->create([
            'condition' => DefectAssessmentCondition::New,
            'status' => 'complete',
            'classification_method' => DefectAssessmentClassificationMethod::Gut,
            ...$attributes,
        ]);
    }
}
