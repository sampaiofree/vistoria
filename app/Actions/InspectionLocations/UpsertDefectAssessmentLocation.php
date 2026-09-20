<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Exceptions\StaleDefectAssessmentLocationException;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentLocation;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationGeometryValidator;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpsertDefectAssessmentLocation
{
    public function __construct(private readonly InspectionLocationGeometryValidator $geometryValidator) {}

    /** @param array<string,mixed> $data */
    public function handle(User $actor, DefectAssessment $assessment, array $data): DefectAssessmentLocation
    {
        $assessment->loadMissing(['inspection', 'locationMapVersion', 'location']);
        if ($actor->organization_id !== $assessment->organization_id || ! $actor->can('update', $assessment)) {
            throw ValidationException::withMessages(['location' => 'A avaliacao nao esta disponivel para localizar a avaria.']);
        }
        if ($assessment->locationMapVersion === null || ! $assessment->locationMapVersion->isReady()) {
            throw ValidationException::withMessages(['location' => 'Aguarde o processamento da imagem do mapa.']);
        }

        $this->geometryValidator->validate($data['geometry'], null);

        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessmentLocation {
            $existing = DefectAssessmentLocation::query()
                ->where('organization_id', $assessment->organization_id)
                ->where('defect_assessment_id', $assessment->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $expected = (int) ($data['lock_version'] ?? 0);
                if ($expected !== $existing->lock_version) {
                    throw new StaleDefectAssessmentLocationException('A localizacao foi alterada por outro usuario. Recarregue o editor.');
                }
                $existing->update([
                    'geometry' => $data['geometry'],
                    'label' => TextNormalizer::nullableText($data['label'] ?? null),
                    'confirmed_at' => now(),
                    'confirmed_by' => $actor->id,
                    'lock_version' => $existing->lock_version + 1,
                    'updated_by' => $actor->id,
                ]);

                return $existing->refresh();
            }

            return DefectAssessmentLocation::query()->create([
                'organization_id' => $assessment->organization_id,
                'equipment_id' => $assessment->equipment_id,
                'inspection_id' => $assessment->inspection_id,
                'defect_assessment_id' => $assessment->id,
                'geometry' => $data['geometry'],
                'label' => TextNormalizer::nullableText($data['label'] ?? null),
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }
}
