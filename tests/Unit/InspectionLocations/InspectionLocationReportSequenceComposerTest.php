<?php

declare(strict_types=1);

namespace Tests\Unit\InspectionLocations;

use App\Services\InspectionLocations\InspectionLocationReportCategoryOrder;
use App\Services\InspectionLocations\InspectionLocationReportSequenceComposer;
use Tests\TestCase;

final class InspectionLocationReportSequenceComposerTest extends TestCase
{
    public function test_orders_categories_and_places_each_maps_photos_immediately_after_it(): void
    {
        $composer = new InspectionLocationReportSequenceComposer(new InspectionLocationReportCategoryOrder);
        $sheets = [
            $this->sheet('cv-map', 'CV', 'CIVIL', 'cv-assessment'),
            $this->sheet('other-map', 'ELE', 'ELÉTRICA', 'other-assessment'),
            $this->sheet('rec-map', 'REC', 'REC', 'rec-assessment'),
            $this->sheet('tac-map-1', 'TAC', 'TAC', 'tac-assessment-1'),
            $this->sheet('tac-map-2', 'TAC', 'TAC', 'tac-assessment-2'),
        ];
        $blocks = [
            ['id' => 'rec-photos', 'assessment_public_id' => 'rec-assessment'],
            ['id' => 'tac-2-photos', 'assessment_public_id' => 'tac-assessment-2'],
            ['id' => 'cv-photos', 'assessment_public_id' => 'cv-assessment'],
            ['id' => 'tac-1-photos', 'assessment_public_id' => 'tac-assessment-1'],
            ['id' => 'other-photos', 'assessment_public_id' => 'other-assessment'],
        ];

        $sequence = $composer->compose($sheets, $blocks);

        $this->assertSame(
            ['tac-map-1', 'tac-map-2', 'rec-map', 'cv-map', 'other-map'],
            collect($sequence)->pluck('map.public_id')->all(),
        );
        $this->assertSame([
            ['tac-1-photos'],
            ['tac-2-photos'],
            ['rec-photos'],
            ['cv-photos'],
            ['other-photos'],
        ], collect($sequence)->map(fn (array $entry): array => collect($entry['photographic_blocks'])->pluck('id')->all())->all());
        $this->assertSame([
            null,
            null,
            'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC',
            'ANEXO C – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - CIVIL',
            'ANEXO D – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - ELÉTRICA',
        ], collect($sequence)->pluck('annex_title')->all());
    }

    public function test_keeps_a_map_without_photos_and_does_not_repeat_a_legacy_assessment(): void
    {
        $composer = new InspectionLocationReportSequenceComposer(new InspectionLocationReportCategoryOrder);
        $sheets = [
            $this->sheet('first-map', 'TAC', 'TAC', 'same-assessment'),
            $this->sheet('second-map', 'TAC', 'TAC', 'same-assessment'),
        ];

        $sequence = $composer->compose($sheets, [
            ['id' => 'photos', 'assessment_public_id' => 'same-assessment'],
        ]);

        $this->assertSame(['photos'], collect($sequence[0]['photographic_blocks'])->pluck('id')->all());
        $this->assertSame([], $sequence[1]['photographic_blocks']);
        $this->assertSame([null, null], collect($sequence)->pluck('annex_title')->all());
    }

    public function test_first_present_non_tac_category_receives_annex_b_only_once(): void
    {
        $composer = new InspectionLocationReportSequenceComposer(new InspectionLocationReportCategoryOrder);
        $sequence = $composer->compose([
            $this->sheet('cv-map-1', 'CV', 'CIVIL', 'cv-assessment-1'),
            $this->sheet('cv-map-2', 'CV', 'CIVIL', 'cv-assessment-2'),
        ], []);

        $this->assertSame([
            'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - CIVIL',
            null,
        ], collect($sequence)->pluck('annex_title')->all());
    }

    /** @return array<string, mixed> */
    private function sheet(string $mapId, string $code, string $name, string $assessmentId): array
    {
        return [
            'id' => $mapId,
            'category' => ['code' => $code, 'name' => $name],
            'maps' => [[
                'public_id' => $mapId,
                'markers' => [[
                    'public_id' => $mapId.'-marker',
                    'position' => 1,
                    'assessment' => ['public_id' => $assessmentId],
                ]],
            ]],
        ];
    }
}
