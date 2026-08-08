<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\Inspection;

final class BuildReinspectionChecklist
{
    /** @return array{is_reinspection:bool, previous_inspection:?array, total:int, completed:int, pending:int, items:array<int,array<string,mixed>>} */
    public function handle(Inspection $inspection): array
    {
        $inspection->loadMissing('previousInspection');

        if ($inspection->previous_inspection_id === null) {
            return ['is_reinspection' => false, 'previous_inspection' => null, 'total' => 0, 'completed' => 0, 'pending' => 0, 'items' => []];
        }

        $defects = Defect::query()
            ->forOrganization($inspection->organization_id)
            ->where('equipment_id', $inspection->equipment_id)
            ->where('first_inspection_id', '!=', $inspection->getKey())
            ->where('status', DefectStatus::Active->value)
            ->with(['assessments' => fn ($query) => $query->where('inspection_id', $inspection->getKey())->with('previousAssessment')])
            ->orderBy('code')
            ->get();

        $items = $defects->map(function (Defect $defect): array {
            $assessment = $defect->assessments->first();
            $previous = $assessment?->previousAssessment;

            return [
                'id' => $defect->id,
                'public_id' => $defect->public_id,
                'defect_code' => $defect->code,
                'title' => $defect->title,
                'previous_condition' => $previous?->condition?->value,
                'previous_condition_label' => $previous?->condition?->label(),
                'previous_comment' => $previous?->comment,
                'previous_recommendation' => $previous?->recommendation,
                'current_assessment' => $assessment === null ? null : [
                    'id' => $assessment->id,
                    'condition' => $assessment->condition->value,
                    'condition_label' => $assessment->condition->label(),
                    'status' => $assessment->status->value,
                    'status_label' => $assessment->status->label(),
                    'show_url' => route('defect-assessments.show', $assessment),
                ],
                'resolved' => $assessment?->status === DefectAssessmentStatus::Complete,
                'assessment_url' => $assessment === null ? null : route('defect-assessments.show', $assessment),
                'defect_url' => route('defects.show', $defect),
            ];
        })->values();

        return [
            'is_reinspection' => true,
            'previous_inspection' => [
                'id' => $inspection->previousInspection->id,
                'public_id' => $inspection->previousInspection->public_id,
                'number' => $inspection->previousInspection->number,
                'show_url' => route('inspections.show', $inspection->previousInspection),
            ],
            'total' => $items->count(),
            'completed' => $items->where('resolved', true)->count(),
            'pending' => $items->where('resolved', false)->count(),
            'items' => $items->all(),
        ];
    }
}
