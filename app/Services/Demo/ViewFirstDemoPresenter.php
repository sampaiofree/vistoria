<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Read model for the 06B View First demonstration.
 *
 * GUT/CV, characterization, quantities and photographic evidence intentionally
 * live here until their definitive modules have persistence of their own.
 */
final class ViewFirstDemoPresenter
{
    public const REPORT_REVISION = '00 — Demonstração';

    /**
     * @return array{criticality: null|array{value:string, label:string, is_provisional:bool}}
     */
    public function equipment(Equipment $equipment): array
    {
        $equipment->loadMissing('defects');

        if ($equipment->defects->isEmpty()) {
            return ['criticality' => null];
        }

        $classification = $equipment->defects
            ->map(fn (Defect $defect): array => $this->technicalData($defect)['classification'])
            ->sortBy(fn (array $item): int => $this->criticalityRank($item['code']))
            ->first() ?? $this->classification(null);

        return [
            'criticality' => [
                'value' => $classification['code'],
                'label' => $classification['label'],
                'is_provisional' => true,
            ],
        ];
    }

    /**
     * @return array{completed:int, total:int, percentage:int}
     */
    public function progress(Inspection $inspection): array
    {
        $defects = $this->defectsForInspection($inspection);
        $total = $defects->count();
        $completed = $defects
            ->filter(function (Defect $defect) use ($inspection): bool {
                $assessment = $defect->assessments
                    ->firstWhere('inspection_id', $inspection->getKey());

                return $assessment?->status === DefectAssessmentStatus::Complete;
            })
            ->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
        ];
    }

    /**
     * @param  array<string, mixed>  $inspectionPayload
     * @return array<string, mixed>
     */
    public function inspection(
        Inspection $inspection,
        User $user,
        array $inspectionPayload,
        string $activeTab,
    ): array {
        $inspection->loadMissing('organization');

        $defects = $this->defectsForInspection($inspection);

        $items = $defects
            ->map(fn (Defect $defect): array => $this->defectCard($inspection, $defect, $user))
            ->all();

        $summary = $this->summary($items);
        $photos = $this->photos($items);

        return [
            'inspection' => array_merge(
                $inspectionPayload,
                ['defects' => $items],
                $this->inspectionLinks($inspection),
            ),
            'summary' => $summary,
            'tabs' => $this->tabs($inspection, $summary, count($photos)),
            'active_tab' => $activeTab,
            'content' => $this->content(
                $activeTab,
                $inspection,
                $items,
                $photos,
                $summary,
                $inspectionPayload,
            ),
            'demo' => $this->demoMetadata(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assessment(DefectAssessment $assessment, User $user): array
    {
        $assessment->loadMissing([
            'defect.equipment.client',
            'defect.equipment.unit',
            'inspection.equipment.defects.assessments.inspection',
            'inspection.equipment.defects.assessments.creator',
            'inspection.equipment.defects.firstInspection',
            'previousAssessment.inspection',
            'previousAssessment.creator',
            'creator',
        ]);

        $technical = $this->technicalData($assessment->defect);
        $items = $this->defectsForInspection($assessment->inspection);
        $position = $items->search(
            fn (Defect $defect): bool => $defect->getKey() === $assessment->defect_id,
        );

        $previousUrl = $this->adjacentAssessmentUrl($items, $position, -1, $assessment->inspection);
        $nextUrl = $this->adjacentAssessmentUrl($items, $position, 1, $assessment->inspection);
        $canUpdate = $user->can('update', $assessment);
        $canComplete = $user->can('complete', $assessment);

        return [
            'assessment' => $this->assessmentPayload($assessment, true),
            'previous_assessment' => $assessment->previousAssessment === null
                ? null
                : $this->assessmentPayload($assessment->previousAssessment),
            'classification' => $technical['classification'],
            'gut' => $technical['gut'],
            'characterization' => $technical['characterization'],
            'quantities' => $technical['quantities'],
            'evidence' => $this->evidenceForDefect($assessment->defect, $technical, $assessment),
            'assessment_navigation' => [
                'previous_url' => $previousUrl,
                'next_url' => $nextUrl,
                'inspection_url' => route('inspections.show', $assessment->inspection),
                'defects_url' => route('inspections.defects', $assessment->inspection),
                'position' => is_int($position) ? $position + 1 : 1,
                'total' => $items->count(),
            ],
            'condition_options' => collect(DefectAssessmentCondition::options())
                ->when(
                    $assessment->inspection_id !== $assessment->defect->first_inspection_id,
                    fn (Collection $options): Collection => $options
                        ->reject(fn (array $option): bool => $option['value'] === DefectAssessmentCondition::New->value),
                )
                ->values()
                ->all(),
            'capabilities' => [
                'update' => $canUpdate,
                'complete' => $canComplete,
                'update_url' => $canUpdate
                    ? route('defect-assessments.update', $assessment)
                    : null,
                'complete_url' => $canComplete
                    ? route('defect-assessments.complete', $assessment)
                    : null,
            ],
            'demo' => $this->demoMetadata(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function inspectionLinks(Inspection $inspection): array
    {
        return [
            'overview_url' => route('inspections.show', $inspection),
            'defects_url' => route('inspections.defects', $inspection),
            'photos_url' => route('inspections.photos', $inspection),
            'documents_url' => route('inspections.documents', $inspection),
            'history_url' => route('inspections.history', $inspection),
            'report_url' => route('inspections.report-preview', $inspection),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, array<string, mixed>>
     */
    private function tabs(Inspection $inspection, array $summary, int $photoCount): array
    {
        return [
            ['key' => 'overview', 'label' => 'Visão geral', 'url' => route('inspections.show', $inspection)],
            ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection), 'count' => $summary['total']],
            ['key' => 'photos', 'label' => 'Fotografias', 'url' => route('inspections.photos', $inspection), 'count' => $photoCount],
            ['key' => 'documents', 'label' => 'Documentos', 'url' => route('inspections.documents', $inspection), 'count' => $inspection->referenceDocuments->count()],
            ['key' => 'history', 'label' => 'Histórico', 'url' => route('inspections.history', $inspection), 'count' => $inspection->statusHistories->count()],
            ['key' => 'report', 'label' => 'Relatório', 'url' => route('inspections.report-preview', $inspection)],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $photos
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $inspectionPayload
     * @return array<string, mixed>
     */
    private function content(
        string $activeTab,
        Inspection $inspection,
        array $items,
        array $photos,
        array $summary,
        array $inspectionPayload,
    ): array {
        return match ($activeTab) {
            'defects' => [
                'items' => $items,
                'filters' => [
                    ['key' => 'all', 'label' => 'Todas', 'count' => $summary['total']],
                    ['key' => 'critical', 'label' => 'Críticas', 'count' => $summary['critical']],
                    ['key' => 'pending', 'label' => 'Pendentes', 'count' => $summary['pending']],
                    ['key' => 'repaired', 'label' => 'Reparadas', 'count' => $summary['repaired']],
                    ['key' => 'not_inspected', 'label' => 'Não inspecionadas', 'count' => $summary['not_inspected']],
                ],
            ],
            'photos' => [
                'items' => $photos,
                'counts' => collect($photos)
                    ->countBy('status')
                    ->all(),
            ],
            'documents' => [
                'items' => $inspectionPayload['reference_documents'] ?? [],
                'reference_document_ids' => $inspectionPayload['reference_document_ids'] ?? [],
                'empty_message' => 'Nenhum documento técnico foi vinculado a esta inspeção.',
            ],
            'history' => [
                'items' => $inspectionPayload['history'] ?? [],
                'previous_inspection' => $inspectionPayload['previous_inspection'] ?? null,
                'next_inspections' => $inspectionPayload['next_inspections'] ?? [],
            ],
            'report' => $this->report($inspection, $items, $photos, $summary, $inspectionPayload),
            default => [
                'metrics' => [
                    ['key' => 'progress', 'label' => 'Avaliações concluídas', 'value' => sprintf('%d/%d', $summary['completed'], $summary['total']), 'detail' => $summary['progress_percent'].'%'],
                    ['key' => 'criticality', 'label' => 'Criticidade atual', 'value' => $summary['criticality']['code'], 'detail' => $summary['criticality']['label']],
                    ['key' => 'critical', 'label' => 'Avarias críticas', 'value' => (string) $summary['critical'], 'detail' => 'prioridade técnica'],
                    ['key' => 'pending', 'label' => 'Pendências', 'value' => (string) $summary['pending'], 'detail' => 'avaliação em aberto'],
                ],
                'highlights' => collect($items)
                    ->filter(fn (array $item): bool => in_array($item['classification']['code'], ['CV-1', 'CV-2'], true) || $item['is_pending'])
                    ->take(3)
                    ->values()
                    ->all(),
                'primary_action' => [
                    'label' => $summary['pending'] > 0 ? 'Continuar avaliações' : 'Revisar avarias',
                    'url' => $this->firstPendingUrl($items) ?? route('inspections.defects', $inspection),
                ],
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summary(array $items): array
    {
        $collection = collect($items);
        $total = count($items);
        $completed = $collection->where('assessment.status', DefectAssessmentStatus::Complete->value)->count();
        $criticality = $collection
            ->pluck('classification')
            ->sortBy(fn (array $classification): int => $this->criticalityRank($classification['code']))
            ->first() ?? $this->classification(null);

        $conditionBreakdown = $collection
            ->groupBy(fn (array $item): string => $item['assessment']['condition'] ?? 'pending')
            ->map(function (Collection $group, string $condition): array {
                $first = $group->first();

                return [
                    'key' => $condition,
                    'label' => $first['condition_label'] ?? 'Pendente',
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        $classificationBreakdown = $collection
            ->groupBy('classification.code')
            ->map(function (Collection $group, string $code): array {
                $first = $group->first();

                return [
                    'code' => $code,
                    'label' => $first['classification']['label'] ?? 'Não classificada',
                    'count' => $group->count(),
                    'historical_count' => $group
                        ->where('classification.historical', true)
                        ->count(),
                ];
            })
            ->sortBy(fn (array $item): int => $this->criticalityRank($item['code']))
            ->values()
            ->all();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'progress_percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            'critical' => $collection->whereIn('classification.code', ['CV-1', 'CV-2'])->count(),
            'repaired' => $collection->where('assessment.condition', DefectAssessmentCondition::Repaired->value)->count(),
            'not_inspected' => $collection->where('assessment.condition', DefectAssessmentCondition::NotInspected->value)->count(),
            'criticality' => $criticality,
            'condition_breakdown' => $conditionBreakdown,
            'classification_breakdown' => $classificationBreakdown,
            'by_condition' => $collection
                ->groupBy('assessment.condition')
                ->map->count()
                ->all(),
            'by_classification' => $collection
                ->groupBy('classification.code')
                ->map->count()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defectCard(Inspection $inspection, Defect $defect, User $user): array
    {
        $assessment = $defect->assessments
            ->firstWhere('inspection_id', $inspection->getKey());
        $technical = $this->technicalData($defect);
        $isPending = $assessment === null || $assessment->status === DefectAssessmentStatus::Draft;
        $canCreate = $assessment === null
            && $user->can('create', [DefectAssessment::class, $inspection, $defect]);

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
            'sequence_number' => (int) $defect->sequence_number,
            'assessment' => $assessment === null ? null : $this->assessmentPayload($assessment),
            'condition' => $assessment?->condition->value,
            'condition_label' => $assessment?->condition->label() ?? 'Pendente',
            'assessment_status' => $assessment?->status->value ?? 'not_assessed',
            'is_pending' => $isPending,
            'is_repaired' => $assessment?->condition === DefectAssessmentCondition::Repaired,
            'is_not_inspected' => $assessment?->condition === DefectAssessmentCondition::NotInspected,
            'classification' => $technical['classification'],
            'gut' => $technical['gut'],
            'characterization' => $technical['characterization'],
            'quantities' => $technical['quantities'],
            'evidence' => $this->evidenceForDefect($defect, $technical, $assessment),
            'assessment_url' => $assessment === null
                ? null
                : route('defect-assessments.show', $assessment),
            'assessment_store_url' => $canCreate
                ? route('inspections.defects.assessments.store', [$inspection, $defect])
                : null,
            'show_url' => route('defects.show', $defect),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessmentPayload(DefectAssessment $assessment, bool $withDefect = false): array
    {
        $payload = [
            'id' => $assessment->id,
            'public_id' => $assessment->public_id,
            'condition' => $assessment->condition->value,
            'condition_label' => $assessment->condition->label(),
            'condition_requires_reason' => $assessment->condition->requiresReason(),
            'status' => $assessment->status->value,
            'status_label' => $assessment->status->label(),
            'location_description' => $assessment->location_description,
            'comment' => $assessment->comment,
            'recommendation' => $assessment->recommendation,
            'reason' => $assessment->reason,
            'internal_notes' => $assessment->internal_notes,
            'assessed_at' => $assessment->assessed_at?->format('d/m/Y H:i'),
            'assessed_at_iso' => $assessment->assessed_at?->toISOString(),
            'snapshot_version' => (int) $assessment->snapshot_version,
            'defect_snapshot' => $assessment->defect_snapshot ?? [],
            'show_url' => route('defect-assessments.show', $assessment),
            'inspection' => [
                'id' => $assessment->inspection->id,
                'public_id' => $assessment->inspection->public_id,
                'number' => $assessment->inspection->number,
                'status' => $assessment->inspection->status->value,
                'status_label' => $assessment->inspection->status->label(),
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

        if ($withDefect) {
            $payload['defect'] = [
                'id' => $assessment->defect->id,
                'public_id' => $assessment->defect->public_id,
                'code' => $assessment->defect->code,
                'title' => $assessment->defect->title,
                'origin_description' => $assessment->defect->origin_description,
                'category' => $assessment->defect->category->value,
                'category_label' => $assessment->defect->category->label(),
                'status' => $assessment->defect->status->value,
                'status_label' => $assessment->defect->status->label(),
                'equipment' => [
                    'id' => $assessment->defect->equipment->id,
                    'public_id' => $assessment->defect->equipment->public_id,
                    'tag' => $assessment->defect->equipment->tag,
                    'name' => $assessment->defect->equipment->name,
                    'show_url' => route('equipments.show', $assessment->defect->equipment),
                ],
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalData(Defect $defect): array
    {
        $title = Str::lower(Str::ascii($defect->title));

        $data = match (true) {
            Str::contains($title, 'fissura longitudinal') => [
                'cv' => 'CV-2', 'gut' => [3, 4, 3],
                'characterization' => ['Manifestação' => 'Fissura longitudinal', 'Elemento' => 'Pedestal de concreto', 'Abertura estimada' => '0,8 mm'],
                'quantities' => [['label' => 'Extensão', 'value' => '1,85', 'unit' => 'm'], ['label' => 'Abertura máxima', 'value' => '0,8', 'unit' => 'mm']],
                'photo_status' => 'ready',
            ],
            Str::contains($title, 'desplacamento') => [
                'cv' => 'CV-3', 'gut' => [3, 5, 2],
                'characterization' => ['Manifestação' => 'Desplacamento', 'Elemento' => 'Base do motor', 'Exposição' => 'Cobrimento comprometido'],
                'quantities' => [['label' => 'Área estimada', 'value' => '0,32', 'unit' => 'm²'], ['label' => 'Profundidade média', 'value' => '18', 'unit' => 'mm']],
                'photo_status' => 'processing',
            ],
            Str::contains($title, 'corrosao') => [
                'cv' => 'CV-3', 'gut' => [3, 4, 2],
                'characterization' => ['Manifestação' => 'Corrosão aparente', 'Elemento' => 'Chumbadores', 'Intensidade' => 'Moderada'],
                'quantities' => [['label' => 'Chumbadores afetados', 'value' => '4', 'unit' => 'un.']],
                'photo_status' => 'ready',
            ],
            Str::contains($title, 'selagem') => [
                'cv' => 'CV-4', 'gut' => [3, 4, 1],
                'characterization' => ['Manifestação' => 'Falha de selagem', 'Elemento' => 'Interface base/piso', 'Continuidade' => 'Descontínua'],
                'quantities' => [['label' => 'Extensão afetada', 'value' => '2,40', 'unit' => 'm']],
                'photo_status' => 'pending',
            ],
            Str::contains($title, 'umidade') => [
                'cv' => 'CV-5', 'gut' => [3, 1, 2],
                'characterization' => ['Manifestação' => 'Umidade superficial', 'Elemento' => 'Canaleta adjacente', 'Aspecto' => 'Sem percolação ativa'],
                'quantities' => [['label' => 'Área observada', 'value' => '0,75', 'unit' => 'm²']],
                'photo_status' => 'failed',
            ],
            Str::contains($title, 'fissura capilar') => [
                'cv' => 'CV-3', 'gut' => null,
                'characterization' => ['Manifestação' => 'Fissura capilar reparada', 'Elemento' => 'Bloco de fundação', 'Tratamento' => 'Selagem confirmada'],
                'quantities' => [['label' => 'Trecho reparado', 'value' => '0,95', 'unit' => 'm']],
                'photo_status' => 'ready',
                'historical' => true,
            ],
            Str::contains($title, 'sem acesso'), Str::contains($title, 'regiao posterior') => [
                'cv' => null, 'gut' => null,
                'characterization' => ['Situação' => 'Não inspecionada', 'Elemento' => 'Região posterior do pedestal', 'Restrição' => 'Acesso físico impedido'],
                'quantities' => [],
                'photo_status' => 'ready',
            ],
            default => [
                'cv' => 'CV-4', 'gut' => null,
                'characterization' => ['Categoria' => $defect->category->label(), 'Elemento' => 'Equipamento inspecionado'],
                'quantities' => [],
                'photo_status' => 'ready',
            ],
        };

        $gut = $data['gut'];

        return [
            'classification' => $this->classification($data['cv'], (bool) ($data['historical'] ?? false)),
            'gut' => $gut === null
                ? null
                : [
                    'severity' => $gut[0],
                    'urgency' => $gut[1],
                    'tendency' => $gut[2],
                    'score' => $gut[0] * $gut[1] * $gut[2],
                    'formula' => sprintf('%d×%d×%d = %d', $gut[0], $gut[1], $gut[2], $gut[0] * $gut[1] * $gut[2]),
                    'provisional' => true,
                ],
            'characterization' => collect($data['characterization'])
                ->map(fn (string $value, string $label): array => compact('label', 'value'))
                ->values()
                ->all(),
            'quantities' => $data['quantities'],
            'photo_status' => $data['photo_status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function classification(?string $code, bool $historical = false): array
    {
        $labels = [
            'CV-1' => ['Crítica', 'critical', 'Intervenção imediata'],
            'CV-2' => ['Alta', 'danger', 'Tratar em até 30 dias'],
            'CV-3' => ['Moderada', 'warning', 'Tratar em até 90 dias'],
            'CV-4' => ['Baixa', 'info', 'Programar no próximo ciclo'],
            'CV-5' => ['Mínima', 'success', 'Manter monitoramento'],
        ];

        if ($code === null) {
            return [
                'code' => '—',
                'label' => 'Não classificada',
                'tone' => 'neutral',
                'deadline_label' => null,
                'provisional' => true,
                'historical' => $historical,
            ];
        }

        return [
            'code' => $code,
            'label' => $labels[$code][0] ?? 'Não classificada',
            'tone' => $labels[$code][1] ?? 'neutral',
            'deadline_label' => $labels[$code][2] ?? null,
            'provisional' => true,
            'historical' => $historical,
        ];
    }

    /**
     * @param  array<string, mixed>  $technical
     * @return array<int, array<string, mixed>>
     */
    private function evidenceForDefect(
        Defect $defect,
        array $technical,
        ?DefectAssessment $assessment = null,
    ): array {
        $status = $technical['photo_status'];

        return [[
            'id' => 'evidence-'.$defect->public_id.'-1',
            'defect_id' => $defect->id,
            'status' => $status,
            'status_label' => $this->photoStatusLabel($status),
            'title' => 'Vista técnica — '.$defect->code,
            'caption' => $defect->title,
            'location' => $assessment?->location_description ?? 'Localização registrada na inspeção',
            'illustrative' => true,
            'url' => null,
            'placeholder_variant' => (($defect->sequence_number - 1) % 4) + 1,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function photos(array $items): array
    {
        return collect($items)
            ->flatMap(fn (array $item): array => $item['evidence'])
            ->values()
            ->all();
    }

    private function photoStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'failed' => 'Falha no processamento',
            default => 'Disponível',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $photos
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $inspectionPayload
     * @return array<string, mixed>
     */
    private function report(
        Inspection $inspection,
        array $items,
        array $photos,
        array $summary,
        array $inspectionPayload,
    ): array {
        $readyPhotos = collect($photos)->where('status', 'ready')->values()->all();

        return [
            'revision' => self::REPORT_REVISION,
            'generated_label' => 'Prévia preparada para apresentação',
            'cover' => [
                'eyebrow' => 'Relatório técnico de inspeção CIVIL',
                'title' => $inspection->number ?? 'Inspeção técnica',
                'client' => $inspection->equipment->client?->name,
                'provider' => $inspection->organization?->name,
                'equipment_tag' => $inspection->equipment->tag,
                'equipment_name' => $inspection->equipment->name,
                'inspection_type' => $inspection->inspection_type->label(),
                'inspected_on' => $inspection->inspected_on?->format('d/m/Y') ?? $inspection->scheduled_for?->format('d/m/Y'),
                'revision' => self::REPORT_REVISION,
            ],
            'executive_summary' => [
                'criticality' => $summary['criticality'],
                'headline' => $summary['critical'] > 0
                    ? 'O equipamento requer tratamento prioritário das manifestações de maior criticidade.'
                    : 'A condição observada permite acompanhamento no ciclo programado.',
                'description' => sprintf(
                    '%d avarias foram consolidadas; %d avaliações estão concluídas e %d permanece em aberto.',
                    $summary['total'],
                    $summary['completed'],
                    $summary['pending'],
                ),
                'metrics' => $summary,
            ],
            'sections' => [
                [
                    'key' => 'defects',
                    'title' => 'Avarias e avaliações CIVIL',
                    'items' => $items,
                ],
                [
                    'key' => 'evidence',
                    'title' => 'Registro fotográfico',
                    'items' => $readyPhotos,
                ],
                [
                    'key' => 'responsibles',
                    'title' => 'Responsabilidade técnica',
                    'items' => $inspectionPayload['responsibles'] ?? [],
                ],
                [
                    'key' => 'documents',
                    'title' => 'Documentos de referência',
                    'items' => $inspectionPayload['reference_documents'] ?? [],
                ],
            ],
            'print_enabled' => true,
            'pdf_enabled' => false,
            'pdf_disabled_reason' => 'A geração do PDF oficial será habilitada no módulo de relatórios.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function demoMetadata(): array
    {
        return [
            'enabled' => true,
            'provisional_notice' => 'GUT, classificação CV, caracterização e quantitativos são demonstrativos e não são persistidos nesta etapa.',
            'photo_notice' => 'As evidências são placeholders neutros e estão identificadas como imagens ilustrativas.',
            'report_revision' => self::REPORT_REVISION,
        ];
    }

    /**
     * @param  Collection<int, Defect>  $items
     */
    private function adjacentAssessmentUrl(
        Collection $items,
        int|false $position,
        int $offset,
        Inspection $inspection,
    ): ?string {
        if (! is_int($position)) {
            return null;
        }

        $defect = $items->get($position + $offset);

        if (! $defect instanceof Defect) {
            return null;
        }

        $assessment = $defect->assessments
            ->firstWhere('inspection_id', $inspection->getKey());

        return $assessment instanceof DefectAssessment
            ? route('defect-assessments.show', $assessment)
            : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function firstPendingUrl(array $items): ?string
    {
        $item = collect($items)
            ->first(fn (array $item): bool => $item['is_pending'] && $item['assessment_url'] !== null);

        return $item['assessment_url'] ?? null;
    }

    /**
     * Excludes defects introduced only in a later inspection while retaining
     * existing defects that still need an assessment in an open inspection.
     *
     * @return Collection<int, Defect>
     */
    private function defectsForInspection(Inspection $inspection): Collection
    {
        $inspection->loadMissing([
            'equipment.defects.firstInspection',
            'equipment.defects.assessments.inspection',
            'equipment.defects.assessments.creator',
        ]);

        $inspectionKey = $this->inspectionOrderKey($inspection);

        return $inspection->equipment->defects
            ->filter(function (Defect $defect) use ($inspection, $inspectionKey): bool {
                if ($defect->assessments->contains('inspection_id', $inspection->getKey())) {
                    return true;
                }

                if ($defect->firstInspection === null) {
                    return false;
                }

                if ($defect->status !== DefectStatus::Active) {
                    return false;
                }

                return $this->inspectionOrderKey($defect->firstInspection) <= $inspectionKey;
            })
            ->sortBy('sequence_number')
            ->values();
    }

    /**
     * @return array{int, int}
     */
    private function inspectionOrderKey(Inspection $inspection): array
    {
        return [
            $inspection->inspected_on?->getTimestamp()
                ?? $inspection->scheduled_for?->getTimestamp()
                ?? $inspection->created_at?->getTimestamp()
                ?? 0,
            (int) $inspection->getKey(),
        ];
    }

    private function criticalityRank(string $code): int
    {
        return match ($code) {
            'CV-1' => 1,
            'CV-2' => 2,
            'CV-3' => 3,
            'CV-4' => 4,
            'CV-5' => 5,
            default => 99,
        };
    }
}
