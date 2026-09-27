<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Enums\DefectAssessmentClassificationMethod;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\DefectStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Defects\InspectionAssessmentResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ReinspectionScopePlanner
{
    public function previous(Equipment $equipment): ?Inspection
    {
        return Inspection::query()->forOrganization($equipment->organization_id)
            ->where('equipment_id', $equipment->id)->where('status', InspectionStatus::Released)
            ->orderByDesc('released_at')->orderByDesc('id')->first();
    }

    /** @return Collection<int, array{defect:Defect, source:?DefectAssessment, due_date:mixed}> */
    public function candidates(?Inspection $previous): Collection
    {
        if ($previous === null) {
            return collect();
        }

        $ids = [];
        $seen = [];
        for ($cursor = $previous; $cursor !== null && ! isset($seen[$cursor->id]); $cursor = $cursor->previousInspection) {
            $seen[$cursor->id] = true;
            if ($cursor->status === InspectionStatus::Released) {
                $ids[] = $cursor->id;
            }
        }

        $ranks = array_flip($ids);
        $sources = DefectAssessment::query()->forOrganization($previous->organization_id)
            ->whereIn('inspection_id', $ids)->where('status', DefectAssessmentStatus::Complete)
            ->with(['inspection', 'defect'])->get()
            ->sortBy(fn (DefectAssessment $assessment): int => $ranks[$assessment->inspection_id])
            ->groupBy('defect_id');
        $resolver = app(InspectionAssessmentResolver::class);

        return Defect::query()->forOrganization($previous->organization_id)
            ->where('equipment_id', $previous->equipment_id)->where('status', DefectStatus::Active)
            ->whereIn('first_inspection_id', $ids)->orderBy('sequence_number')->orderBy('id')->get()
            ->map(function (Defect $defect) use ($sources, $previous, $resolver): array {
                $entry = $resolver->entry($previous, $defect->id);
                $source = $entry?->requires_reinspection === false
                    ? $entry->sourceAssessment
                    : $sources->get($defect->id)?->first();

                return [
                    'defect' => $defect,
                    'source' => $source,
                    'due_date' => $entry?->requires_reinspection === false
                        ? $entry->historical_due_date
                        : ($source === null ? null : $resolver->dueDate($source->inspection, $source)),
                ];
            });
    }

    private function savedCandidates(Inspection $inspection): Collection
    {
        return $inspection->defectScopes()->with(['defect', 'sourceAssessment.inspection'])->get()
            ->sortBy(fn ($entry): array => [$entry->defect->sequence_number, $entry->defect_id])
            ->map(fn ($entry): array => [
                'defect' => $entry->defect,
                'source' => $entry->sourceAssessment,
                'due_date' => $entry->historical_due_date,
            ])->values();
    }

    public function options(Equipment $equipment, ?Inspection $planned = null): array
    {
        $useSaved = $planned?->equipment_id === $equipment->id && $planned->reinspection_scope_version !== null;
        $previous = $useSaved ? $planned->previousInspection : $this->previous($equipment);
        $candidates = $useSaved ? $this->savedCandidates($planned) : $this->candidates($previous);
        $selected = $useSaved
            ? $planned->defectScopes()->where('requires_reinspection', true)->pluck('defect_id')->all()
            : $candidates->map(fn (array $item): int => $item['defect']->id)->all();

        return [
            'inspection_type' => $previous === null ? 'initial' : 'reinspection',
            'previous_inspection_id' => $previous?->id,
            'previous_inspection_number' => $previous?->number,
            'selected_ids' => $selected,
            'defects' => $candidates->map(function (array $item): array {
                $defect = $item['defect'];
                $source = $item['source'];
                $method = $source?->classification_method;
                $score = $defect->category === DefectCategory::RoofCladding ? $source?->tel_score : $source?->gut_score;
                $scoreLabel = match (true) {
                    $source === null => 'Sem avaliação publicada',
                    $method === DefectAssessmentClassificationMethod::EngineeringNote => 'Nota de engenharia',
                    $defect->category === DefectCategory::SolidaryStructures => 'Não se aplica',
                    $defect->category === DefectCategory::RoofCladding => $score === null ? 'TEL não informado' : 'TEL '.$score,
                    default => $score === null ? 'GUT não informado' : 'GUT '.$score,
                };

                return [
                    'id' => $defect->id,
                    'code' => $defect->code,
                    'category' => $defect->category->value,
                    'category_label' => $defect->category->label(),
                    'score' => $score,
                    'score_label' => $scoreLabel,
                    'classification_code' => data_get($source?->classification_snapshot, 'code') ?? $source?->classification_code,
                    'classification_color' => data_get($source?->classification_snapshot, 'color'),
                    'must_reinspect' => $source === null,
                    'source_inspection_number' => $source?->inspection->number,
                    'assessed_at' => $source?->assessed_at?->format('d/m/Y'),
                ];
            })->all(),
        ];
    }

    /** Called inside the equipment/inspection transaction, before field work can start. */
    public function save(Inspection $inspection, User $actor, array $data, bool $reset = false): void
    {
        if ($inspection->status !== InspectionStatus::Planned) {
            throw ValidationException::withMessages(['reinspection_defect_ids' => 'O escopo só pode ser alterado enquanto a inspeção estiver planejada.']);
        }

        if (array_key_exists('reinspection_base_id', $data)
            && (string) $data['reinspection_base_id'] !== (string) $inspection->previous_inspection_id) {
            throw ValidationException::withMessages(['reinspection_defect_ids' => 'O histórico do equipamento mudou. Recarregue as avarias antes de salvar.']);
        }

        $saved = ! $reset && $inspection->reinspection_scope_version !== null;
        $candidates = $saved ? $this->savedCandidates($inspection) : $this->candidates($inspection->previousInspection);
        $before = $inspection->defectScopes()->orderBy('defect_id')->get()
            ->map(fn ($entry): array => $entry->only(['defect_id', 'source_assessment_id', 'requires_reinspection']))->all();
        $eligible = $candidates->map(fn (array $item): int => $item['defect']->id)->all();
        $selected = $data['reinspection_defect_ids'] ?? ($saved
            ? collect($before)->where('requires_reinspection', true)->pluck('defect_id')->all()
            : $eligible);

        if (! is_array($selected) || collect($selected)->contains(fn ($id): bool => ! is_int($id) && ! ctype_digit((string) $id))) {
            throw ValidationException::withMessages(['reinspection_defect_ids' => 'Selecione avarias válidas.']);
        }
        $selected = array_map('intval', $selected);
        if (count($selected) !== count(array_unique($selected)) || array_diff($selected, $eligible) !== []) {
            throw ValidationException::withMessages(['reinspection_defect_ids' => 'A seleção contém avarias que não pertencem ao histórico elegível deste equipamento.']);
        }
        if ($eligible !== [] && $selected === []) {
            throw ValidationException::withMessages(['reinspection_defect_ids' => 'Selecione ao menos uma avaria para reinspecionar.']);
        }
        foreach ($candidates as $item) {
            if ($item['source'] === null && ! in_array($item['defect']->id, $selected, true)) {
                throw ValidationException::withMessages(['reinspection_defect_ids' => 'Avarias sem avaliação histórica publicada precisam ser reinspecionadas.']);
            }
        }

        $inspection->defectScopes()->delete();
        foreach ($candidates as $item) {
            $inspection->defectScopes()->create([
                'organization_id' => $inspection->organization_id,
                'defect_id' => $item['defect']->id,
                'source_assessment_id' => $item['source']?->id,
                'requires_reinspection' => in_array($item['defect']->id, $selected, true),
                'historical_due_date' => $item['due_date'],
            ]);
        }
        $inspection->update(['reinspection_scope_version' => 1]);
        $inspection->unsetRelation('defectScopes');
        $after = $inspection->defectScopes()->orderBy('defect_id')->get()
            ->map(fn ($entry): array => $entry->only(['defect_id', 'source_assessment_id', 'requires_reinspection']))->all();

        if ($before !== $after) {
            InspectionStatusHistory::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'from_status' => InspectionStatus::Planned,
                'to_status' => InspectionStatus::Planned,
                'changed_by' => $actor->id,
                'reason' => 'Escopo de reinspeção atualizado.',
                'metadata' => ['event' => 'reinspection_scope_updated', 'before' => $before, 'after' => $after],
                'created_at' => now(),
            ]);
        }
    }
}
