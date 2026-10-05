<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use App\Services\Reports\InspectionPhotographicDocumentationComposer;
use Tests\TestCase;

final class InspectionPhotographicDocumentationComposerTest extends TestCase
{
    public function test_groups_all_photos_by_defect_in_manual_gallery_order(): void
    {
        $items = [
            [
                'id' => 10,
                'code' => 'VT009-CV-001',
                'title' => 'Estrutura do ventilador',
                'category' => 'CV',
                'category_label' => 'Civil',
                'assessment' => [
                    'id' => 100,
                    'public_id' => 'assessment-100',
                    'status' => 'complete',
                    'condition' => 'reclassified',
                    'condition_label' => 'Agravou',
                    'comment' => 'Comentário técnico.',
                    'recommendation' => 'Recomendação técnica.',
                ],
                'previous_assessment_summary' => [
                    'classification' => ['code' => 'IE-3', 'label' => 'Média'],
                ],
                'classification' => ['code' => 'IE-2'],
                'photos' => [
                    ['id' => 'photo-3', 'position' => 3, 'status' => 'ready'],
                    ['id' => 'photo-1', 'position' => 1, 'status' => 'ready'],
                    ['id' => 'photo-4', 'position' => 4, 'status' => 'ready'],
                    ['id' => 'photo-2', 'position' => 2, 'status' => 'ready'],
                ],
            ],
            [
                'id' => 11,
                'code' => 'VT009-CV-002',
                'title' => 'Base do motor',
                'assessment' => ['status' => 'complete'],
                'photos' => [
                    ['id' => 'photo-5', 'position' => 1, 'status' => 'ready'],
                    ['id' => 'photo-6', 'position' => 2, 'status' => 'ready'],
                ],
            ],
            [
                'id' => 12,
                'code' => 'VT009-CV-003',
                'title' => 'Avaria em rascunho',
                'assessment' => ['status' => 'draft'],
                'photos' => [
                    ['id' => 'photo-draft', 'position' => 1, 'status' => 'ready'],
                ],
            ],
        ];

        $result = app(InspectionPhotographicDocumentationComposer::class)->compose($items, [
            'photo-1' => 1,
            'photo-2' => 2,
            'photo-3' => 3,
            'photo-4' => 4,
            'photo-5' => 5,
            'photo-6' => 6,
        ]);

        $this->assertSame(6, $result['photo_count']);
        $this->assertCount(3, $result['blocks']);
        $this->assertSame(100, $result['blocks'][0]['assessment_id']);
        $this->assertSame('assessment-100', $result['blocks'][0]['assessment_public_id']);
        $this->assertSame(['photo-1', 'photo-2'], collect($result['blocks'][0]['photos'])->pluck('id')->all());
        $this->assertSame(['photo-3', 'photo-4'], collect($result['blocks'][1]['photos'])->pluck('id')->all());
        $this->assertSame('Estrutura do ventilador', $result['blocks'][1]['photos'][0]['title']);
        $this->assertSame('Comentário técnico.', $result['blocks'][1]['comment']);
        $this->assertSame('Recomendação técnica.', $result['blocks'][1]['recommendation']);
        $this->assertSame('VT009-CV-001', $result['blocks'][1]['defect_code']);
        $this->assertSame('IE-2', $result['blocks'][1]['classification_code']);
        $this->assertSame('reclassified', $result['blocks'][1]['condition']);
        $this->assertSame('Agravou', $result['blocks'][1]['condition_label']);
        $this->assertSame('IE-3', $result['blocks'][1]['previous_classification']['code']);
        $this->assertSame('IE-2', $result['blocks'][1]['current_classification']['code']);
        $this->assertSame(['photo-5', 'photo-6'], collect($result['blocks'][2]['photos'])->pluck('id')->all());
        $this->assertFalse(collect($result['blocks'])->pluck('photos')->flatten(1)->contains('id', 'photo-draft'));
        $this->assertSame([], $result['unindexed_photo_ids']);
    }

