<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMarker;
use Illuminate\Support\Collection;

final class InspectionLocationPhotoNumbering
{
    public function __construct(private readonly InspectionLocationReportCategoryOrder $categoryOrder) {}

    /**
     * Builds the canonical report index, restarting the sequence for each
     * defect category. TAC reserves 1-4 for the inspection overview.
     *
     * @return array<string, int>
     */
    public function buildForReport(Inspection $inspection, ?Collection $loadedCategories = null): array
    {
        $categories = $this->categoryOrder->sort($loadedCategories ?? DefectCategory::query()
            ->forOrganization($inspection->organization_id)
            ->whereHas('locationMaps', fn ($query) => $query
                ->where('organization_id', $inspection->organization_id)
                ->where('inspection_id', $inspection->id))
            ->with([
                'locationMaps' => fn ($query) => $query
                    ->where('organization_id', $inspection->organization_id)
                    ->where('inspection_id', $inspection->id)
                    ->with([
                        'markers.assessment.defect',
                        'markers.assessment.photos',
                    ])
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->get());

        $numbering = [];

        foreach ($categories as $category) {
            $nextNumber = $this->categoryOrder->priority($category->code, $category->name) === 0 ? 5 : 1;
            $seenAssessments = [];

            foreach ($category->locationMaps as $map) {
                foreach ($map->markers as $marker) {
                    $assessment = $marker->assessment;

                    if ($assessment === null
                        || ! $assessment->isComplete()
                        || (int) $assessment->defect?->defect_category_id !== (int) $category->id
                        || isset($seenAssessments[$assessment->id])) {
                        continue;
                    }

                    $seenAssessments[$assessment->id] = true;

                    foreach ($this->orderedAssessmentPhotos($assessment) as $photo) {
                        if (! isset($numbering[$photo->public_id])) {
                            $numbering[$photo->public_id] = $nextNumber++;
                        }
                    }
                }
            }
        }

        return $numbering;
    }

    /** @return Collection<int, AssessmentPhoto> */
    private function orderedAssessmentPhotos(DefectAssessment $assessment): Collection
    {
        $assessment->loadMissing('photos');

        return $assessment->photos
            ->filter(fn (AssessmentPhoto $photo): bool => $photo->isReady())
            ->sortBy(fn (AssessmentPhoto $photo): array => [
                (int) $photo->position,
                (int) $photo->id,
            ])
            ->values();
    }

    /** @param iterable<string> $orderedPublicIds @return array<string, int> */
    public function assign(iterable $orderedPublicIds, int $offset = 0): array
    {
        $numbering = [];
        foreach ($orderedPublicIds as $publicId) {
            if (! isset($numbering[$publicId])) {
                $numbering[$publicId] = count($numbering) + 1 + $offset;
            }
        }

        return $numbering;
    }

    /** @param array<string, int>|null $numbering @return array<int, int> */
    public function numbersForMarker(InspectionLocationMarker $marker, ?array $numbering = null): array
    {
        $numbering ??= $this->buildForReport($marker->inspection);

        return $this->photosForMarker($marker)
            ->map(fn ($photo): ?int => $numbering[$photo->public_id] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<string, int> $numbering @return array<int, int> */
    public function numbersForAssessment(DefectAssessment $assessment, array $numbering): array
    {
        $assessment->loadMissing('photos');

        return $assessment->photos
            ->map(fn (AssessmentPhoto $photo): ?int => $numbering[$photo->public_id] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /** @return Collection<int, AssessmentPhoto> */
    public function photosForMarker(InspectionLocationMarker $marker): Collection
    {
        $marker->loadMissing(['photos', 'assessment.photos']);

        return $marker->photos->isNotEmpty()
            ? $marker->photos
            : ($marker->assessment?->photos ?? collect());
    }

    /** @param array<int, int> $numbers */
    public function format(array $numbers): string
    {
        $numbers = array_values(array_unique(array_map('intval', $numbers)));
        sort($numbers);
        if ($numbers === []) {
            return '—';
        }

        $ranges = [];
        $start = $previous = $numbers[0];
        foreach (array_slice($numbers, 1) as $number) {
            if ($number === $previous + 1) {
                $previous = $number;

                continue;
            }
            $ranges[] = $this->range($start, $previous);
            $start = $previous = $number;
        }
        $ranges[] = $this->range($start, $previous);

        return implode(', ', $ranges);
    }

    /** @param array<int, int> $numbers */
    public function legend(array $numbers): string
    {
        $formatted = $this->format($numbers);

        return $formatted === '—'
            ? 'FOTOS: —'
            : 'FOTOS: '.$formatted;
    }

    private function range(int $start, int $end): string
    {
        if ($start === $end) {
            return (string) $start;
        }

        return $end === $start + 1 ? "{$start} E {$end}" : "{$start} A {$end}";
    }
}
