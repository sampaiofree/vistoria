<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\PhotoProcessingStatus;
use App\Models\Inspection;
use Illuminate\Validation\ValidationException;

final class InspectionLocationCoverageValidator
{
    public function __construct(private readonly InspectionLocationGeometryValidator $geometryValidator) {}

    public function validate(Inspection $inspection): void
    {
        $assessments = $inspection->defectAssessments()
            ->with(['defect.categoryDefinition', 'locationMarkers.map', 'locationMarkers.photos'])
            ->whereHas('defect.categoryDefinition', fn ($query) => $query->where('requires_location_map', true)->where('status', 'active'))
            ->get();

        if ($assessments->isEmpty()) {
            return;
        }

        $required = $assessments->reject(fn ($assessment): bool => in_array($assessment->condition, [
            DefectAssessmentCondition::NotLocated,
            DefectAssessmentCondition::NotInspected,
        ], true));
        $categoryIds = $required->pluck('defect.defect_category_id')->unique();
        $maps = $inspection->locationMaps()->with('markers')->whereIn('defect_category_id', $categoryIds)->get();
        $issues = [];

        foreach ($maps as $map) {
            if ($map->processing_status !== InspectionLocationMapProcessingStatus::Ready) {
                $issues[] = "Mapa {$map->title} ainda não está processado";
            }
        }

        foreach ($required as $assessment) {
            $code = $assessment->defect?->code ?? (string) $assessment->id;
            $markers = $assessment->locationMarkers;
            if ($markers->isEmpty()) {
                $issues[] = "{$code} sem marcação";

                continue;
            }

            foreach ($markers as $marker) {
                $map = $marker->map;
                if ($map === null
                    || $marker->organization_id !== $inspection->organization_id
                    || $marker->inspection_id !== $inspection->id
                    || $marker->equipment_id !== $inspection->equipment_id
                    || $map->organization_id !== $inspection->organization_id
                    || $map->inspection_id !== $inspection->id
                    || $map->equipment_id !== $inspection->equipment_id
                    || $map->defect_category_id !== $assessment->defect->defect_category_id) {
                    $issues[] = "{$code} possui marcação inconsistente";

                    continue;
                }

                try {
                    $this->geometryValidator->validate($marker->geometry, $marker->style);
                } catch (ValidationException) {
                    $issues[] = "{$code} possui geometria inválida";
                }

                if ($marker->photos->isEmpty()) {
                    $issues[] = "{$code} possui marcação sem fotografias";

                    continue;
                }

                if ($marker->photos->contains(fn ($photo): bool => $photo->defect_assessment_id !== $assessment->id
                    || $photo->organization_id !== $inspection->organization_id
                    || $photo->inspection_id !== $inspection->id
                    || $photo->processing_status !== PhotoProcessingStatus::Ready)) {
                    $issues[] = "{$code} possui fotografia selecionada ainda não pronta ou incompatível";
                }
            }
        }

        if ($issues !== []) {
            throw ValidationException::withMessages([
                'inspection_location' => 'A cobertura de localização está incompleta: '.implode('; ', array_unique($issues)).'.',
            ]);
        }
    }
}
