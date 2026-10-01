<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Models\DefectAssessment;

final class DefectLocationColor
{
    public const NEUTRAL = '#64748B';

    public function forAssessment(DefectAssessment $assessment): string
    {
        if ($assessment->condition === DefectAssessmentCondition::Treated) {
            return self::NEUTRAL;
        }

        $color = data_get($assessment->classification_snapshot, 'color');

        return is_string($color) && preg_match('/^#[0-9A-F]{6}$/i', $color) === 1
            ? strtoupper($color)
            : self::NEUTRAL;
    }

    /** @return array{stroke:string,fill:string,stroke_width:float,opacity:float,dashed:bool} */
    public function styleForAssessment(DefectAssessment $assessment): array
    {
        $color = $this->forAssessment($assessment);

        return [
            'stroke' => $color,
            'fill' => $color,
            'stroke_width' => 0.005,
            'opacity' => 0.9,
            'dashed' => false,
        ];
    }
}
