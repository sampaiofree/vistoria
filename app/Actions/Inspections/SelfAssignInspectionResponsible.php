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
use Illuminate\Validation\ValidationException;

final class SelfAssignInspectionResponsible
{
    public function __construct(private readonly TenantContext $tenant, private readonly InspectionSelfAssignment $availability) {}

    /** @param list<string>|null $selected @return list<InspectionResponsible>|null */
    public function handle(Inspection $inspection, User $actor, ?array $selected = null): ?array
    {
        return DB::transaction(function () use ($inspection, $actor, $selected): ?array {
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $inspection = Inspection::query()->forOrganization($this->tenant->id())
                ->lockForUpdate()->findOrFail($inspection->id);
            Gate::forUser($actor)->authorize('selfAssign', $inspection);
            $eligible = $this->availability->rolesFor($actor);
            if ($selected === null) {
                if (count($eligible) !== 1) {
                    throw ValidationException::withMessages(['responsibilities' => 'Selecione uma ou duas funções para assumir.']);
                }
                $selected = [$eligible[0]->value];
            }
            foreach ($selected as $value) {
                if (! in_array($value, array_map(fn ($role) => $role->value, $eligible), true)) {
                    throw ValidationException::withMessages(['responsibilities' => 'Você não possui uma das funções selecionadas.']);
                }
            }

            $existing = $inspection->responsibles()->whereIn('responsibility', $selected)
                ->lockForUpdate()->get()->keyBy(fn (InspectionResponsible $item) => $item->responsibility->value);

            foreach ($existing as $assignment) {
                if ($assignment->user_id !== $actor->id) {
                    return null;
                }
            }

            $assignments = [];
            foreach ($selected as $value) {
                if ($existing->has($value)) {
                    $assignments[] = $existing->get($value);
                    continue;
                }
                $role = \App\Enums\InspectionResponsibility::from($value);
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
                $assignments[] = $assignment;
            }

            return $assignments;
        });
    }
}
