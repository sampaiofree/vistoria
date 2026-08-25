<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Actions\InspectionLocations\BuildInspectionLocationSnapshot;
use App\Enums\GutCriterion;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\MeasurementUnit;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\DefectCategory;
use App\Models\DefectCategoryGutOption;
use App\Models\DefectClassification;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\Organization;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionLocationReportComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_composer_creates_ordered_map_sheets_and_keeps_category_photo_numbering(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $category = DefectCategory::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'TA',
        ]);
        $firstAssessment = $this->assessment($inspection, $category, 'TA-001');
        $secondAssessment = $this->assessment($inspection, $category, 'TA-002');
        $firstMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'title' => 'Mapa 1',
            'position' => 1,
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_width' => 1600,
            'background_height' => 900,
            'reference_snapshot' => ['document_number' => 'U030600-M-560002'],
        ]);
        $secondMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'title' => 'Mapa 2',
            'position' => 2,
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);
        $firstMarker = InspectionLocationMarker::factory()->forMapAndAssessment($firstMap, $firstAssessment)->create([
            'label' => 'Face inferior do pedestal.',
            'position' => 1,
            'style' => ['stroke' => 'none', 'fill' => '#7C3AED', 'stroke_width' => 0.005, 'opacity' => 0.9, 'dashed' => false],
            'geometry' => ['version' => 1, 'shapes' => [
                ['type' => 'rectangle', 'x' => 0.1, 'y' => 0.1, 'width' => 0.1, 'height' => 0.1],
                ['type' => 'rectangle', 'x' => 0.4, 'y' => 0.4, 'width' => 0.1, 'height' => 0.1],
            ]],
        ]);
        $secondMarker = InspectionLocationMarker::factory()->forMapAndAssessment($secondMap, $secondAssessment)->create();
        $firstPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $firstAssessment->id,
            'position' => 1,
        ]);
        $secondPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $firstAssessment->id,
            'position' => 2,
        ]);
        $thirdPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $firstAssessment->id,
            'position' => 3,
        ]);
        $fourthPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $secondAssessment->id,
            'position' => 1,
        ]);
        $firstMarker->photos()->attach([
            $firstPhoto->id => ['organization_id' => $organization->id, 'inspection_id' => $inspection->id, 'position' => 1],
            $secondPhoto->id => ['organization_id' => $organization->id, 'inspection_id' => $inspection->id, 'position' => 2],
            $thirdPhoto->id => ['organization_id' => $organization->id, 'inspection_id' => $inspection->id, 'position' => 3],
        ]);
        $secondMarker->photos()->attach($fourthPhoto->id, [
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'position' => 1,
        ]);

        $report = app(InspectionLocationReportComposer::class)->compose($inspection);
        $snapshot = app(BuildInspectionLocationSnapshot::class)->fromComposition($inspection, $report);

        $this->assertCount(1, $report['categories']);
        $this->assertCount(2, $report['sheets']);
        $this->assertCount(1, $report['sheets'][0]['maps']);
        $this->assertCount(1, $report['sheets'][1]['maps']);
        $this->assertCount(2, $report['sheets'][0]['maps'][0]['markers'][0]['geometry']['shapes']);
        $this->assertSame('#7C3AED', $report['sheets'][0]['maps'][0]['markers'][0]['style']['fill']);
        $this->assertSame('none', $report['sheets'][0]['maps'][0]['markers'][0]['style']['stroke']);
        $this->assertSame('1 A 3', $report['sheets'][0]['maps'][0]['markers'][0]['photo_interval']);
        $this->assertSame('FOTOS: 1 A 3', $report['sheets'][0]['maps'][0]['markers'][0]['photo_legend']);
        $this->assertSame('Face inferior do pedestal.', $report['sheets'][0]['maps'][0]['markers'][0]['label']);
        $this->assertSame('Mapa 1 — PROJETO DE REFERÊNCIA: U030600-M-560002', $report['sheets'][0]['maps'][0]['report_title']);
        $this->assertSame('4', $report['sheets'][1]['maps'][0]['markers'][0]['photo_interval']);
        $this->assertSame('FOTOS: 4', $report['sheets'][1]['maps'][0]['markers'][0]['photo_legend']);
        $this->assertSame(4, $report['photo_count']);
        $this->assertSame($snapshot, app(BuildInspectionLocationSnapshot::class)->fromComposition($inspection, $report));
        $this->assertArrayNotHasKey('url', $snapshot['categories'][0]['maps'][0]['background']);
        $this->assertArrayNotHasKey('thumbnail_url', $snapshot['categories'][0]['maps'][0]['markers'][0]['photos'][0]);
    }

    public function test_marker_falls_back_to_all_numbered_assessment_photos_when_no_selection_exists(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'RX']);
        $assessment = $this->assessment($inspection, $category, 'CV-001');
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();

        foreach (range(1, 4) as $position) {
            AssessmentPhoto::factory()->ready()->create([
                'organization_id' => $organization->id,
                'inspection_id' => $inspection->id,
                'defect_assessment_id' => $assessment->id,
                'position' => $position,
            ]);
        }

        $marker = app(InspectionLocationReportComposer::class)
            ->compose($inspection)['sheets'][0]['maps'][0]['markers'][0];

        $this->assertSame([1, 2, 3, 4], $marker['photo_numbers']);
        $this->assertSame('1 A 4', $marker['photo_interval']);
        $this->assertSame('FOTOS: 1 A 4', $marker['photo_legend']);
    }

    public function test_report_omits_markers_linked_to_draft_assessments(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'CIV']);
        $published = $this->assessment($inspection, $category, 'CV-001');
        $draftDefect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'defect_category_id' => $category->id,
            'code' => 'CV-002',
        ]);
        $draft = DefectAssessment::factory()->forDefect($draftDefect, $inspection)->draft()->create();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $published)->create(['position' => 1]);
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $draft)->create(['position' => 2]);
        $draftPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $draft->id,
        ]);

        $report = app(InspectionLocationReportComposer::class)->compose($inspection);
        $reportMap = $report['sheets'][0]['maps'][0];

        $this->assertSame(1, $reportMap['marker_count']);
        $this->assertCount(1, $reportMap['markers']);
        $this->assertSame('CV-001', $reportMap['markers'][0]['defect']['code']);
        $this->assertArrayNotHasKey($draftPhoto->public_id, $report['numbering']);
    }

    public function test_report_numbering_ignores_unmapped_assessments_and_keeps_all_photos_from_mapped_assessment(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'MAP-NUM']);
        $unmapped = $this->assessment($inspection, $category, 'CV-001');
        $mapped = $this->assessment($inspection, $category, 'CV-002');
        $unmapped->defect->update(['sequence_number' => 1]);
        $mapped->defect->update(['sequence_number' => 2]);
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $mapped)->create();

        $unmappedPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $unmapped->id,
        ]);
        $firstMappedPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $mapped->id,
            'position' => 1,
        ]);
        $secondMappedPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $mapped->id,
            'position' => 2,
        ]);
        $marker->photos()->attach($firstMappedPhoto->id, [
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'position' => 1,
        ]);

        $numbering = app(InspectionLocationPhotoNumbering::class)->buildForReport($inspection);
        $report = app(InspectionLocationReportComposer::class)->compose($inspection);

        $this->assertSame([
            $firstMappedPhoto->public_id => 1,
            $secondMappedPhoto->public_id => 2,
        ], $numbering);
        $this->assertArrayNotHasKey($unmappedPhoto->public_id, $numbering);
        $this->assertSame(2, $report['photo_count']);
        $this->assertSame([1], $report['sheets'][0]['maps'][0]['markers'][0]['photo_numbers']);
    }

    public function test_report_numbering_restarts_by_category_and_reserves_one_to_four_only_for_tac(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $tac = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'TAC')
            ->firstOrFail();
        $tac->update(['name' => 'TAC', 'position' => 30]);
        $rec = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'REC')
            ->firstOrFail();
        $rec->update(['name' => 'REC', 'position' => 20]);
        $cv = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'CV')
            ->first();
        if ($cv === null) {
            $cv = DefectCategory::factory()->create([
                'organization_id' => $organization->id,
                'code' => 'CV',
                'name' => 'CV',
                'position' => 3,
            ]);
        } else {
            $cv->update(['name' => 'CV', 'position' => 3]);
        }

        $firstTacAssessment = $this->assessment($inspection, $tac, 'TA-001');
        $secondTacAssessment = $this->assessment($inspection, $tac, 'TA-002');
        $recAssessment = $this->assessment($inspection, $rec, 'REC-001');
        $cvAssessment = $this->assessment($inspection, $cv, 'CV-001');

        $firstTacMap = InspectionLocationMap::factory()->forInspection($inspection, $tac)->create(['position' => 1]);
        $secondTacMap = InspectionLocationMap::factory()->forInspection($inspection, $tac)->create(['position' => 2]);
        InspectionLocationMarker::factory()->forMapAndAssessment($firstTacMap, $firstTacAssessment)->create(['position' => 1]);
        InspectionLocationMarker::factory()->forMapAndAssessment($secondTacMap, $secondTacAssessment)->create(['position' => 1]);

        $recMap = InspectionLocationMap::factory()->forInspection($inspection, $rec)->create(['position' => 1]);
        InspectionLocationMarker::factory()->forMapAndAssessment($recMap, $recAssessment)->create();
        $cvMap = InspectionLocationMap::factory()->forInspection($inspection, $cv)->create(['position' => 1]);
        InspectionLocationMarker::factory()->forMapAndAssessment($cvMap, $cvAssessment)->create();

        $firstTacPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $firstTacAssessment->id,
            'position' => 1,
        ]);
        $thirdTacPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $firstTacAssessment->id,
            'position' => 9,
        ]);
        $fourthTacPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $firstTacAssessment->id,
            'position' => 10,
        ]);
        $secondTacPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $secondTacAssessment->id,
            'position' => 1,
        ]);
        $recPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $recAssessment->id,
            'position' => 1,
        ]);
        $cvPhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $cvAssessment->id,
            'position' => 1,
        ]);

        $numbering = app(InspectionLocationPhotoNumbering::class)->buildForReport($inspection);
        $report = app(InspectionLocationReportComposer::class)->compose($inspection);

        $this->assertSame([
            $firstTacPhoto->public_id => 5,
            $thirdTacPhoto->public_id => 6,
            $fourthTacPhoto->public_id => 7,
            $secondTacPhoto->public_id => 8,
            $recPhoto->public_id => 1,
            $cvPhoto->public_id => 1,
        ], $numbering);
        $this->assertCount(6, $numbering);
        $this->assertSame(
            ['TAC', 'REC', 'CV'],
            collect($report['categories'])->pluck('category.code')->all(),
        );
    }

    public function test_map_footer_groups_damage_rows_and_exposes_colored_category_legend_and_observations(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $category = DefectCategory::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'TA',
        ]);
        $moderate = DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'code' => 'TA-2',
            'color' => '#FFBF00',
            'position' => 2,
        ]);
        $critical = DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'code' => 'TA-1',
            'color' => '#FF0000',
            'position' => 1,
        ]);
        DefectClassification::factory()->for($category, 'category')->create([
            'organization_id' => $organization->id,
            'code' => 'TA-3',
            'color' => null,
            'position' => 3,
        ]);
        DefectCategoryGutOption::factory()->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Gravity,
            'score' => 3,
            'color' => '#FFFF00',
        ]);
        DefectCategoryGutOption::factory()->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Urgency,
            'score' => 3,
            'color' => '#92D050',
        ]);
        DefectCategoryGutOption::factory()->create([
            'organization_id' => $organization->id,
            'defect_category_id' => $category->id,
            'criterion' => GutCriterion::Trend,
            'score' => 4,
            'color' => '#FFBF00',
        ]);
        $classified = $this->assessment($inspection, $category, 'TA-001');
        $classified->update([
            'defect_classification_id' => $moderate->id,
            'classification_code' => $moderate->code,
            'gravity' => 3,
            'urgency' => 3,
            'trend' => 4,
            'gut_snapshot' => [
                'criteria' => [
                    'gravity' => ['score' => 3, 'color' => '#000000'],
                    'urgency' => ['score' => 3, 'color' => '#000000'],
                    'trend' => ['score' => 4, 'color' => '#000000'],
                ],
            ],
        ]);
        DefectAssessmentQuantity::factory()->forAssessment($classified)->create([
            'measurement_value' => 42,
            'measurement_unit' => MeasurementUnit::SquareMeter,
        ]);
        $unclassified = $this->assessment($inspection, $category, 'TA-002');
        $unclassified->update(['gravity' => 9]);
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'description' => "Primeira linha.\nSegunda linha.",
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $classified)->create([
            'position' => 1,
            'geometry' => ['version' => 1, 'shapes' => [
                ['type' => 'point', 'x' => 0.25, 'y' => 0.25],
                ['type' => 'point', 'x' => 0.75, 'y' => 0.75],
            ]],
        ]);
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $unclassified)->create(['position' => 2]);

        foreach (range(1, 2) as $position) {
            AssessmentPhoto::factory()->ready()->create([
                'organization_id' => $organization->id,
                'inspection_id' => $inspection->id,
                'defect_assessment_id' => $classified->id,
                'position' => $position,
            ]);
        }

        $report = app(InspectionLocationReportComposer::class)->compose($inspection);
        $reportMap = $report['sheets'][0]['maps'][0];
        $snapshotMap = app(BuildInspectionLocationSnapshot::class)
            ->fromComposition($inspection, $report)['categories'][0]['maps'][0];

        $this->assertSame("Primeira linha.\nSegunda linha.", $reportMap['observations']);
        $this->assertCount(2, $reportMap['damage_rows']);
        $this->assertSame($classified->public_id, $reportMap['damage_rows'][0]['assessment']['public_id']);
        $this->assertSame([1, 2], $reportMap['damage_rows'][0]['photo_numbers']);
        $this->assertSame('1 E 2', $reportMap['damage_rows'][0]['photo_interval']);
        $this->assertSame([
            'value' => 42.0,
            'unit' => 'M²',
        ], $reportMap['damage_rows'][0]['quantity']);
        $this->assertSame([
            'gravity' => ['score' => 3, 'color' => '#FFFF00'],
            'urgency' => ['score' => 3, 'color' => '#92D050'],
            'trend' => ['score' => 4, 'color' => '#FFBF00'],
        ], $reportMap['damage_rows'][0]['gut']);
        $this->assertSame([
            'code' => 'TA-2',
            'color' => '#FFBF00',
        ], $reportMap['damage_rows'][0]['classification']);
        $this->assertSame('—', $reportMap['damage_rows'][1]['photo_interval']);
        $this->assertSame([
            'code' => null,
            'color' => null,
        ], $reportMap['damage_rows'][1]['classification']);
        $this->assertNull($reportMap['damage_rows'][1]['quantity']);
        $this->assertSame([
            'gravity' => ['score' => 9, 'color' => null],
            'urgency' => ['score' => null, 'color' => null],
            'trend' => ['score' => null, 'color' => null],
        ], $reportMap['damage_rows'][1]['gut']);
        $this->assertSame([
            [
                'public_id' => $critical->public_id,
                'code' => 'TA-1',
                'color' => '#FF0000',
            ],
            [
                'public_id' => $moderate->public_id,
                'code' => 'TA-2',
                'color' => '#FFBF00',
            ],
        ], $reportMap['classification_legend']);
        $this->assertSame($reportMap['observations'], $snapshotMap['observations']);
        $this->assertSame($reportMap['damage_rows'], $snapshotMap['damage_rows']);
        $this->assertSame($reportMap['classification_legend'], $snapshotMap['classification_legend']);
    }

    private function assessment(Inspection $inspection, DefectCategory $category, string $code): DefectAssessment
    {
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'defect_category_id' => $category->id,
            'code' => $code,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create(['comment' => 'Avaliação publicada.']);
    }
}
