<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use Illuminate\Support\Collection;

final class InspectionLocationPhotoNumbering
{
    public function __construct(private readonly InspectionLocationReportCategoryOrder $categoryOrder) {}

    /** @return array<string,int> */
    public function buildForReport(Inspection $inspection, ?Collection $loadedAssessments = null): array
    {
        $assessments = $loadedAssessments ?? DefectAssessment::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->with(['defect', 'photos', 'location', 'locationMapVersion'])
            ->get();

        $numbering = [];
        foreach ($this->categoryOrder->sort(collect(DefectCategory::cases())) as $category) {
            $nextNumber = $category === DefectCategory::AnticorrosiveTreatment ? 5 : 1;
            $ordered = $assessments
                ->filter(fn (DefectAssessment $assessment): bool => $assessment->defect?->category === $category
                    && ! $assessment->condition->isCanceled()
                    && $assessment->locationMapVersion?->isReady()
                    && $assessment->location?->isConfirmed())
                ->sortBy(fn (DefectAssessment $assessment): array => [
                    (int) ($assessment->defect?->sequence_number ?? PHP_INT_MAX),
                    (int) $assessment->id,
                ]);

            foreach ($ordered as $assessment) {
                foreach ($this->orderedAssessmentPhotos($assessment) as $photo) {
                    $numbering[$photo->public_id] ??= $nextNumber++;
                }
            }
        }

        return $numbering;
    }

    /** @return Collection<int,AssessmentPhoto> */
    private function orderedAssessmentPhotos(DefectAssessment $assessment): Collection
    {
        $assessment->loadMissing('photos');

        return $assessment->photos
            ->filter(fn (AssessmentPhoto $photo): bool => $photo->isReady())
            ->sortBy(fn (AssessmentPhoto $photo): array => [(int) $photo->position, (int) $photo->id])
            ->values();
    }

    /** @param iterable<string> $orderedPublicIds @return array<string,int> */
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

    /** @param array<string,int> $numbering @return array<int,int> */
    public function numbersForAssessment(DefectAssessment $assessment, array $numbering): array
    {
        return $this->orderedAssessmentPhotos($assessment)
            ->map(fn (AssessmentPhoto $photo): ?int => $numbering[$photo->public_id] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<string,int> $numbering */
    public function displayLegendForAssessment(DefectAssessment $assessment, array $numbering): string
    {
        if ($assessment->isComplete()) {
            return $this->legend($this->numbersForAssessment($assessment, $numbering));
        }

        $count = $this->orderedAssessmentPhotos($assessment)->count();

        return $count === 1 ? '1 FOTO PRONTA' : "{$count} FOTOS PRONTAS";
    }

    /** @param array<int,int> $numbers */
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

    /** @param array<int,int> $numbers */
    public function legend(array $numbers): string
    {
        $formatted = $this->format($numbers);

        return $formatted === '—' ? 'FOTOS: —' : 'FOTOS: '.$formatted;
    }

    private function range(int $start, int $end): string
    {
        if ($start === $end) {
            return (string) $start;
        }

        return $end === $start + 1 ? "{$start} E {$end}" : "{$start} A {$end}";
    }
}
