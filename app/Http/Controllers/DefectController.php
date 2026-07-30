<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Defects\CreateDefectWithAssessment;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Defects\StoreDefectRequest;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DefectController extends Controller
{
    use ResolvesTenantStructure;

    public function show(
        TenantContext $tenant,
        Request $request,
        Defect $defect,
    ): InertiaResponse {
        $defect = $this->tenantDefect($tenant, $defect);

        $this->authorize('view', $defect);

        $defect->loadMissing([
            'equipment.client',
            'equipment.unit',
            'firstInspection.equipment',
            'draftAssessments.inspection',
            'draftAssessments.creator',
            'draftAssessments.previousAssessment.inspection',
            'draftAssessments.previousAssessment.creator',
            'assessments.inspection',
            'assessments.creator',
            'assessments.previousAssessment.inspection',
            'assessments.previousAssessment.creator',
            'latestAssessment.inspection',
            'latestAssessment.creator',
            'latestAssessment.previousAssessment.inspection',
            'latestAssessment.previousAssessment.creator',
        ]);

        $currentAssessment = $this->currentDefectAssessment($defect);
        $latestCompleteAssessment = $defect->latestAssessment;
        $previousAssessment = $currentAssessment?->previousAssessment
            ?? $latestCompleteAssessment?->previousAssessment;
        $contextInspection = $currentAssessment?->inspection
            ?? $latestCompleteAssessment?->inspection
            ?? $defect->firstInspection;
        $completedAssessments = $this->completedDefectAssessments($defect);
        $user = $request->user();
        $canUpdateAssessment = $currentAssessment !== null && $user?->can('update', $currentAssessment) === true;
        $canCompleteAssessment = $currentAssessment !== null && $user?->can('complete', $currentAssessment) === true;

        return Inertia::render('Defects/Show', [
            'defect' => $this->defectPayload(
                $defect,
                $currentAssessment,
                $latestCompleteAssessment,
                $previousAssessment,
                $canUpdateAssessment,
                $canCompleteAssessment,
            ),
            'assessments' => $completedAssessments
                ->map(fn (DefectAssessment $assessment): array => $this->defectAssessmentPayload($assessment))
                ->values()
                ->all(),
            'back_url' => route('inspections.show', $contextInspection),
            'equipment_url' => route('equipments.show', $defect->equipment),
            'inspection_url' => route('inspections.show', $contextInspection),
        ]);
    }

    public function store(
        StoreDefectRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        CreateDefectWithAssessment $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $this->authorize('create', [Defect::class, $inspection]);

        $defect = $action->handle(
            $request->user(),
            $inspection,
            $request->validated(),
        );

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Avaria criada.');
    }

    /**
     * @return array{id:int, public_id:string, code:string, title:string, origin_description:?string, category:string, category_label:string, status:string, status_label:string, sequence_number:int, repaired_at:?string, archived_at:?string, equipment:array{id:int, public_id:string, tag:string, name:string, show_url:string}, first_inspection:array{id:int, public_id:string, number:?string, show_url:string}, latest_assessment:?array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, location_description:?string, comment:?string, recommendation:?string, reason:?string, internal_notes:?string, assessed_at:?string, snapshot_version:int, defect_snapshot:array<string, mixed>, inspection:array{id:int, public_id:string, number:?string, show_url:string}, creator:?array{id:int, public_id:string, name:string}}, current_assessment:?array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, location_description:?string, comment:?string, recommendation:?string, reason:?string, internal_notes:?string, assessed_at:?string, snapshot_version:int, defect_snapshot:array<string, mixed>, inspection:array{id:int, public_id:string, number:?string, show_url:string}, creator:?array{id:int, public_id:string, name:string}}, previous_assessment:?array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, location_description:?string, comment:?string, recommendation:?string, reason:?string, internal_notes:?string, assessed_at:?string, snapshot_version:int, defect_snapshot:array<string, mixed>, inspection:array{id:int, public_id:string, number:?string, show_url:string}, creator:?array{id:int, public_id:string, name:string}}, latest_complete_assessment:?array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, location_description:?string, comment:?string, recommendation:?string, reason:?string, internal_notes:?string, assessed_at:?string, snapshot_version:int, defect_snapshot:array<string, mixed>, inspection:array{id:int, public_id:string, number:?string, show_url:string}, creator:?array{id:int, public_id:string, name:string}}, assessment_actions:array{update_url:?string, complete_url:?string}, can_update_assessment:bool, can_complete_assessment:bool}
     */
    private function defectPayload(
        Defect $defect,
        ?DefectAssessment $currentAssessment,
        ?DefectAssessment $latestCompleteAssessment,
        ?DefectAssessment $previousAssessment,
        bool $canUpdateAssessment,
        bool $canCompleteAssessment,
    ): array {
        $latestAssessment = $currentAssessment ?? $latestCompleteAssessment;

        return [
            'id' => $defect->id,
            'public_id' => $defect->public_id,
            'code' => $defect->code,
            'title' => $defect->title,
            'origin_description' => $defect->origin_description,
            'category' => $defect->category->value,
            'category_label' => $defect->category->label(),
            'status' => $defect->status->value,
            'status_label' => $defect->status->label(),
            'sequence_number' => $defect->sequence_number,
            'repaired_at' => $defect->repaired_at?->toDateTimeString(),
            'archived_at' => $defect->archived_at?->toDateTimeString(),
            'equipment' => [
                'id' => $defect->equipment->id,
                'public_id' => $defect->equipment->public_id,
                'tag' => $defect->equipment->tag,
                'name' => $defect->equipment->name,
                'show_url' => route('equipments.show', $defect->equipment),
            ],
            'first_inspection' => [
                'id' => $defect->firstInspection->id,
                'public_id' => $defect->firstInspection->public_id,
                'number' => $defect->firstInspection->number,
                'show_url' => route('inspections.show', $defect->firstInspection),
            ],
            'latest_assessment' => $latestAssessment === null
                ? null
                : $this->defectAssessmentPayload($latestAssessment),
            'current_assessment' => $currentAssessment === null
                ? null
                : $this->defectAssessmentPayload($currentAssessment),
            'previous_assessment' => $previousAssessment === null
                ? null
                : $this->defectAssessmentPayload($previousAssessment),
            'latest_complete_assessment' => $latestCompleteAssessment === null
                ? null
                : $this->defectAssessmentPayload($latestCompleteAssessment),
            'assessment_actions' => [
                'update_url' => $canUpdateAssessment && $currentAssessment !== null
                    ? route('defect-assessments.update', $currentAssessment)
                    : null,
                'complete_url' => $canCompleteAssessment && $currentAssessment !== null
                    ? route('defect-assessments.complete', $currentAssessment)
                    : null,
            ],
            'can_update_assessment' => $canUpdateAssessment,
            'can_complete_assessment' => $canCompleteAssessment,
        ];
    }

    private function currentDefectAssessment(Defect $defect): ?DefectAssessment
    {
        return $defect->draftAssessments->first()
            ?? $defect->latestAssessment;
    }

    /**
     * @return \Illuminate\Support\Collection<int, DefectAssessment>
     */
    private function completedDefectAssessments(Defect $defect)
    {
        return $defect->assessments
            ->filter(fn (DefectAssessment $assessment): bool => $assessment->isComplete())
            ->sort(function (DefectAssessment $left, DefectAssessment $right): int {
                return $this->assessmentSortKey($left) <=> $this->assessmentSortKey($right);
            })
            ->values();
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private function assessmentSortKey(DefectAssessment $assessment): array
    {
        $inspection = $assessment->inspection;

        $inspectionKey = $inspection?->inspected_on?->getTimestamp()
            ?? $inspection?->scheduled_for?->getTimestamp()
            ?? $inspection?->created_at?->getTimestamp()
            ?? $inspection?->getKey()
            ?? 0;

        $assessmentKey = $assessment->assessed_at?->getTimestamp()
            ?? $assessment->created_at?->getTimestamp()
            ?? $assessment->getKey()
            ?? 0;

        return [
            (int) $inspectionKey,
            (int) $assessmentKey,
            (int) $assessment->getKey(),
        ];
    }

    /**
     * @return array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, location_description:?string, comment:?string, recommendation:?string, reason:?string, internal_notes:?string, assessed_at:?string, snapshot_version:int, defect_snapshot:array<string, mixed>, inspection:array{id:int, public_id:string, number:?string, show_url:string}, creator:?array{id:int, public_id:string, name:string}}
     */
    private function defectAssessmentPayload(DefectAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'public_id' => $assessment->public_id,
            'condition' => $assessment->condition->value,
            'condition_label' => $assessment->condition->label(),
            'status' => $assessment->status->value,
            'status_label' => $assessment->status->label(),
            'location_description' => $assessment->location_description,
            'comment' => $assessment->comment,
            'recommendation' => $assessment->recommendation,
            'reason' => $assessment->reason,
            'internal_notes' => $assessment->internal_notes,
            'assessed_at' => $assessment->assessed_at?->toDateTimeString(),
            'snapshot_version' => (int) $assessment->snapshot_version,
            'defect_snapshot' => $assessment->defect_snapshot ?? [],
            'inspection' => [
                'id' => $assessment->inspection->id,
                'public_id' => $assessment->inspection->public_id,
                'number' => $assessment->inspection->number,
                'show_url' => route('inspections.show', $assessment->inspection),
            ],
            'creator' => $assessment->creator === null
                ? null
                : [
                    'id' => $assessment->creator->id,
                    'public_id' => $assessment->creator->public_id,
                    'name' => $assessment->creator->name,
                ],
        ];
    }
}
