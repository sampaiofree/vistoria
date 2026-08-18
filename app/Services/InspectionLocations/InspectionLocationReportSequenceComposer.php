<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use Illuminate\Support\Collection;

final class InspectionLocationReportSequenceComposer
{
    public function __construct(private readonly InspectionLocationReportCategoryOrder $categoryOrder) {}

    /**
     * @param  array<int, array<string, mixed>>  $sheets
     * @param  array<int, array<string, mixed>>  $photographicBlocks
     * @return array<int, array<string, mixed>>
     */
    public function compose(array $sheets, array $photographicBlocks): array
    {
        $blocksByAssessment = collect($photographicBlocks)
            ->filter(fn (array $block): bool => filled($block['assessment_public_id'] ?? null))
            ->groupBy(fn (array $block): string => (string) $block['assessment_public_id']);

        $orderedSheets = collect($sheets)
            ->values()
            ->map(fn (array $sheet, int $index): array => ['sheet' => $sheet, 'index' => $index])
            ->sortBy(fn (array $entry): array => [
                $this->categoryOrder->priority(
                    data_get($entry, 'sheet.category.code'),
                    data_get($entry, 'sheet.category.name'),
                ),
                $entry['index'],
            ])
            ->pluck('sheet')
            ->values();

        $seenAssessments = [];

        $sequence = $orderedSheets
            ->flatMap(function (array $sheet) use ($blocksByAssessment, &$seenAssessments): array {
                return collect($sheet['maps'] ?? [])->map(function (array $map) use ($sheet, $blocksByAssessment, &$seenAssessments): array {
                    $assessmentIds = collect($map['markers'] ?? [])
                        ->sortBy(fn (array $marker): array => [
                            (int) ($marker['position'] ?? PHP_INT_MAX),
                            (string) ($marker['public_id'] ?? ''),
                        ])
                        ->map(fn (array $marker): ?string => data_get($marker, 'assessment.public_id'))
                        ->filter()
                        ->unique()
                        ->reject(fn (string $assessmentId): bool => isset($seenAssessments[$assessmentId]))
                        ->values();

                    $photographicBlocks = $assessmentIds
                        ->flatMap(function (string $assessmentId) use ($blocksByAssessment, &$seenAssessments): Collection {
                            $seenAssessments[$assessmentId] = true;

                            return $blocksByAssessment->get($assessmentId, collect());
                        })
                        ->values()
                        ->all();

                    return [
                        'id' => (string) ($map['public_id'] ?? $sheet['id'] ?? ''),
                        'category' => $sheet['category'] ?? null,
                        'map' => $map,
                        'photographic_blocks' => $photographicBlocks,
                    ];
                })->all();
            })
            ->values();

        $seenCategories = [];
        $nextAnnexNumber = 2;

        return $sequence
            ->map(function (array $entry) use (&$seenCategories, &$nextAnnexNumber): array {
                $category = (array) ($entry['category'] ?? []);
                $categoryKey = $this->categoryKey($category);
                $isTac = in_array('TAC', [
                    $this->normalize($category['code'] ?? null),
                    $this->normalize($category['name'] ?? null),
                ], true);

                $entry['annex_title'] = null;

                if ($isTac || $categoryKey === '' || isset($seenCategories[$categoryKey])) {
                    return $entry;
                }

                $seenCategories[$categoryKey] = true;
                $categoryLabel = $this->normalize($category['name'] ?? $category['code'] ?? null);
                $entry['annex_title'] = sprintf(
                    'ANEXO %s – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - %s',
                    $this->annexLetter($nextAnnexNumber++),
                    $categoryLabel,
                );

                return $entry;
            })
            ->all();
    }

    /** @param array<string, mixed> $category */
    private function categoryKey(array $category): string
    {
        return $this->normalize(
            $category['code']
                ?? $category['name']
                ?? $category['public_id']
                ?? null,
        );
    }

    private function normalize(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function annexLetter(int $number): string
    {
        $letter = '';

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)).$letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }
}
