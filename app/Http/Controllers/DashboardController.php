<?php

namespace App\Http\Controllers;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\InspectionReadModelPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DashboardController extends Controller
{
    public function index(
        Request $request,
        InspectionReadModelPresenter $demoPresenter,
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
                    'organizations_index' => route('admin.organizations.index'),
                    'inspections_index' => null,
                    'inspections_create' => null,
                    'equipments_index' => null,
                    'clients_index' => null,
                    'priority' => [
                        'overdue' => null,
                        'awaiting_review' => null,
                        'in_correction' => null,
                        'awaiting_release' => null,
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
                'organizations_index' => null,
                'inspections_index' => route('inspections.index', $personalFilters),
                'inspections_create' => $request->user()->can('create', Inspection::class) ? route('inspections.create') : null,
                'equipments_index' => route('equipments.index'),
                'clients_index' => route('clients.index'),
                'priority' => [
                    'overdue' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::Planned->value,
                        'scheduled_to' => $today->subDay()->toDateString(),
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Preparer->value,
                    ])),
                    'awaiting_review' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::AwaitingReview->value,
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Approver->value,
                    ])),
                    'in_correction' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::InCorrection->value,
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Reviewer->value,
                    ])),
                    'awaiting_release' => route('inspections.index', array_merge([
                        'status' => InspectionStatus::AwaitingRelease->value,
                    ], $personalFilters, $companySummary ? [] : [
                        'responsibility' => InspectionResponsibility::Releaser->value,
                    ])),
                ],
                'workflow' => [
                    'planned' => route('inspections.index', array_merge(['status' => InspectionStatus::Planned->value], $personalFilters)),
                    'in_progress' => route('inspections.index', array_merge(['status' => InspectionStatus::InProgress->value], $personalFilters)),
                    'awaiting_review' => route('inspections.index', array_merge(['status' => InspectionStatus::AwaitingReview->value], $personalFilters)),
                    'in_correction' => route('inspections.index', array_merge(['status' => InspectionStatus::InCorrection->value], $personalFilters)),
                    'in_review' => route('inspections.index', array_merge(['status' => InspectionStatus::InReview->value], $personalFilters)),
                    'awaiting_release' => route('inspections.index', array_merge(['status' => InspectionStatus::AwaitingRelease->value], $personalFilters)),
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
     *     equipment:array{name:string,tag:string,show_url:string},
     *     progress:array{completed:int,total:int,percentage:int},
     *     show_url:string
     * }
     */
    private function featuredInspection(
        int $organizationId,
        int $userId,
        bool $companySummary,
        InspectionReadModelPresenter $demoPresenter,
    ): ?array {
        $query = Inspection::query()
            ->forOrganization($organizationId)
            ->with([
                'equipment.client:id,public_id,name',
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
                    ->whereDate('planned_end_on', '<', $today->toDateString())
                    ->count(),
                'awaiting_review' => Inspection::query()
                    ->forOrganization($organizationId)
                    ->where('status', InspectionStatus::AwaitingReview->value)
                    ->count(),
                'in_correction' => Inspection::query()
                    ->forOrganization($organizationId)
                    ->where('status', InspectionStatus::InCorrection->value)
                    ->count(),
                'awaiting_release' => Inspection::query()
                    ->forOrganization($organizationId)
                    ->where('status', InspectionStatus::AwaitingRelease->value)
                    ->count(),
            ];
        }

        return [
            'overdue' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::Planned->value)
                ->whereDate('planned_end_on', '<', $today->toDateString())
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Preparer->value))
                ->count(),
            'awaiting_review' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::AwaitingReview->value)
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Approver->value))
                ->count(),
            'in_correction' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::InCorrection->value)
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Reviewer->value))
                ->count(),
            'awaiting_release' => Inspection::query()
                ->forOrganization($organizationId)
                ->where('status', InspectionStatus::AwaitingRelease->value)
                ->whereHas('responsibles', fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('responsibility', InspectionResponsibility::Releaser->value))
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
                        WHEN status = ? AND planned_end_on < ? THEN 0
                        WHEN status = ? THEN 1
                        WHEN status = ? THEN 2
                        WHEN status = ? THEN 3
                        WHEN status = ? THEN 4
                        WHEN status = ? THEN 5
                        WHEN status = ? THEN 6
                        ELSE 7
                    END
                SQL,
                [
                    InspectionStatus::Planned->value,
                    $today->toDateString(),
                    InspectionStatus::InCorrection->value,
                    InspectionStatus::AwaitingReview->value,
                    InspectionStatus::InReview->value,
                    InspectionStatus::AwaitingRelease->value,
                    InspectionStatus::InProgress->value,
                    InspectionStatus::Planned->value,
                ],
            )
            ->orderByRaw('CASE WHEN planned_start_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('planned_start_on')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->values();

        return $inspections->map(function (Inspection $inspection) use ($today, $timezone, $user, $userId): array {
            $responsibilities = $inspection->responsibles
                ->where('user_id', $userId)
                ->map(fn ($responsible): array => [
                    'value' => $responsible->responsibility->value,
                    'label' => $this->dashboardResponsibilityLabel($responsible->responsibility),
                ])
                ->unique('value')
                ->values()
                ->all();

            $plannedStart = $inspection->planned_start_on?->toDateString();
            $plannedEnd = $inspection->planned_end_on?->toDateString();

            return [
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'inspection_type' => $inspection->inspection_type->value,
                'inspection_type_label' => $inspection->inspection_type->label(),
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'created_at' => $inspection->created_at?->setTimezone($timezone)->format('d/m/Y'),
                'equipment' => [
                    'name' => $inspection->equipment->name,
                    'tag' => $inspection->equipment->tag,
                ],
                'user_responsibilities' => $responsibilities,
                'schedule' => [
                    'date' => $this->plannedWindowLabel($plannedStart, $plannedEnd),
                    'label' => $this->scheduleLabel(
                        $plannedEnd,
                        $today,
                        $inspection->status,
                    ),
                    'is_overdue' => $inspection->status === InspectionStatus::Planned
                        && $inspection->planned_end_on !== null
                        && $inspection->planned_end_on->toDateString() < $today->toDateString(),
                ],
                'next_action' => [
                    'label' => $this->nextActionLabel($inspection, $user),
                    'href' => route('inspections.show', $inspection),
                ],
            ];
        })->all();
    }

    private function dashboardResponsibilityLabel(InspectionResponsibility $responsibility): string
    {
        return match ($responsibility) {
            InspectionResponsibility::Preparer => 'Planejador',
            InspectionResponsibility::Reviewer => 'Inspetor',
            InspectionResponsibility::Approver => 'Revisor',
            InspectionResponsibility::Releaser => 'Liberador',
        };
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
            InspectionStatus::AwaitingReview->value => 'Aguardando revisão',
            InspectionStatus::InReview->value => 'Em revisão',
            InspectionStatus::InCorrection->value => 'Correção',
            InspectionStatus::AwaitingRelease->value => 'Aguardando liberação',
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
                ? 'Concluir inspeção'
                : 'Acompanhar inspeção',
            InspectionStatus::AwaitingReview => (
                $user->can('startReview', $inspection)
            ) ? 'Iniciar revisão' : 'Acompanhar revisão',
            InspectionStatus::InReview => (
                $user->can('approve', $inspection)
                || $user->can('returnForCorrection', $inspection)
            ) ? 'Revisar inspeção' : 'Acompanhar revisão',
            InspectionStatus::InCorrection => $user->can('submitForReview', $inspection)
                ? 'Corrigir pendências'
                : 'Acompanhar correção',
            InspectionStatus::AwaitingRelease => $user->can('release', $inspection)
                ? 'Liberar inspeção'
                : 'Acompanhar liberação',
            InspectionStatus::Released => 'Ver inspeção',
            InspectionStatus::Canceled => 'Ver detalhes',
        };
    }

    private function scheduleLabel(
        ?string $plannedEnd,
        CarbonImmutable $today,
        InspectionStatus $status,
    ): string {
        if ($plannedEnd === null) {
            return 'Sem prazo';
        }

        if ($status !== InspectionStatus::Planned) {
            return 'Data programada';
        }

        $date = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $plannedEnd,
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

    private function plannedWindowLabel(?string $plannedStart, ?string $plannedEnd): string
    {
        if ($plannedStart === null || $plannedEnd === null) {
            return '—';
        }

        $start = CarbonImmutable::parse($plannedStart)->format('d/m/Y');
        $end = CarbonImmutable::parse($plannedEnd)->format('d/m/Y');

        return $start === $end ? $start : sprintf('%s a %s', $start, $end);
    }

    private function activityDescription(InspectionStatusHistory $history): string
    {
        $inspectionNumber = $history->inspection?->number ?? 'inspeção';
        $actor = $history->actor?->name ?? 'Sistema';

        return match ($history->to_status) {
            InspectionStatus::Planned => sprintf('%s planejou a inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::InProgress => sprintf('%s iniciou a inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::AwaitingReview => sprintf('%s enviou a inspeção %s para revisão.', $actor, $inspectionNumber),
            InspectionStatus::InReview => sprintf('%s iniciou a revisão da inspeção %s.', $actor, $inspectionNumber),
            InspectionStatus::InCorrection => sprintf('%s devolveu a inspeção %s para correção.', $actor, $inspectionNumber),
            InspectionStatus::AwaitingRelease => sprintf('%s enviou a inspeção %s para liberação.', $actor, $inspectionNumber),
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
