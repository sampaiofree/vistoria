<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionStatus;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Exceptions\StaleInspectionLocationMarkerException;
use App\Models\DefectAssessment;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssessmentAvailability;
use App\Services\InspectionLocations\InspectionLocationGeometryValidator;
use App\Support\TextNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateInspectionLocationMarker
{
    public function __construct(
        private readonly InspectionLocationGeometryValidator $geometryValidator,
        private readonly InspectionLocationAssessmentAvailability $assessmentAvailability,
    ) {}

    public function handle(User $actor, InspectionLocationMarker $marker, array $data): InspectionLocationMarker
    {
        $map = $marker->map;
        $assessment = DefectAssessment::query()->forOrganization($map->organization_id)->with('defect')->whereKey($data['defect_assessment_id'])->firstOrFail();
        $this->validateContext($actor, $map, $assessment, $marker);
        $style = $this->geometryValidator->validate($data['geometry'], $data['style'] ?? null);

        try {
            DB::transaction(function () use ($actor, $marker, $map, $assessment, $data, $style): void {
                $assessmentChanged = (int) $marker->defect_assessment_id !== (int) $assessment->id;
                $expectedMarkerVersion = (int) $data['lock_version'];
                $updated = InspectionLocationMarker::query()->whereKey($marker->id)->where('lock_version', $expectedMarkerVersion)->update([
                    'defect_assessment_id' => $assessment->id,
                    'label' => TextNormalizer::nullableText($data['label'] ?? null),
                    'geometry' => $data['geometry'],
                    'style' => $style,
                    'position' => $data['position'],
                    'lock_version' => $expectedMarkerVersion + 1,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);
                if ($updated !== 1) {
                    throw new StaleInspectionLocationMarkerException('A marcação foi alterada por outro usuário. Recarregue o editor.');
                }

                if ($assessmentChanged) {
                    DB::table('inspection_location_marker_photos')->where('inspection_location_marker_id', $marker->id)->delete();
                }

                $expectedMapVersion = (int) $data['map_lock_version'];
                $advanced = InspectionLocationMap::query()->whereKey($map->id)->where('lock_version', $expectedMapVersion)->update([
                    'lock_version' => $expectedMapVersion + 1,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);
                if ($advanced !== 1) {
                    throw new StaleInspectionLocationMapException('O mapa foi alterado por outro usuário. Recarregue o editor.');
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->assessmentAvailability->isAvailable($assessment, $marker)) {
                $this->assessmentAvailability->assertAvailable($assessment, $marker);
            }

            throw $exception;
        }

        return $marker->refresh();
    }

    private function validateContext(User $actor, InspectionLocationMap $map, DefectAssessment $assessment, InspectionLocationMarker $marker): void
    {
        if ($actor->organization_id !== $map->organization_id
            || $assessment->inspection_id !== $map->inspection_id
            || $assessment->equipment_id !== $map->equipment_id
            || $map->processing_status !== InspectionLocationMapProcessingStatus::Ready
            || ! in_array($map->inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)) {
            throw ValidationException::withMessages(['map' => 'O mapa não está disponível para marcação.']);
        }
        if ((int) $assessment->defect->defect_category_id !== (int) $map->defect_category_id) {
            throw ValidationException::withMessages(['defect_assessment_id' => 'A avaliação deve pertencer à mesma categoria do mapa.']);
        }
        if ($assessment->status !== DefectAssessmentStatus::Complete) {
            throw ValidationException::withMessages(['defect_assessment_id' => 'Publique a avaliação antes de vinculá-la ao mapa.']);
        }
        if (in_array($assessment->condition, [
            DefectAssessmentCondition::NotLocated,
            DefectAssessmentCondition::NotInspected,
        ], true)) {
            throw ValidationException::withMessages(['defect_assessment_id' => 'Esta condição de avaliação não recebe marcação no mapa.']);
        }
        $this->assessmentAvailability->assertAvailable($assessment, $marker);
    }
}
