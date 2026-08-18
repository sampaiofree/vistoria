<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionStatus;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Models\DefectAssessment;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssessmentAvailability;
use App\Services\InspectionLocations\InspectionLocationCapacity;
use App\Services\InspectionLocations\InspectionLocationGeometryValidator;
use App\Support\TextNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInspectionLocationMarker
{
    public function __construct(
        private readonly InspectionLocationGeometryValidator $geometryValidator,
        private readonly InspectionLocationCapacity $capacity,
        private readonly InspectionLocationAssessmentAvailability $assessmentAvailability,
    ) {}

    public function handle(User $actor, InspectionLocationMap $map, array $data): InspectionLocationMarker
    {
        $assessment = $this->assessment($map, (int) $data['defect_assessment_id']);
        $this->validateContext($actor, $map, $assessment);
        $style = $this->geometryValidator->validate($data['geometry'], $data['style'] ?? null);

        try {
            return DB::transaction(function () use ($actor, $map, $assessment, $data, $style): InspectionLocationMarker {
                $expectedMapVersion = (int) $data['map_lock_version'];
                $advanced = InspectionLocationMap::query()->whereKey($map->id)->where('lock_version', $expectedMapVersion)->update([
                    'lock_version' => $expectedMapVersion + 1,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);
                if ($advanced !== 1) {
                    throw new StaleInspectionLocationMapException('O mapa foi alterado por outro usuário. Recarregue o editor.');
                }

                $this->capacity->assertCanCreateMarker($map);
                $this->assessmentAvailability->assertAvailable($assessment);

                return InspectionLocationMarker::query()->create([
                    'organization_id' => $map->organization_id,
                    'equipment_id' => $map->equipment_id,
                    'inspection_id' => $map->inspection_id,
                    'inspection_location_map_id' => $map->id,
                    'defect_assessment_id' => $assessment->id,
                    'label' => TextNormalizer::nullableText($data['label'] ?? null),
                    'geometry' => $data['geometry'],
                    'style' => $style,
                    'position' => $data['position'] ?? ((int) $map->markers()->max('position') + 1),
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->assessmentAvailability->isAvailable($assessment)) {
                $this->assessmentAvailability->assertAvailable($assessment);
            }

            throw $exception;
        }
    }

    private function assessment(InspectionLocationMap $map, int $assessmentId): DefectAssessment
    {
        return DefectAssessment::query()->forOrganization($map->organization_id)->with('defect')->whereKey($assessmentId)->firstOrFail();
    }

    private function validateContext(User $actor, InspectionLocationMap $map, DefectAssessment $assessment): void
    {
        $editable = in_array($map->inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true);
        $sameScope = $actor->organization_id === $map->organization_id
            && $assessment->organization_id === $map->organization_id
            && $assessment->inspection_id === $map->inspection_id
            && $assessment->equipment_id === $map->equipment_id;
        if (! $editable || ! $sameScope || $map->processing_status !== InspectionLocationMapProcessingStatus::Ready) {
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
        $this->assessmentAvailability->assertAvailable($assessment);
    }
}
