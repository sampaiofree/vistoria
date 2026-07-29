<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\InspectionTransitionGuard;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @internal Called only by the named inspection lifecycle actions. */
final class TransitionInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InspectionTransitionGuard $guard,
    ) {}

    /**
     * @param  list<InspectionStatus>  $expectedStatuses
     * @param  Closure(Inspection): void  $precondition
     */
    public function handle(
        Inspection $inspection,
        User $actor,
        array $expectedStatuses,
        InspectionStatus $targetStatus,
        ?string $timestampColumn,
        Closure $precondition,
        ?string $reason = null,
        array $extraAttributes = [],
    ): Inspection {
        return DB::transaction(function () use ($inspection, $actor, $expectedStatuses, $targetStatus, $timestampColumn, $precondition, $reason, $extraAttributes): Inspection {
            $locked = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            if (! $actor->isActive() || (int) $actor->organization_id !== $this->tenant->id()) {
                throw ValidationException::withMessages([
                    'actor' => 'O usuário deve estar ativo e pertencer à organização da inspeção.',
                ]);
            }

            $from = $locked->status;

            if (! in_array($from, $expectedStatuses, true) || ! $this->guard->allows($from, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => "A transição de {$from->value} para {$targetStatus->value} não é permitida.",
                ]);
            }

            $precondition($locked);

            $now = now();
            $attributes = array_merge($extraAttributes, [
                'status' => $targetStatus,
                'updated_by' => $actor->getKey(),
            ]);

            if ($timestampColumn !== null && $locked->{$timestampColumn} === null) {
                $attributes[$timestampColumn] = $now;
            }

            $locked->update($attributes);

            InspectionStatusHistory::query()->create([
                'organization_id' => $locked->organization_id,
                'inspection_id' => $locked->getKey(),
                'from_status' => $from,
                'to_status' => $targetStatus,
                'changed_by' => $actor->getKey(),
                'reason' => $reason,
                'created_at' => $now,
            ]);

            return $locked->refresh();
        });
    }
}