    public function test_does_not_assign_a_silent_number_to_a_photo_missing_from_the_canonical_index(): void
    {
        $result = app(InspectionPhotographicDocumentationComposer::class)->compose([
            [
                'id' => 10,
                'title' => 'Avaria publicada',
                'assessment' => ['status' => 'complete'],
                'photos' => [
                    ['id' => 'indexed', 'position' => 1],
                    ['id' => 'missing', 'position' => 2],
                ],
            ],
        ], ['indexed' => 1]);

        $photos = collect($result['blocks'])->flatMap(fn (array $block): array => $block['photos'])->values();

        $this->assertSame(1, $photos[0]['sequence']);
        $this->assertNull($photos[1]['sequence']);
        $this->assertSame(['missing'], $result['unindexed_photo_ids']);
    }

    public function test_keeps_assessment_markers_on_every_photo_pair_including_historical_blocks(): void
    {
        $cases = [
            ['code' => 'CV-001', 'unsafe' => false, 'method' => 'gut', 'photos' => 1],
            ['code' => 'CV-002', 'unsafe' => true, 'method' => 'gut', 'photos' => 1],
            ['code' => 'CV-003', 'unsafe' => false, 'method' => 'engineering_note', 'photos' => 1],
            ['code' => 'CV-004', 'unsafe' => true, 'method' => 'engineering_note', 'photos' => 3],
        ];

        $items = collect($cases)->map(fn (array $case, int $index): array => [
            'id' => $index + 1,
            'code' => $case['code'],
            'historical_label' => $case['code'] === 'CV-004' ? 'Histórico mantido' : null,
            'assessment' => [
                'status' => 'complete',
                'is_unsafe_condition' => $case['unsafe'],
                'classification_method' => $case['method'],
            ],
            'photos' => array_map(
                fn (int $position): array => ['id' => "{$case['code']}-{$position}", 'position' => $position],
                range(1, $case['photos']),
            ),
        ])->all();

        $blocks = app(InspectionPhotographicDocumentationComposer::class)->compose($items)['blocks'];

        $this->assertSame([
            ['CV-001', false, false],
            ['CV-002', true, false],
            ['CV-003', false, true],
            ['CV-004', true, true],
            ['CV-004', true, true],
        ], collect($blocks)->map(fn (array $block): array => [
            $block['defect_code'],
            $block['is_unsafe_condition'],
            $block['has_engineering_note'],
        ])->all());
        $this->assertSame('Histórico mantido', $blocks[3]['historical_label']);
        $this->assertSame('Histórico mantido', $blocks[4]['historical_label']);
    }

    public function test_omits_photos_of_both_canceled_conditions_but_keeps_treated_photos(): void
    {
        $result = app(InspectionPhotographicDocumentationComposer::class)->compose([
            [
                'id' => 1,
                'code' => 'CV-001',
                'assessment' => ['status' => 'complete', 'condition' => 'canceled'],
                'photos' => [['id' => 'canceled-photo', 'position' => 1]],
            ],
            [
                'id' => 2,
                'code' => 'CV-002',
                'assessment' => ['status' => 'complete', 'condition' => 'canceled_sr'],
                'photos' => [['id' => 'canceled-sr-photo', 'position' => 1]],
            ],
            [
                'id' => 3,
                'code' => 'CV-003',
                'assessment' => ['status' => 'complete', 'condition' => 'treated'],
                'photos' => [['id' => 'treated-photo', 'position' => 1]],
            ],
        ], ['treated-photo' => 1]);

        $this->assertSame(1, $result['photo_count']);
        $this->assertSame(['CV-003'], collect($result['blocks'])->pluck('defect_code')->all());
        $this->assertSame(1, $result['blocks'][0]['photos'][0]['sequence']);
        $this->assertSame([], $result['unindexed_photo_ids']);
    }
}
