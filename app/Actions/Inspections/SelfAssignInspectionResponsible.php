<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\InspectionSelfAssignment;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SelfAssignInspectionResponsible
{
    public function __construct(private readonly TenantContext $tenant, private readonly InspectionSelfAssignment $availability) {}

    // Null means the role was taken; the caller must return to the available queue.
    public function handle(Inspection $inspection, User $actor): ?InspectionResponsible
    {
        return DB::transaction(function () use ($inspection, $actor): ?InspectionResponsible {
            $inspection = Inspection::query()->forOrganization($this->tenant->id())
                ->lockForUpdate()->findOrFail($inspection->id);
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('selfAssign', $inspection);
            $role = $this->availability->roleFor($actor);
            $existing = $inspection->responsibles()->where('responsibility', $role->value)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing->user_id === $actor->id ? $existing : null;
            }

            $assignment = InspectionResponsible::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'user_id' => $actor->id,
                'responsibility' => $role,
                'is_primary' => true,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);
            InspectionStatusHistory::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'from_status' => $inspection->status,
                'to_status' => $inspection->status,
                'changed_by' => $actor->id,
                'reason' => $actor->name.' assumiu como '.$role->label().'.',
                'metadata' => ['event' => 'responsibility_self_assigned', 'responsibility' => $role->value, 'user_id' => $actor->id, 'user_name' => $actor->name],
                'created_at' => now(),
            ]);

            return $assignment;
        });
    }
}
