<?php

namespace Tests;

use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentLocation;
use App\Models\DefectAssessmentQuantity;
use App\Models\DefectLocationMap;
use App\Models\DefectLocationMapVersion;
use App\Enums\DefectCategory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function satisfyAssessmentPublicationRequirements(DefectAssessment $assessment): void
    {
        $assessment->loadMissing('defect');
        if ($assessment->defect->category !== DefectCategory::RoofCladding
            && ! $assessment->quantities()->exists()) {
            DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();
        }
        if (blank($assessment->recommendation)) {
            $assessment->update(['recommendation' => 'Executar a intervenção recomendada.']);
        }

        foreach ([1, 2] as $position) {
            AssessmentPhoto::factory()->ready()->create([
                'organization_id' => $assessment->organization_id,
                'inspection_id' => $assessment->inspection_id,
                'defect_assessment_id' => $assessment->id,
                'position' => $position,
            ]);
        }

        $this->locateAssessment($assessment);
    }

    protected function locateAssessment(DefectAssessment $assessment, bool $confirmed = true): DefectLocationMapVersion
    {
        $assessment->refresh()->load(['defect', 'locationMapVersion', 'location']);
        if ($assessment->locationMapVersion?->isReady()) {
            if ($assessment->location === null) {
                $location = DefectAssessmentLocation::factory()->forAssessment($assessment);
                ($confirmed ? $location->confirmed() : $location)->create();
            } elseif ($confirmed && ! $assessment->location->isConfirmed()) {
                $assessment->location->update(['confirmed_at' => now()]);
            }

            $assessment->unsetRelation('locationMapVersion')->unsetRelation('location');

            return $assessment->locationMapVersion()->firstOrFail();
        }

        $map = DefectLocationMap::query()->firstOrCreate(
            [
                'organization_id' => $assessment->organization_id,
                'defect_id' => $assessment->defect_id,
            ],
            [
                'equipment_id' => $assessment->equipment_id,
            ],
        );
        $version = DefectLocationMapVersion::factory()
            ->forMapAndAssessment($map, $assessment)
            ->ready()
            ->create(['version' => ((int) $map->versions()->max('version')) + 1]);
        $version->update(['background_path' => sprintf(
            'organizations/%d/defects/%s/maps/%s/versions/%s/derivatives/test/background.webp',
            $assessment->organization_id,
            $assessment->defect->public_id,
            $map->public_id,
            $version->public_id,
        )]);
        $assessment->update(['defect_location_map_version_id' => $version->id]);
        $location = DefectAssessmentLocation::factory()->forAssessment($assessment);
        ($confirmed ? $location->confirmed() : $location)->create();
        $assessment->unsetRelation('locationMapVersion')->unsetRelation('location');

        return $version;
    }
}
