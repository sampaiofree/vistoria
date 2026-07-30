<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\InspectionTransitionGuard;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InspectionTransitionGuard $guard,
    ) {}

    /**
     * @param  array<int, InspectionStatus>  $fromStatuses
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        User $actor,
        Inspection $inspection,
        array $fromStatuses,
        InspectionStatus $toStatus,
        array $attributes = [],
        ?string $reason = null,
        ?array $metadata = null,
    ): Inspection {
        return DB::transaction(function () use (
            $actor,
            $inspection,
            $fromStatuses,
            $toStatus,
            $attributes,
            $reason,
            $metadata,
        ): Inspection {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->with(['responsibles', 'equipment'])
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            $fromStatus = $inspection->status;

            if (! in_array($fromStatus, $fromStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'A inspeção não está no estado esperado para esta transição.',
                ]);
            }

            if (! $this->guard->allows($fromStatus, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'A transição solicitada não é permitida.',
                ]);
            }

            $inspection->update(array_merge($attributes, [
                'status' => $toStatus,
                'updated_by' => $actor->getKey(),
            ]));

            InspectionStatusHistory::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $inspection->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $actor->getKey(),
                'reason' => $reason ?? sprintf(
                    'Transição de %s para %s.',
                    $fromStatus->label(),
                    $toStatus->label(),
                ),
                'metadata' => $metadata === null ? null : Arr::wrap($metadata),
                'created_at' => now(),
            ]);

            return $inspection->refresh();
        });
    }
}
