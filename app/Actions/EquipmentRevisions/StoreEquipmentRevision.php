<?php

declare(strict_types=1);

namespace App\Actions\EquipmentRevisions;

use App\Enums\EquipmentRevisionEmissionType;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreEquipmentRevision
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, Equipment $equipment, array $data): EquipmentRevision
    {
        $equipment = Equipment::query()
            ->forOrganization($this->tenant->id())
            ->whereKey($equipment->getKey())
            ->firstOrFail();

        $users = $this->resolveActiveUsers($data);

        return DB::transaction(function () use ($actor, $equipment, $data, $users): EquipmentRevision {
            return EquipmentRevision::query()->create([
                'organization_id' => $equipment->organization_id,
                'equipment_id' => $equipment->getKey(),
                'emission_type' => EquipmentRevisionEmissionType::from($data['emission_type']),
                'revision_date' => $data['revision_date'],
                'preparer_id' => $users['preparer']->getKey(),
                'reviewer_id' => $users['reviewer']->getKey(),
                'approver_id' => $users['approver']->getKey(),
                'releaser_id' => $users['releaser']->getKey(),
                'preparer_name' => $users['preparer']->name,
                'reviewer_name' => $users['reviewer']->name,
                'approver_name' => $users['approver']->name,
                'releaser_name' => $users['releaser']->name,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        });
    }

    /** @return array{preparer:User, reviewer:User, approver:User, releaser:User} */
    private function resolveActiveUsers(array $data): array
    {
        $ids = collect(['preparer', 'reviewer', 'approver', 'releaser'])
            ->mapWithKeys(fn (string $role): array => [$role => (int) ($data[$role.'_id'] ?? 0)]);

        $users = User::query()
            ->where('organization_id', $this->tenant->id())
            ->where('status', 'active')
            ->whereIn('id', $ids->values())
            ->get()
            ->keyBy('id');

        if ($users->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages([
                'users' => 'Todos os responsáveis precisam ser usuários ativos da organização.',
            ]);
        }

        return $ids->mapWithKeys(fn (int $id, string $role): array => [$role => $users->get($id)])->all();
    }
}
