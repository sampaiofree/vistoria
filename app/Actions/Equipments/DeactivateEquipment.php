<?php

namespace App\Actions\Equipments;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeactivateEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, Equipment $equipment): Equipment
    {
        return DB::transaction(function () use ($actor, $equipment): Equipment {
            $equipment = Equipment::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($equipment->getKey());

            if ($equipment->status === EquipmentStatus::Decommissioned) {
                throw ValidationException::withMessages([
                    'status' => 'Nao e possivel inativar um equipamento descomissionado.',
                ]);
            }

            $equipment->update([
                'status' => EquipmentStatus::Inactive,
                'updated_by' => $actor->getKey(),
            ]);

            return $equipment->refresh();
        });
    }
}
