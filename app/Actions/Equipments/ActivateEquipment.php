<?php

namespace App\Actions\Equipments;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActivateEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, Equipment $equipment): Equipment
    {
        return DB::transaction(function () use ($actor, $equipment): Equipment {
            $equipment = Equipment::query()
                ->forOrganization($this->tenant->id())
                ->with(['client', 'unit', 'area', 'subarea'])
                ->lockForUpdate()
                ->findOrFail($equipment->getKey());

            if ($equipment->status === EquipmentStatus::Decommissioned) {
                throw ValidationException::withMessages([
                    'status' => 'Nao e possivel reativar um equipamento descomissionado.',
                ]);
            }

            if (! $equipment->hasOperationalStructure()) {
                throw ValidationException::withMessages([
                    'status' => 'Nao e possivel ativar o equipamento com a estrutura operacional inativa.',
                ]);
            }

            $equipment->update([
                'status' => EquipmentStatus::Active,
                'updated_by' => $actor->getKey(),
            ]);

            return $equipment->refresh();
        });
    }
}
