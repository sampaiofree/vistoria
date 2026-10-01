<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectCategory;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionDefectScope;
use App\Services\Reports\AssessmentTreatmentDueDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Resolves the actual assessment or the immutable historical reference for this inspection. */
final class InspectionAssessmentResolver
{
    public function entry(Inspection $inspection, int $defectId): ?InspectionDefectScope
    {
        if ($inspection->reinspection_scope_version === null) {
            return null;
        }

        $inspection->loadMissing('defectScopes.sourceAssessment.inspection');

        return $inspection->defectScopes->firstWhere('defect_id', $defectId);
    }

    public function isHistorical(Inspection $inspection, int $defectId): bool
    {
        return $this->entry($inspection, $defectId)?->requires_reinspection === false;
    }

    public function requiresAssessment(Inspection $inspection, Defect $defect): bool
    {
        return $inspection->reinspection_scope_version === null
            || $defect->first_inspection_id === $inspection->id
            || $this->entry($inspection, $defect->id)?->requires_reinspection === true;
    }

    public function assessment(Inspection $inspection, Defect $defect): ?DefectAssessment
    {
        $entry = $this->entry($inspection, $defect->id);

        return $entry?->requires_reinspection === false
            ? $entry->sourceAssessment
            : $defect->assessments->firstWhere('inspection_id', $inspection->id);
    }

    /** Also protects callers of domain actions that do not pass through HTTP policies. */
    public function ensureMutable(DefectAssessment $assessment): void
    {
        if ($assessment->inspection->status->isFinal()
            || ! $this->requiresAssessment($assessment->inspection, $assessment->defect)) {
            throw ValidationException::withMessages([
                'assessment' => 'Esta avaliação é histórica e está bloqueada para edição.',
            ]);
        }
    }

    /** @return Builder<DefectAssessment> */
    public function query(Inspection $inspection): Builder
    {
        $query = DefectAssessment::query()->forOrganization($inspection->organization_id)->with('inspection');
        if ($inspection->reinspection_scope_version === null) {
            return $query->where('inspection_id', $inspection->id);
        }

        $inspection->loadMissing('defectScopes');
        $historical = $inspection->defectScopes->where('requires_reinspection', false);

        return $query->where(function (Builder $query) use ($inspection, $historical): void {
            $query->where(fn (Builder $current) => $current
                ->where('inspection_id', $inspection->id)
                ->whereNotIn('defect_id', $historical->pluck('defect_id')))
                ->orWhereIn('id', $historical->pluck('source_assessment_id')->filter());
        });
    }

    public function dueDate(Inspection $inspection, DefectAssessment $assessment): ?CarbonInterface
    {
        $entry = $this->entry($inspection, $assessment->defect_id);
        if ($entry?->requires_reinspection === false) {
            return $entry->historical_due_date;
        }

        $category = data_get($assessment->defect_snapshot, 'defect.category');

        return AssessmentTreatmentDueDate::forClassification(
            $inspection->inspected_on,
            DefectCategory::tryFrom($category === 'CIVIL' ? 'CV' : (string) $category) ?? $assessment->defect->category,
            data_get($assessment->classification_snapshot, 'code') ?? $assessment->classification_code,
        );
    }

    public function url(Inspection $inspection, Defect $defect, ?DefectAssessment $assessment): ?string
    {
        if ($this->isHistorical($inspection, $defect->id)) {
            return route('inspections.defects.historical', [$inspection, $defect]);
        }

        return $assessment === null ? null : route('defect-assessments.show', $assessment);
    }

    public function historicalLabel(Inspection $inspection, DefectAssessment $assessment): ?string
    {
        if (! $this->isHistorical($inspection, $assessment->defect_id)) {
            return null;
        }

        return 'Histórico mantido · '.$assessment->inspection->number.' · '.($assessment->assessed_at?->format('d/m/Y') ?? 'Data não informada');
    }
}
