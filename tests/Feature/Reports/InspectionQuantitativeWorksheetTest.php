<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\DefectAssessmentClassificationMethod;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionClassificationM2Link;
use App\Models\InspectionResponsible;
use App\Models\InspectionSpecialAssessmentNote;
use App\Models\Organization;
use App\Models\SapM2Note;
use App\Models\User;
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Services\Reports\BuildInspectionQuantitativeWorksheet;
use App\Services\Reports\ExportInspectionQuantitativeWorksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InspectionQuantitativeWorksheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_includes_only_civil_rec_and_tel_with_special_assessments_without_maps_or_quantities(): void
    {
        [$user, $inspection] = $this->context();
        // Create out of order, with more than one quantity item in a single snapshot.
        $rec = $this->assessment($inspection, DefectCategory::StructuralRecovery, 4, [
            'is_unsafe_condition' => true,
            'quantity_snapshot' => ['total' => '12.50', 'items' => [['total' => '5'], ['total' => '7.50']]],
        ]);
        $this->assessment($inspection, DefectCategory::Civil, 1);
        $this->assessment($inspection, DefectCategory::AnticorrosiveTreatment, 2);
        $this->assessment($inspection, DefectCategory::RoofCladding, 3, ['classification_code' => 'TE-1']);
        $this->assessment($inspection, DefectCategory::SolidaryStructures, 5);
        $this->assessment($inspection, DefectCategory::Civil, 6, ['classification_method' => DefectAssessmentClassificationMethod::EngineeringNote]);

        $this->actingAs($user)->get(route('inspections.quantitative', $inspection))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Inspections/Quantitative')
                ->has('worksheet.columns', 14)->has('worksheet.rows', 4)
                ->where('worksheet.columns.1.key', 'discipline')
                ->where('worksheet.columns.1.label', 'DISCIPLINA')
                ->where('worksheet.columns.1.width', 14)
                ->where('worksheet.header.row_count.value', 4)
                ->where('worksheet.rows.0.category', 'CV')
                ->where('worksheet.rows.0.cells.discipline.value', 'CIVIL')
                ->where('worksheet.rows.1.cells.discipline.value', 'TEL')
                ->where('worksheet.rows.1.cells.classification.value', 'TE-1')
                ->where('worksheet.rows.1.cells.gravity.value', null)
                ->where('worksheet.rows.2.assessment_public_id', $rec->public_id)
                ->where('worksheet.rows.2.cells.discipline.value', 'REC')
                ->where('worksheet.rows.2.cells.line.value', 3)
                ->where('worksheet.rows.2.cells.quantity.display', '12,50 kg')
                ->where('worksheet.rows.3.cells.line.value', 4)
                ->where('worksheet.rows.3.cells.gravity.value', null)
                ->where('worksheet.rows.3.cells.classification.value', null)
                ->missing('worksheet.rows.0.cells.long_text')
                ->missing('worksheet.rows.0.cells.note_description')
                ->where('inspection_navigation.items.6.key', 'report')
                ->where('inspection_navigation.items.7.key', 'quantitative')
                ->where('inspection_navigation.items.7.active', true)
                ->where('tabs.7.key', 'quantitative')
                ->where('export_url', route('inspections.quantitative.export', $inspection)));
    }

    public function test_discipline_and_filter_both_use_the_historical_category(): void
    {
        [, $inspection] = $this->context();
        $this->assessment($inspection, DefectCategory::Civil, 1, ['defect_snapshot' => ['defect' => ['category' => 'TAC']]]);
        $this->assessment($inspection, DefectCategory::StructuralRecovery, 2, ['defect_snapshot' => ['defect' => ['category' => 'ES']]]);
        $historicalCivil = $this->assessment($inspection, DefectCategory::AnticorrosiveTreatment, 3, ['defect_snapshot' => ['defect' => ['category' => 'CV']]]);
        $this->assessment($inspection, DefectCategory::Civil, 4, ['defect_snapshot' => ['defect' => ['category' => 'CIVIL']]]);
        $this->assessment($inspection, DefectCategory::RoofCladding, 5, ['defect_snapshot' => null]);
        $sheet = $this->build($inspection);

        $this->assertSame(3, $sheet['header']['row_count']['value']);
        $this->assertSame($historicalCivil->public_id, $sheet['rows'][0]['assessment_public_id']);
        $this->assertSame(['CIVIL', 'CIVIL', 'TEL'], array_column(array_column(array_column($sheet['rows'], 'cells'), 'discipline'), 'value'));
        $this->assertSame([1, 2, 3], array_column(array_column(array_column($sheet['rows'], 'cells'), 'line'), 'value'));
    }

    public function test_only_active_published_assessments_of_this_inspection_and_organization_are_included(): void
    {
        [, $inspection] = $this->context();
        foreach ([DefectAssessmentCondition::New, DefectAssessmentCondition::Reinspected, DefectAssessmentCondition::Reclassified] as $index => $condition) {
            $this->assessment($inspection, DefectCategory::Civil, $index + 1, ['condition' => $condition]);
        }
        $this->assessment($inspection, DefectCategory::Civil, 4, ['status' => 'draft']);
        $this->assessment($inspection, DefectCategory::Civil, 5, ['condition' => DefectAssessmentCondition::Canceled]);
        $this->assessment($inspection, DefectCategory::Civil, 6, ['condition' => DefectAssessmentCondition::CanceledWithoutRepair]);
        $this->assessment($inspection, DefectCategory::Civil, 7, ['condition' => DefectAssessmentCondition::Treated]);
        $otherInspection = Inspection::factory()->forEquipment($inspection->equipment)->create();
        $this->assessment($otherInspection, DefectCategory::Civil, 8);
        [, $foreignInspection] = $this->context();
        $this->assessment($foreignInspection, DefectCategory::Civil, 9);

        $rows = $this->build($inspection)['rows'];
        $this->assertCount(3, $rows);
        $this->assertSame(['Nova', 'Reinspecionada', 'Reclassificada'], array_map(fn ($row) => $row['cells']['condition']['value'], $rows));
        $this->assertSame([1, 2, 3], array_map(fn ($row) => $row['cells']['line']['value'], $rows));
    }

    public function test_snapshots_supply_code_totals_classification_and_criteria_while_reserved_fields_stay_empty(): void
    {
        [$user, $inspection] = $this->context();
        $assessment = $this->assessment($inspection, DefectCategory::StructuralRecovery, 1, [
            'gravity' => 1, 'urgency' => 1, 'trend' => 1, 'gut_score' => 1,
            'classification_code' => 'IE-5',
            'gut_snapshot' => ['score' => 75, 'criteria' => [
                'gravity' => ['score' => 3, 'asset_impact' => ['score' => 3], 'safety_impact' => ['score' => 2]],
                'urgency' => ['score' => 5], 'trend' => ['score' => 5, 'group' => ['label' => 'Deformação']],
            ]],
            'classification_snapshot' => ['code' => 'IE-1', 'color' => '#FF0000'],
            'quantity_snapshot' => ['total' => '1234.567', 'items' => [['total' => '99999']]],
            'comment' => 'Não é texto longo da planilha', 'item_description' => 'Não é descrição da nota',
        ]);
        $assessment->defect->update(['code' => 'CODIGO-ATUAL', 'status' => 'repaired']);
        $inspection->equipment->update(['tag' => 'TAG-ATUAL', 'installation_location' => 'LOCAL ATUAL']);
        $note = SapM2Note::query()->create(['organization_id' => $inspection->organization_id, 'equipment_id' => $inspection->equipment_id, 'sap_number' => '00123456']);
        InspectionClassificationM2Link::query()->create(['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'category' => 'REC', 'classification_code' => 'IE-1', 'sap_m2_note_id' => $note->id]);
        InspectionSpecialAssessmentNote::query()->create(['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'defect_assessment_id' => $assessment->id, 'note' => 'NOTA END', 'created_by' => $user->id, 'updated_by' => $user->id]);

        $sheet = $this->build($inspection);
        $cells = $sheet['rows'][0]['cells'];
        $this->assertSame('TAG-HISTORICO', $sheet['header']['tag']['value']);
        $this->assertSame('LOCAL HISTÓRICO', $sheet['header']['installation_location']['value']);
        $this->assertSame('10/05/2026', $sheet['header']['inspection_date']['display']);
        $this->assertSame('REC-001', $cells['code']['value']);
        $this->assertSame('1.234,57 kg', $cells['quantity']['display']);
        $this->assertSame('IMP. ATIV.', $cells['impact']['value']);
        $this->assertSame([3, 5, 5, 75], array_map(fn ($key) => $cells[$key]['value'], ['gravity', 'urgency', 'trend', 'gut_score']));
        $this->assertSame('Deformação', $cells['damage']['value']);
        $this->assertSame('IE-1', $cells['classification']['value']);
        $this->assertSame('#FF0000', $cells['gut_score']['background']);
        $this->assertSame('#FFFFFF', $cells['gut_score']['color']);
        $this->assertSame('10/05/2027', $cells['due_date']['display']);
        foreach (['report', 'environment', 'mandator'] as $key) {
            $this->assertSame(['value' => null, 'display' => ''], $sheet['header'][$key]);
        }
        $this->assertSame(['value' => null, 'display' => ''], $cells['note']);
        $this->assertArrayNotHasKey('note_description', $cells);
        $this->assertArrayNotHasKey('long_text', $cells);
    }

    public function test_missing_snapshots_do_not_invent_quantities_or_criteria_and_zero_is_preserved(): void
    {
        [, $inspection] = $this->context();
        $this->assessment($inspection, DefectCategory::Civil, 1, ['defect_snapshot' => null]);
        $this->assessment($inspection, DefectCategory::Civil, 2, ['quantity_snapshot' => ['total' => '0']]);
        $this->assessment($inspection, DefectCategory::Civil, 3, ['quantity_snapshot' => ['total' => '2.50']]);
        $inspection->update(['inspected_on' => null, 'context_snapshot' => []]);
        $sheet = $this->build($inspection);
        $this->assertNull($sheet['header']['tag']['value']);
        $this->assertNull($sheet['header']['inspection_date']['value']);
        $this->assertSame('CV-001', $sheet['rows'][0]['cells']['code']['value']);
        foreach (['quantity', 'gravity', 'urgency', 'trend', 'gut_score', 'impact', 'damage', 'due_date'] as $key) {
            $this->assertSame('', $sheet['rows'][0]['cells'][$key]['display']);
        }
        $this->assertNull($sheet['rows'][0]['cells']['gut_score']['background']);
        $this->assertSame('0,00 m³', $sheet['rows'][1]['cells']['quantity']['display']);
        $this->assertSame('2,50 m³', $sheet['rows'][2]['cells']['quantity']['display']);
    }

    #[DataProvider('gravityLegends')]
    public function test_impact_uses_the_shared_report_rule(array $gravity, string $expected): void
    {
        [, $inspection] = $this->context();
        $this->assessment($inspection, DefectCategory::Civil, 1, ['gut_snapshot' => ['criteria' => ['gravity' => ['score' => 4, ...$gravity], 'trend' => ['group' => ['label' => 'Fissuração']]]]]);
        $cells = $this->build($inspection)['rows'][0]['cells'];
        $this->assertSame($expected, $cells['impact']['value']);
        $this->assertSame('Fissuração', $cells['damage']['value']);
    }

    public static function gravityLegends(): array
    {
        return [
            'ativo' => [['asset_impact' => ['score' => 4], 'safety_impact' => ['score' => 2]], 'IMP. ATIV.'],
            'segurança' => [['asset_impact' => ['score' => 2], 'safety_impact' => ['score' => 4]], 'IMP. SEG.'],
            'empate' => [['asset_impact' => ['score' => 4], 'safety_impact' => ['score' => 4]], 'IMP. ATIV. / IMP. SEG.'],
            'histórico incompleto' => [[], 'IMP. ATIV. / IMP. SEG.'],
            'segurança não se aplica' => [['asset_impact' => ['score' => 4], 'safety_impact' => ['code' => 'not_applicable', 'score' => null]], 'IMP. ATIV.'],
            'ativo não se aplica' => [['asset_impact' => ['code' => 'not_applicable', 'score' => null], 'safety_impact' => ['score' => 4]], 'IMP. SEG.'],
        ];
    }

    public function test_due_dates_match_m2_and_change_with_the_inspection_date(): void
    {
        [, $inspection] = $this->context();
        $sequence = 0;
        foreach (['REC' => 'IE', 'CV' => 'CV', 'TAC' => 'TA', 'TEL' => 'TE'] as $category => $prefix) {
            foreach ([1, 2, 3, 4, 5] as $priority) {
                $this->assessment($inspection, DefectCategory::from($category), ++$sequence, ['classification_code' => $prefix.'-'.$priority]);
            }
        }
        $summary = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $dates = collect($summary['categories'])->flatMap(fn ($category) => $category['rows'])->keyBy('classification_code');
        $before = $this->build($inspection);
        $this->assertCount(15, $before['rows']);
        foreach ($before['rows'] as $row) {
            $this->assertSame($dates[$row['cells']['classification']['value']]['m2_due_date'] ?? '', $row['cells']['due_date']['display']);
        }
        $inspection->update(['inspected_on' => '2027-06-20']);
        $after = $this->build($inspection);
        $this->assertSame('20/06/2028', $after['rows'][0]['cells']['due_date']['display']);
        $this->assertSame('20/06/2030', $after['rows'][7]['cells']['due_date']['display']);
        $this->assertSame('', $after['rows'][10]['cells']['due_date']['display']);
        $inspection->update(['inspected_on' => null]);
        foreach ($this->build($inspection)['rows'] as $row) {
            $this->assertNull($row['cells']['due_date']['value']);
        }
    }

    public function test_export_is_a_native_xls_matching_the_page_including_types_colors_and_blanks(): void
    {
        [$user, $inspection] = $this->context();
        $this->assessment($inspection, DefectCategory::StructuralRecovery, 1, [
            'defect_snapshot' => ['defect' => ['code' => '000123']],
            'gravity' => 5, 'urgency' => 5, 'trend' => 3, 'gut_score' => 75,
            'quantity_snapshot' => ['total' => '12.5'],
            'classification_code' => 'IE-1', 'classification_snapshot' => ['code' => 'IE-1', 'color' => '#FF0000'],
        ]);
        $this->assessment($inspection, DefectCategory::Civil, 2, ['defect_snapshot' => ['defect' => ['code' => '=1+1']]]);
        $this->assessment($inspection, DefectCategory::SolidaryStructures, 3);
        $this->assessment($inspection, DefectCategory::AnticorrosiveTreatment, 4);
        $this->assessment($inspection, DefectCategory::RoofCladding, 5);
        $payload = $this->build($inspection);
        $sourceBook = app(ExportInspectionQuantitativeWorksheet::class)->spreadsheet($payload);
        // The XLS writer exports AutoFilter, but the XLS reader does not restore it.
        $this->assertSame('A7:N10', $sourceBook->getActiveSheet()->getAutoFilter()->getRange());
        $sourceBook->disconnectWorksheets();
        $response = $this->actingAs($user)->get(route('inspections.quantitative.export', $inspection))->assertOk()->assertDownload()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel');
        $binary = $response->streamedContent();
        $this->assertSame('d0cf11e0a1b11ae1', bin2hex(substr($binary, 0, 8)));
        $temporary = tempnam(sys_get_temp_dir(), 'quantitative-test-');
        try {
            file_put_contents($temporary, $binary);
            $book = (new Xls)->load($temporary);
            $this->assertSame(['QUANTITATIVO'], $book->getSheetNames());
            $sheet = $book->getActiveSheet();
            $this->assertSame(10, $sheet->getHighestRow());
            $this->assertSame('N', $sheet->getHighestColumn());
            $this->assertSame([
                'LINHA', 'DISCIPLINA', 'NOTA', 'CÓD. AVARIA', 'PESO/ÁREA', 'IMPACTO', 'G', 'U',
                'DANO', 'T', 'PONT. G.U.T', 'CLASS. DO DANO', 'DATA ESTIMADA EXECUÇÃO', 'STATUS DA AVARIA',
            ], $sheet->rangeToArray('A7:N7')[0]);
            $this->assertSame([['REC'], ['CIVIL'], ['TEL']], $sheet->rangeToArray('B8:B10'));
            $this->assertSame([['1'], ['2'], ['3']], $sheet->rangeToArray('A8:A10'));
            $this->assertSame('A1:N10', str_replace('$', '', $sheet->getPageSetup()->getPrintArea()));
            $this->assertSame($payload['title'], $sheet->getCell('A1')->getValue());
            $this->assertSame('TAG-HISTORICO', $sheet->getCell('E2')->getValue());
            $this->assertNull($sheet->getCell('E3')->getValue());
            $this->assertSame(3, $sheet->getCell('L4')->getValue());
            $this->assertSame('10/05/2026', $sheet->getCell('E5')->getFormattedValue());
            foreach ($payload['rows'] as $index => $row) {
                foreach ($payload['columns'] as $columnIndex => $column) {
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex + 1).($index + 8));
                    $expected = $row['cells'][$column['key']];
                    $this->assertNotSame(DataType::TYPE_FORMULA, $cell->getDataType());
                    if ($expected['value'] === null) {
                        $this->assertNull($cell->getValue());
                    } elseif ($column['type'] === 'date') {
                        $this->assertTrue(Date::isDateTime($cell));
                        $this->assertSame($expected['display'], $cell->getFormattedValue());
                    } elseif (in_array($column['type'], ['number', 'quantity'], true)) {
                        $this->assertEquals((float) $expected['value'], $cell->getValue());
                        $this->assertSame(DataType::TYPE_NUMERIC, $cell->getDataType());
                    } else {
                        $this->assertSame($expected['value'], $cell->getValue());
                        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
                    }
                    $this->assertSame(strtoupper(ltrim($expected['background'] ?? $column['background'], '#')), $sheet->getStyle($cell->getCoordinate())->getFill()->getStartColor()->getRGB());
                }
            }
            $this->assertSame('FF0000', $sheet->getStyle('K8')->getFill()->getStartColor()->getRGB());
            $this->assertSame('FFFFFF', $sheet->getStyle('K8')->getFont()->getColor()->getRGB());
            $this->assertSame('FF0000', $sheet->getStyle('L8')->getFill()->getStartColor()->getRGB());
            $this->assertSame('FFFFFF', $sheet->getStyle('L8')->getFont()->getColor()->getRGB());
            $this->assertSame('#,##0.00 "kg"', $sheet->getStyle('E8')->getNumberFormat()->getFormatCode());
            $this->assertSame('E8', $sheet->getFreezePane());
            $book->disconnectWorksheets();
        } finally {
            unlink($temporary);
        }
    }

    public function test_empty_worksheet_has_no_artificial_rows_and_missing_header_values_stay_empty(): void
    {
        [, $inspection] = $this->context();
        $this->assessment($inspection, DefectCategory::AnticorrosiveTreatment, 1);
        $this->assessment($inspection, DefectCategory::SolidaryStructures, 2);
        $sheet = $this->build($inspection);
        $this->assertSame([], $sheet['rows']);
        $this->assertSame(0, $sheet['header']['row_count']['value']);
        $book = app(ExportInspectionQuantitativeWorksheet::class)->spreadsheet($sheet);
        $this->assertSame(7, $book->getActiveSheet()->getHighestRow());
        $this->assertFalse($book->getActiveSheet()->cellExists('A8'));
        $book->disconnectWorksheets();
    }

    public function test_long_labels_wrap_and_export_keeps_every_row(): void
    {
        [, $inspection] = $this->context();
        $longLabel = str_repeat('Deformação de elemento estrutural ', 8);
        for ($sequence = 1; $sequence <= 80; $sequence++) {
            $this->assessment($inspection, DefectCategory::StructuralRecovery, $sequence, [
                'gut_snapshot' => ['criteria' => ['trend' => ['score' => 3, 'group' => ['label' => $longLabel]]]],
            ]);
        }
        $payload = $this->build($inspection);
        $this->assertCount(80, $payload['rows']);
        $book = app(ExportInspectionQuantitativeWorksheet::class)->spreadsheet($payload);
        $sheet = $book->getActiveSheet();
        $this->assertSame(87, $sheet->getHighestRow());
        $this->assertSame('REC-080', $sheet->getCell('D87')->getValue());
        $this->assertSame($longLabel, $sheet->getCell('I87')->getValue());
        $this->assertTrue($sheet->getStyle('I87')->getAlignment()->getWrapText());
        $this->assertGreaterThan(22, $sheet->getRowDimension(87)->getRowHeight());
        $book->disconnectWorksheets();
    }

    public function test_page_and_export_require_view_permission_and_the_same_organization(): void
    {
        [$user, $inspection] = $this->context();
        $unassigned = User::factory()->for($inspection->organization)->create(['operational_role' => OperationalRole::Inspector]);
        [, $foreignInspection] = $this->context();
        foreach (['inspections.quantitative', 'inspections.quantitative.export'] as $route) {
            $this->actingAs($unassigned)->get(route($route, $inspection))->assertForbidden();
            $this->actingAs($user)->get(route($route, $foreignInspection))->assertNotFound();
        }
    }

    private function build(Inspection $inspection): array
    {
        return app(BuildInspectionQuantitativeWorksheet::class)->build($inspection);
    }

    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress, 'inspected_on' => '2026-05-10',
            'external_report_number' => 'REL-EXISTENTE', 'designer_i_report_number' => 'PROJ-EXISTENTE',
            'context_snapshot' => ['equipment' => ['tag' => 'TAG-HISTORICO', 'installation_location' => 'LOCAL HISTÓRICO']],
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);

        return [$user, $inspection];
    }

    private function assessment(Inspection $inspection, DefectCategory $category, int $sequence, array $attributes = []): DefectAssessment
    {
        $code = $category->value.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'category' => $category, 'sequence_number' => $sequence, 'code' => $code,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create([
            'defect_snapshot' => ['defect' => ['code' => $code, 'category' => $category->value, 'sequence_number' => $sequence]],
            ...$attributes,
        ]);
    }
}
