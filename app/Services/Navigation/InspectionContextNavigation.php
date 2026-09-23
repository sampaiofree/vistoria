<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectStatus;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionOverviewPhoto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class InspectionContextNavigation
{
    /**
     * @return array<string, mixed>|null
     */
    public function forRequest(Request $request): ?array
    {
        $user = $request->user();
        $inspection = $this->inspectionFromRequest($request);

        if (! $user instanceof User || ! $inspection instanceof Inspection) {
            return null;
        }

        if ($user->organization_id === null
            || $inspection->organization_id !== $user->organization_id
            || ! $user->can('view', $inspection)) {
            return null;
        }

        return $this->build($request, $user, $inspection);
    }

    private function inspectionFromRequest(Request $request): ?Inspection
    {
        $inspection = $request->route('inspection');

        if ($inspection instanceof Inspection) {
            return $inspection;
        }

        $assessment = $request->route('defectAssessment');

        if ($assessment instanceof DefectAssessment) {
            return $assessment->inspection;
        }

        $photo = $request->route('assessmentPhoto');

        if ($photo instanceof AssessmentPhoto) {
            return $photo->inspection;
        }

        $overviewPhoto = $request->route('overviewPhoto');

        if ($overviewPhoto instanceof InspectionOverviewPhoto) {
            return $overviewPhoto->inspection;
        }

        $defect = $request->route('defect');

        if (! $defect instanceof Defect) {
            return null;
        }

        $defect->loadMissing([
            'draftAssessments.inspection',
            'latestAssessment.inspection',
            'firstInspection',
        ]);

        return $defect->draftAssessments->first()?->inspection
            ?? $defect->latestAssessment?->inspection
            ?? $defect->firstInspection;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Request $request, User $user, Inspection $inspection): array
    {
        $inspection->loadMissing('equipment');
        $inspection->loadCount([
            'responsibles',
            'statusHistories',
        ]);

        $defects = $this->defectsForInspection($inspection);
        $canCreateDefect = $user->can('create', [Defect::class, $inspection]);
        $currentDefectId = $this->currentDefectId($request);
        $defectsActive = $request->routeIs(
            'inspections.defects',
            'inspections.defects.create',
            'inspections.reinspection-checklist',
            'defects.*',
            'defect-assessments.*',
        );
        $classificationsActive = $request->routeIs('inspections.classifications');
        $teamActive = $request->routeIs('inspections.team');

        return [
            'mode' => 'inspection',
            'back_url' => route('inspections.index'),
            'back_label' => 'Navegação geral',
            'inspection' => [
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'equipment_tag' => $inspection->equipment->tag,
                'equipment_name' => $inspection->equipment->name,
                'overview_url' => route('inspections.show', $inspection),
            ],
            'items' => [
                [
                    'key' => 'overview',
                    'label' => 'Visão geral',
                    'href' => route('inspections.show', $inspection),
                    'icon' => 'dashboard',
                    'active' => $request->routeIs('inspections.edit')
                        || ($request->routeIs('inspections.show') && ! $teamActive),
                ],
                [
                    'key' => 'report_overview',
                    'label' => 'Vista geral',
                    'href' => route('inspections.report-overview', $inspection),
                    'icon' => 'photos',
                    'active' => $request->routeIs(
                        'inspections.report-overview',
                        'inspections.report-overview.*',
                        'inspection-overview-photos.*',
                    ),
                ],
                [
                    'key' => 'defects',
                    'label' => 'Avarias',
                    'href' => route('inspections.defects', $inspection),
                    'icon' => 'activity',
                    'badge' => (string) $defects->count(),
                    'active' => $defectsActive,
                    'default_open' => $defectsActive,
                    'children' => $this->defectGroups(
                        $inspection,
                        $defects,
                        $currentDefectId,
                        $defectsActive,
                        $canCreateDefect,
                    ),
                ],
                [
                    'key' => 'classifications',
                    'label' => 'Nota M2',
                    'href' => route('inspections.classifications', $inspection),
                    'icon' => 'classification',
                    'active' => $classificationsActive,
                ],
                [
                    'key' => 'team',
                    'label' => 'Equipe e responsáveis',
                    'href' => route('inspections.team', $inspection),
                    'icon' => 'users',
                    'badge' => (string) $inspection->responsibles_count,
                    'active' => $teamActive,
                ],
                [
                    'key' => 'history',
                    'label' => 'Histórico',
                    'href' => route('inspections.history', $inspection),
                    'icon' => 'clock',
                    'badge' => (string) $inspection->status_histories_count,
                    'active' => $request->routeIs('inspections.history'),
                ],
                [
                    'key' => 'report',
                    'label' => 'Relatório',
                    'href' => route('inspections.report-preview', $inspection),
                    'icon' => 'report',
                    'active' => $request->routeIs('inspections.report-preview'),
                ],
            ],
        ];
    }

    /**
     * Keeps the navigation in sync with the inspection read model: defects
     * introduced later are hidden, while still-active inherited defects remain.
     *
     * @return Collection<int, Defect>
     */
    private function defectsForInspection(Inspection $inspection): Collection
    {
        $defects = Defect::query()
            ->forOrganization($inspection->organization_id)
            ->where('equipment_id', $inspection->equipment_id)
            ->with([
                'firstInspection',
                'assessments' => fn ($query) => $query
                    ->where('inspection_id', $inspection->getKey())
                    ->withCount('photos'),
            ])
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->get();

        $inspectionKey = $this->inspectionOrderKey($inspection);

        return $defects
            ->filter(function (Defect $defect) use ($inspection, $inspectionKey): bool {
                if ($defect->assessments->contains('inspection_id', $inspection->getKey())) {
                    return true;
                }

                return $defect->firstInspection !== null
                    && $defect->status === DefectStatus::Active
                    && $this->inspectionOrderKey($defect->firstInspection) <= $inspectionKey;
            })
            ->values();
    }

    /**
     * @param  Collection<int, Defect>  $defects
     * @return array<int, array<string, mixed>>
     */
    private function defectGroups(
        Inspection $inspection,
        Collection $defects,
        ?int $currentDefectId,
        bool $sectionActive,
        bool $canCreateDefect,
    ): array {
        $groups = $defects
            ->groupBy(fn (Defect $defect): string => $defect->categoryCode())
            ->sortBy(fn (Collection $group): array => [
                $group->first()?->category->position() ?? PHP_INT_MAX,
                $group->first()?->categoryCode() ?? '',
            ]);

        $children = $groups
            ->map(function (Collection $group, string $categoryCode) use ($inspection, $currentDefectId, $sectionActive, $groups): array {
                $children = $group->map(function (Defect $defect) use ($inspection, $currentDefectId): array {
                    $assessment = $defect->assessments->firstWhere('inspection_id', $inspection->getKey());
                    $active = $defect->getKey() === $currentDefectId;

                    return [
                        'key' => 'defect-'.$defect->public_id,
                        'label' => $defect->code,
                        'href' => $assessment instanceof DefectAssessment
                            ? route('defect-assessments.show', $assessment)
                            : route('inspections.defects', $inspection).'#defect-'.$defect->public_id,
                        'meta' => $assessment instanceof DefectAssessment
                            ? ($assessment->status === DefectAssessmentStatus::Draft
                                ? $assessment->status->label()
                                : $assessment->condition->label())
                            : 'Avaliação pendente',
                        'secondary_badge' => $this->assessmentPhotoCount($assessment),
                        'tone' => $this->defectTone($assessment),
                        'active' => $active,
                    ];
                })->values();
                $active = $children->contains('active', true);

                return [
                    'key' => 'defect-category-'.$categoryCode,
                    'label' => $categoryCode,
                    'meta' => $group->first()?->categoryLabel(),
                    'badge' => (string) $group->count(),
                    'active' => $active,
                    'default_open' => $active || ($sectionActive && $groups->count() === 1),
                    'children' => $children->all(),
                ];
            })
            ->values()
            ->all();

        if ($canCreateDefect) {
            array_unshift($children, [
                'key' => 'defect-create',
                'label' => '+ Adicionar avaria',
                'href' => route('inspections.defects.create', $inspection),
                'tone' => 'success',
                'active' => request()->routeIs('inspections.defects.create'),
            ]);
        }

        return $children;
    }

    private function currentDefectId(Request $request): ?int
    {
        $assessment = $request->route('defectAssessment');

        if ($assessment instanceof DefectAssessment) {
            return (int) $assessment->defect_id;
        }

        $defect = $request->route('defect');

        return $defect instanceof Defect ? (int) $defect->getKey() : null;
    }

    private function assessmentPhotoCount(?DefectAssessment $assessment): ?string
    {
        if (! $assessment instanceof DefectAssessment) {
            return null;
        }

        return (string) ($assessment->photos_count ?? 0);
    }

    private function defectTone(?DefectAssessment $assessment): string
    {
        if (! $assessment instanceof DefectAssessment || $assessment->status === DefectAssessmentStatus::Draft) {
            return 'warning';
        }

        return match ($assessment->condition) {
            DefectAssessmentCondition::Treated => 'success',
            DefectAssessmentCondition::Canceled, DefectAssessmentCondition::CanceledWithoutRepair => 'danger',
            DefectAssessmentCondition::Reclassified => 'warning',
            default => 'neutral',
        };
    }

    /**
     * @return array{int, int}
     */
    private function inspectionOrderKey(Inspection $inspection): array
    {
        return [
            $inspection->inspected_on?->getTimestamp()
                ?? $inspection->planned_start_on?->getTimestamp()
                ?? $inspection->created_at?->getTimestamp()
                ?? 0,
            (int) $inspection->getKey(),
        ];
    }
}
