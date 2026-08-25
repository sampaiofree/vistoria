<?php

declare(strict_types=1);

namespace App\Actions\EquipmentRevisions;

use App\Enums\EquipmentRevisionEmissionType;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class StoreEquipmentRevision
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, Equipment $equipment, array $data): EquipmentRevision
    {
        $equipment = Equipment::query()
            ->forOrganization($this->tenant->id())
            ->whereKey($equipment->getKey())
            ->firstOrFail();

        return DB::transaction(function () use ($actor, $equipment, $data): EquipmentRevision {
            return EquipmentRevision::query()->create([
                'organization_id' => $equipment->organization_id,
                'equipment_id' => $equipment->getKey(),
                'emission_type' => EquipmentRevisionEmissionType::from($data['emission_type']),
                'revision_date' => $data['revision_date'],
                'preparer_name' => $data['preparer_name'],
                'reviewer_name' => $data['reviewer_name'],
                'approver_name' => $data['approver_name'],
                'releaser_name' => $data['releaser_name'],
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        });
    }
}
