<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Actions\InspectionLocations\BuildInspectionLocationSnapshot;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionLocationReportComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_creates_one_sheet_per_located_assessment_in_category_and_defect_order(): void
    {
        $inspection = $this->inspection();
        $civil = $this->assessment($inspection, DefectCategory::Civil, 'CV-002', 2);
        $tac = $this->assessment($inspection, DefectCategory::AnticorrosiveTreatment, 'TA-001', 1);
        $firstCivil = $this->assessment($inspection, DefectCategory::Civil, 'CV-001', 1);
        $canceled = $this->assessment($inspection, DefectCategory::Civil, 'CV-003', 3);
        $canceled->update(['condition' => DefectAssessmentCondition::Canceled]);
        foreach ([$civil, $tac, $firstCivil, $canceled] as $assessment) {
            $this->locateAssessment($assessment);
        }

        $report = app(InspectionLocationReportComposer::class)->compose($inspection);

        $this->assertSame(['TAC', 'CV'], collect($report['categories'])->pluck('category.code')->all());
        $this->assertSame(
            ['TA-001', 'CV-001', 'CV-002'],
            collect($report['sheets'])->pluck('maps.0.markers.0.defect.code')->all(),
        );
        $this->assertCount(3, $report['sheets']);
        $this->assertFalse(collect($report['sheets'])->contains(
            fn (array $sheet): bool => data_get($sheet, 'maps.0.markers.0.defect.code') === 'CV-003',
        ));
        foreach ($report['sheets'] as $sheet) {
            $this->assertCount(1, $sheet['maps']);
            $this->assertCount(1, $sheet['maps'][0]['markers']);
        }
    }

    public function test_report_uses_all_ready_assessment_photos_and_preserves_tac_reservation(): void
    {
        $inspection = $this->inspection();
        $tac = $this->assessment($inspection, DefectCategory::AnticorrosiveTreatment, 'TA-001', 1);
        $civil = $this->assessment($inspection, DefectCategory::Civil, 'CV-001', 1);
        $this->locateAssessment($tac);
        $this->locateAssessment($civil);
        $tacPhotos = $this->photos($tac, 3);
        $civilPhotos = $this->photos($civil, 2);

        $numbering = app(InspectionLocationPhotoNumbering::class)->buildForReport($inspection);
        $report = app(InspectionLocationReportComposer::class)->compose($inspection);

        $this->assertSame([5, 6, 7], array_map(fn (AssessmentPhoto $photo): int => $numbering[$photo->public_id], $tacPhotos));
        $this->assertSame([1, 2], array_map(fn (AssessmentPhoto $photo): int => $numbering[$photo->public_id], $civilPhotos));
        $this->assertSame('5 A 7', $report['sheets'][0]['maps'][0]['markers'][0]['photo_interval']);
        $this->assertSame('1 E 2', $report['sheets'][1]['maps'][0]['markers'][0]['photo_interval']);
        $this->assertSame(5, $report['photo_count']);
    }

    public function test_report_keeps_historical_version_and_automatic_color_without_editable_style(): void
    {
        $inspection = $this->inspection();
        $assessment = $this->assessment($inspection, DefectCategory::StructuralRecovery, 'REC-001', 1);
        $assessment->update(['classification_snapshot' => ['code' => 'IE-2', 'color' => '#FFC000']]);
        $historical = $this->locateAssessment($assessment);
        $snapshotBefore = app(InspectionLocationReportComposer::class)->compose($inspection);

        $newer = $historical->replicate(['public_id', 'version']);
        $newer->public_id = null;
        $newer->version = 2;
        $newer->created_for_assessment_id = $assessment->id;
        $newer->save();

        $report = app(InspectionLocationReportComposer::class)->compose($inspection);
        $map = $report['sheets'][0]['maps'][0];
        $this->assertSame($historical->version, $map['source']['version']);
        $this->assertSame('#FFC000', $map['markers'][0]['style']['fill']);
        $this->assertSame('#FFC000', $map['damage_rows'][0]['classification']['color']);
        $this->assertSame(
            $snapshotBefore['sheets'][0]['maps'][0]['background']['checksum'],
            $map['background']['checksum'],
        );

        $snapshot = app(BuildInspectionLocationSnapshot::class)->fromComposition($inspection, $report);
        $this->assertArrayNotHasKey('url', $snapshot['categories'][0]['maps'][0]['background']);

        $assessment->update(['condition' => DefectAssessmentCondition::Treated, 'classification_snapshot' => null]);
        $neutral = app(InspectionLocationReportComposer::class)->compose($inspection);
        $this->assertSame('#64748B', $neutral['sheets'][0]['maps'][0]['markers'][0]['style']['fill']);
    }

    private function inspection(): Inspection
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();

        return Inspection::factory()->forEquipment($equipment)->create();
    }

    private function assessment(Inspection $inspection, DefectCategory $category, string $code, int $sequence): DefectAssessment
    {
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'category' => $category,
            'code' => $code,
            'sequence_number' => $sequence,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();
    }

    /** @return array<int,AssessmentPhoto> */
    private function photos(DefectAssessment $assessment, int $count): array
    {
        return collect(range(1, $count))->map(fn (int $position): AssessmentPhoto => AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $assessment->organization_id,
            'inspection_id' => $assessment->inspection_id,
            'defect_assessment_id' => $assessment->id,
            'position' => $position,
        ]))->all();
    }
}
