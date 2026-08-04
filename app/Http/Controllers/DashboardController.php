<?php

namespace App\Http\Controllers;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Demo\ViewFirstDemoPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DashboardController extends Controller
{
    public function index(
        Request $request,
        ViewFirstDemoPresenter $demoPresenter,
    ): InertiaResponse {
        $user = $request->user();
        $user->loadMissing('organization');

        $organization = $user->organization;

        if ($user->isSuperAdmin() || $organization === null) {
            return Inertia::render('Dashboard/Index', [
                'mode' => 'global',
                'organization' => null,
                'can' => [
                    'create_inspection' => false,
                    'view_company_summary' => false,
                ],
                'links' => [
                    'dashboard' => route('dashboard'),
                    'inspections_index' => null,
                    'inspections_create' => null,
                    'equipments_index' => null,
                    'clients_index' => null,
                    'priority' => [
                        'overdue' => null,
                        'awaiting_review' => null,
                        'in_correction' => null,
                        'awaiting_approval' => null,
                    ],
                    'workflow' => [],
                ],
                'priority_counts' => null,
                'my_inspections' => [],
                'workflow_summary' => [],
                'recent_activities' => [],
                'featured_inspection' => null,
            ]);
        }

        $organizationId = (int) $organization->getKey();
        $timezone = $organization->timezone ?: config('app.timezone');
        $userId = (int) $user->getKey();
        $today = $this->today($timezone);
        $companySummary = $user->isCompanyAdmin();
        $personalFilters = $companySummary ? [] : ['responsible' => $userId];

        return Inertia::render('Dashboard/Index', [
            'mode' => 'operational',
            'organization' => [
                'public_id' => $organization->public_id,
                'name' => $organization->name,
                'timezone' => $timezone,
            ],
            'can' => [
                'create_inspection' => $request->user()->can('create', Inspection::class),
                'view_company_summary' => $companySummary,
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'inspections_index' => route('inspections.index', $personalFilters),
                'inspections_create' => $request->user()->can('create', Inspection::class) ? route('inspections.create') : null,
                'equipments_index' => route('equipments.index'),
                'clients_index' => route('clients.index'),
                'priority' => [
                    'overdue' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::Planned->value,
                        'scheduled_to' => $today->subDay()->toDateString(),
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Inspector->value,
                    ])),
                    'awaiting_review' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::AwaitingReview->value,
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Reviewer->value,
                    ])),
                    'in_correction' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::InCorrection->value,
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Preparer->value,
                    ])),
                    'awaiting_approval' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::AwaitingApproval->value,
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Approver->value,
                    ])),
                ],
                'workflow' => [
                    'planned' => route('inspections.index', array_merge(['status' => InspectionStatus::Planned->value], $personalFilters)),
                    'in_progress' => route('inspections.index', array_merge(['status' => InspectionStatus::InProgress->value], $personalFilters)),
                    'awaiting_review' => route('inspections.index', array_merge(['status' => InspectionStatus::AwaitingReview->value], $personalFilters)),
                    'in_correction' => route('inspections.index', array_merge(['status' => InspectionStatus::InCorrection->value], $personalFilters)),
                    'awaiting_approval' => route('inspections.index', array_merge(['status' => InspectionStatus::AwaitingApproval->value], $personalFilters)),
                    'approved' => route('inspections.index', array_merge(['status' => InspectionStatus::Approved->value], $personalFilters)),
                    'report_generated' => route('inspections.index', array_merge(['status' => InspectionStatus::ReportGenerated->value], $personalFilters)),
                    'released' => route('inspections.index', array_merge(['status' => InspectionStatus::Released->value], $personalFilters)),
                ],
            ],
            'priority_counts' => Inertia::defer(
                fn (): array => $this->priorityCounts($organizationId, $userId, $today, $companySummary),
                'dashboard-priority-counts',
                true,
            ),
            'my_inspections' => Inertia::defer(
                fn (): array => $this->myInspections($organizationId, $user, $timezone),
                'dashboard-my-inspections',
                true,
            ),
            'workflow_summary' => Inertia::defer(
                fn (): array => $this->workflowSummary($organizationId, $userId, $companySummary),
                'dashboard-workflow-summary',
                true,
            ),
            'recent_activities' => Inertia::defer(
                fn (): array => $this->recentActivities($organizationId, $userId, $timezone, $companySummary),
                'dashboard-recent-activities',
                true,
            ),
            'featured_inspection' => $this->featuredInspection(
                $organizationId,
                $userId,
                $companySummary,
                $demoPresenter,
            ),
        ]);
    }

    /**
     * @return null|array{
     *     public_id:string,
     *     number:string,
     *     inspection_type:string,
     *     inspection_type_label:string,
     *     status:string,
     *     status_label:string,
     *     service_order:?string,
     *     client:array{name:string},
     *     unit:array{name:string},
     *     equipment:array{name:string,tag:string,show_url:string},
     *     progress:array{completed:int,total:int,percentage:int},
     *     show_url:string
     * }
     */
    private function featuredInspection(
        int $organizationId,
        int $userId,
        bool $companySummary,
        ViewFirstDemoPresenter $demoPresenter,
    ): ?array {
        $query = Inspection::query()
            ->forOrganization($organizationId)
            ->with([
                'equipment.client:id,public_id,name',
                'equipment.unit:id,public_id,name',
            ])
            ->where('status', InspectionStatus::InProgress->value);

        if (! $companySummary) {
            $query->whereHas('responsibles', fn ($responsibles) => $responsibles->where('user_id', $userId));
        }

        $inspection = $query
            ->orderByRaw('CASE WHEN previous_inspection_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->first();

        if ($inspection === null) {
            return null;
        }

        return [
            'public_id' => $inspection->public_id,
            'number' => $inspection->number ?? 'Inspeção em andamento',
            'inspection_type' => $inspection->inspection_type->value,
            'inspection_type_label' => $inspection->inspection_type->label(),
            'status' => $inspection->status->value,
            'status_label' => $inspection->status->label(),
            'service_order' => $inspection->service_order,
            'client' => [
                'name' => $inspection->equipment->client?->name ?? '—',
            ],
            'unit' => [
                'name' => $inspection->equipment->unit?->name ?? '—',
            ],
            'equipment' => [
                'name' => $inspection->equipment->name,
                'tag' => $inspection->equipment->tag,
                'show_url' => route('equipments.show', $inspection->equipment),
            ],
            'progress' => $demoPresenter->progress($inspection),
            'show_url' => route('inspections.show', $inspection),
        ];
    }

    private function priorityCounts(
        int $organizationId,
        int $userId,
        CarbonImmutable $today,
        bool $companySummary,
    ): array {
        if ($companySummary) {
            return [
                'overdue' => Inspection::query()
                    ->forOrganization($organizationId)
                    ->where('status', InspectionStatus::Planned->value)
                    ->whereDate('scheduled_for', '<', $today->toDateString())
                    ->count(),
                'awaiting_review' => Inspection::query()
                    ->forOrganization($organizationId)
                    ->where('status', InspectionStatus::AwaitingReview->value)
                    ->count(),
                'in_correction' => Inspection::query()
                    ->forOrganization($organizationId)
                    ->where('status', InspectionStatus::InCorrection->value)
                    ->count(),
                'awaiting_approval' => Inspection::query()
                    ->forOrganization($organizationId)
                    ->where('status', InspectionStatus::AwaitingApproval->value)
                    ->count(),
            ];
        }

        return [
            'overdue' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::Planned->value)
                ->whereDate('scheduled_for', '<', $today->toDateString())
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Inspector->value))
                ->count(),
            'awaiting_review' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::AwaitingReview->value)
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Reviewer->value))
                ->count(),
            'in_correction' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::InCorrection->value)
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Preparer->value))
                ->count(),
            'awaiting_approval' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::AwaitingApproval->value)
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Approver->value))
                ->count(),
        ];
    }

    /**
     * @return array<int, array{
     *     public_id:string,
     *     number:string,
     *     inspection_type:string,
     *     inspection_type_label:string,
     *     status:string,
     *     status_label:string,
     *     created_at:string,
     *     client: array{name:string},
     *     unit: array{name:string},
     *     equipment: array{name:string, tag:string},
     *     user_responsibilities: array<int, array{value:string, label:string}>,
     *     schedule: array{date:string, label:string, is_overdue:bool},
     *     next_action: array{label:string, href:string}
     * }>
     */
    private function myInspections(
        int $organizationId,
        User $user,
        string $timezone,
    ): array {
        $today = $this->today($timezone);
        $userId = (int) $user->getKey();

        $inspections = Inspection::query()
            ->forOrganization($organizationId)
            ->with([
                'equipment.client:id,public_id,name,status',
                'equipment.unit:id,public_id,name,client_id,status',
                'equipment.area:id,public_id,client_unit_id,status',
                'equipment.subarea:id,public_id,area_id,status',
                'responsibles.user:id,public_id,name',
            ])
            ->whereHas('responsibles', fn ($query) => $query->where('user_id', $userId))
            ->whereNotIn('status', [
                InspectionStatus::Released->value,
                InspectionStatus::Canceled->value,
            ])
            ->orderByRaw(
                <<<'SQL'
                    CASE
                        WHEN status = ? AND scheduled_for < ? THEN 0
                        WHEN status = ? THEN 1
                        WHEN status = ? THEN 2
                        WHEN status = ? THEN 3
                        WHEN status = ? THEN 4
                        WHEN status = ? THEN 5
                        WHEN status = ? THEN 6
                        WHEN status = ? THEN 7
                        ELSE 8
                    END
                SQL,
                [
                    InspectionStatus::Planned->value,
                    $today->toDateString(),
                    InspectionStatus::InCorrection->value,
                    InspectionStatus::AwaitingReview->value,
                    InspectionStatus::AwaitingApproval->value,
                    InspectionStatus::InProgress->value,
                    InspectionStatus::Planned->value,
                    InspectionStatus::Approved->value,
                    InspectionStatus::ReportGenerated->value,
                ],
            )
            ->orderByRaw('CASE WHEN scheduled_for IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_for')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->values();

        return $inspections->map(function (Inspection $inspection) use ($today, $timezone, $user, $userId): array {
            $responsibilities = $inspection->responsibles
                ->where('user_id', $userId)
                ->map(fn ($responsible): array => [
                    'value' => $responsible->responsibility->value,
                    'label' => $responsible->responsibility->label(),
                ])
                ->unique('value')
                ->values()
                ->all();

            $scheduledFor = $inspection->scheduled_for?->toDateString() ?? '';

            return [
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'inspection_type' => $inspection->inspection_type->value,
                'inspection_type_label' => $inspection->inspection_type->label(),
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'created_at' => $inspection->created_at?->setTimezone($timezone)->format('d/m/Y'),
                'client' => [
                    'name' => $inspection->equipment->client?->name ?? '—',
                ],
                'unit' => [
                    'name' => $inspection->equipment->unit?->name ?? '—',
                ],
                'equipment' => [
                    'name' => $inspection->equipment->name,
                    'tag' => $inspection->equipment->tag,
                ],
                'user_responsibilities' => $responsibilities,
                'schedule' => [
                    'date' => $scheduledFor !== '' ? CarbonImmutable::parse($scheduledFor)->format('d/m/Y') : '—',
                    'label' => $this->scheduleLabel(
                        $inspection->scheduled_for?->toDateString(),
                        $today,
                        $inspection->status,
                    ),
                    'is_overdue' => $inspection->status === InspectionStatus::Planned
                        && $inspection->scheduled_for !== null
                        && $inspection->scheduled_for->toDateString() < $today->toDateString(),
                ],
                'next_action' => [
                    'label' => $this->nextActionLabel($inspection, $user),
                    'href' => route('inspections.show', $inspection),
                ],
            ];
        })->all();
    }

    /**
     * @return array<int, array{key:string, label:string, count:int, href:string}>
     */
    private function workflowSummary(
        int $organizationId,
        int $userId,
        bool $companySummary,
    ): array {
        $inspectionQuery = Inspection::query()
            ->forOrganization($organizationId);

        if (! $companySummary) {
            $inspectionQuery->whereHas('responsibles', fn ($query) => $query->where('user_id', $userId));
        }

        $steps = [
            InspectionStatus::Planned->value => 'Planejadas',
            InspectionStatus::InProgress->value => 'Em inspeção',
            InspectionStatus::AwaitingReview->value => 'Revisão',
            InspectionStatus::InCorrection->value => 'Correção',
            InspectionStatus::AwaitingApproval->value => 'Aprovação',
            InspectionStatus::Approved->value => 'Aprovadas',
            InspectionStatus::ReportGenerated->value => 'Relatório gerado',
            InspectionStatus::Released->value => 'Liberadas',
        ];

        return collect($steps)->map(function (string $label, string $status) use ($companySummary, $inspectionQuery, $userId): array {
            $href = route('inspections.index', array_filter([
                'status' => $status,
                'responsible' => $companySummary ? null : $userId,
            ], fn ($value): bool => $value !== null));

            return [
                'key' => $status,
                'label' => $label,
                'count' => (clone $inspectionQuery)
                    ->where('status', $status)
                    ->count(),
                'href' => $href,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     description:string,
     *     time_label:string,
     *     status:string,
     *     inspection: array{number:string, href:string},
     *     actor:string
     * }>
     */
    private function recentActivities(
        int $organizationId,
        int $userId,
        string $timezone,
        bool $companySummary,
    ): array {
        $query = InspectionStatusHistory::query()
            ->forOrganization($organizationId)
            ->with([
                'actor:id,public_id,name',
                'inspection:id,public_id,number,organization_id',
            ])
            ->orderByDesc('created_at');

        if (! $companySummary) {
            $query->where(function ($builder) use ($userId): void {
                $builder->where('changed_by', $userId)
                    ->orWhereHas('inspection.responsibles', fn ($responsibles) => $responsibles->where('user_id', $userId));
            });
        }

        return $query
            ->limit(6)
            ->get()
            ->map(function (InspectionStatusHistory $history) use ($timezone): array {
                return [
                    'id' => $history->getKey(),
                    'description' => $this->activityDescription($history),
                    'time_label' => $this->activityTimeLabel($history->created_at, $timezone),
                    'status' => $history->to_status->value,
                    'inspection' => [
                        'number' => $history->inspection?->number ?? '—',
                        'href' => route('inspections.show', $history->inspection),
                    ],
                    'actor' => $history->actor?->name ?? 'Sistema',
                ];
            })
            ->all();
    }

    private function nextActionLabel(Inspection $inspection, User $user): string
    {
        return match ($inspection->status) {
            InspectionStatus::Planned => $user->can('start', $inspection)
                ? 'Iniciar inspeção'
                : 'Ver planejamento',
            InspectionStatus::InProgress => $user->can('submitForReview', $inspection)
                ? 'Preparar revisão'
                : 'Acompanhar inspeção',
            InspectionStatus::AwaitingReview => (
                $user->can('completeReview', $inspection)
                || $user->can('returnForCorrection', $inspection)
            ) ? 'Abrir para revisar' : 'Acompanhar revisão',
            InspectionStatus::InCorrection => $user->can('submitForReview', $inspection)
                ? 'Corrigir pendências'
                : 'Acompanhar correção',
            InspectionStatus::AwaitingApproval => (
                $user->can('approve', $inspection)
                || $user->can('returnForCorrection', $inspection)
            ) ? 'Abrir para aprovar' : 'Acompanhar aprovação',
            InspectionStatus::Approved => 'Visualizar aprovação',
            InspectionStatus::ReportGenerated => $user->can('release', $inspection)
                ? 'Abrir para liberar'
                : 'Visualizar relatório',
            InspectionStatus::Released => 'Ver inspeção',
            InspectionStatus::Canceled => 'Ver detalhes',
        };
    }

    private function scheduleLabel(
        ?string $scheduledFor,
        CarbonImmutable $today,
        InspectionStatus $status,
    ): string {
        if ($scheduledFor === null) {
            return 'Sem prazo';
        }

        if ($status !== InspectionStatus::Planned) {
            return 'Data programada';
        }

        $date = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $scheduledFor,
            $today->getTimezone(),
        )->startOfDay();

        if ($date->isSameDay($today)) {
            return 'Vence hoje';
        }

        $days = (int) $date->diffInDays($today);

        if ($date->lt($today)) {
            return sprintf('%d dia%s atrasada', $days, $days === 1 ? '' : 's');
        }

        return sprintf('Faltam %d dia%s', $days, $days === 1 ? '' : 's');
    }

    private function activityDescription(InspectionStatusHistory $history): string
    {
        $inspectionNumber = $history->inspection?->number ?? 'inspeção';
        $actor = $history->actor?->name ?? 'Sistema';

        return match ($history->to_status) {
            InspectionStatus::Planned => sprintf('%s planejou a inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::InProgress => sprintf('%s iniciou a inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::AwaitingReview => sprintf('%s enviou a inspeção %s para revisão.', $actor, $inspectionNumber),
            InspectionStatus::InCorrection => sprintf('%s devolveu a inspeção %s para correção.', $actor, $inspectionNumber),
            InspectionStatus::AwaitingApproval => sprintf('%s enviou a inspeção %s para aprovação.', $actor, $inspectionNumber),
            InspectionStatus::Approved => sprintf('%s aprovou a inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::ReportGenerated => sprintf('%s gerou o relatório da inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::Released => sprintf('%s liberou a inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::Canceled => sprintf('%s cancelou a inspeção %s.', $actor, $inspectionNumber),
        };
    }

    private function activityTimeLabel(?\DateTimeInterface $dateTime, string $timezone): string
    {
        if ($dateTime === null) {
            return 'Agora';
        }

        $moment = CarbonImmutable::instance($dateTime)->setTimezone($timezone);
        $now = CarbonImmutable::now($timezone);
        $minutes = $moment->diffInMinutes($now);

        if ($minutes < 60) {
            return sprintf('Há %d min', max(1, $minutes));
        }

        $hours = $moment->diffInHours($now);

        if ($hours < 24) {
            return sprintf('Há %d h', max(1, $hours));
        }

        if ($moment->isYesterday()) {
            return sprintf('Ontem, às %s', $moment->format('H:i'));
        }

        return sprintf('%s às %s', $moment->format('d/m/Y'), $moment->format('H:i'));
    }

    private function today(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::now($timezone)->startOfDay();
    }
}
