<?php

namespace App\Actions\Equipments;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecommissionEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, Equipment $equipment, ?string $reason): Equipment
    {
        return DB::transaction(function () use ($actor, $equipment, $reason): Equipment {
            $equipment = Equipment::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($equipment->getKey());

            if ($reason === null || trim($reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Informe o motivo do descomissionamento.',
                ]);
            }

            $equipment->update([
                'status' => EquipmentStatus::Decommissioned,
                'decommissioned_at' => now(),
                'decommissioned_by' => $actor->getKey(),
                'decommission_reason' => trim($reason),
                'updated_by' => $actor->getKey(),
            ]);

            return $equipment->refresh();
        });
    }
}
