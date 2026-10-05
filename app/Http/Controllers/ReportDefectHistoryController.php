<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionStatus;
use App\Enums\PhotoProcessingStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Services\Defects\InspectionAssessmentResolver;
use App\Services\Defects\InspectionDefectScope;
use App\Services\Inspections\InspectionReadModelPresenter;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportDefectHistoryController extends Controller
{
    use ResolvesTenantStructure;

    public function __invoke(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        Defect $defect,
        InspectionAssessmentResolver $resolver,
        InspectionDefectScope $scope,
        InspectionReadModelPresenter $presenter,
    ): JsonResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $defect = $this->tenantDefect($tenant, $defect);
        $this->authorize('view', $inspection);

        abort_unless($defect->equipment_id === $inspection->equipment_id, 404);
        abort_unless($scope->handle($inspection)->contains('id', $defect->id), 404);

        $defect->loadMissing('assessments');
        $assessment = $resolver->assessment($inspection, $defect);
        abort_unless($assessment?->isComplete(), 404);
        $this->authorize('view', $assessment);

        $ancestorIds = array_flip($scope->ancestorInspectionIds($assessment->inspection));
        $history = [];
        $seen = [];
        $previousId = $assessment->previous_assessment_id;

        while ($previousId !== null && ! isset($seen[$previousId])) {
            $seen[$previousId] = true;
            $previous = DefectAssessment::query()
                ->forOrganization($tenant->id())
                ->with(['inspection', 'defect', 'photos', 'quantities'])
                ->whereKey($previousId)
                ->where('defect_id', $defect->id)
                ->where('equipment_id', $inspection->equipment_id)
                ->first();

            if ($previous === null) {
                break;
            }

            if ($previous->status === DefectAssessmentStatus::Complete
                && isset($ancestorIds[$previous->inspection_id])
                && $previous->inspection?->status !== InspectionStatus::Canceled
                && (! $request->user()->isClient() || $previous->inspection->status === InspectionStatus::Released)
                && $request->user()->can('view', $previous)) {
                $classificationColor = data_get($previous->classification_snapshot, 'color');
                $history[] = [
                    'public_id' => $previous->public_id,
                    'inspection_number' => $previous->inspection->number,
                    'assessed_at' => $previous->assessed_at?->format('d/m/Y'),
                    'condition_label' => $previous->condition->label(),
                    'classification_code' => data_get($previous->classification_snapshot, 'code') ?? $previous->classification_code,
                    'classification_color' => is_string($classificationColor) && preg_match('/^#[0-9a-f]{6}$/i', $classificationColor) ? $classificationColor : null,
                    'technical_details' => $presenter->reportDefectTechnicalDetails($previous),
                    'photos' => $previous->photos
                        ->filter(fn ($photo): bool => $photo->processing_status === PhotoProcessingStatus::Ready
                            && $photo->optimized_path !== null && $photo->thumbnail_path !== null)
                        ->map(fn ($photo): array => [
                            'id' => $photo->public_id,
                            'caption' => $photo->caption,
                            'url' => route('assessment-photos.show', [$photo, 'optimized']),
                            'thumbnail_url' => route('assessment-photos.show', [$photo, 'thumbnail']),
                        ])->values()->all(),
                ];
            }

            $previousId = $previous->previous_assessment_id;
        }

        return response()->json([
            'assessment_public_id' => $assessment->public_id,
            'history' => $history,
        ]);
    }
}
