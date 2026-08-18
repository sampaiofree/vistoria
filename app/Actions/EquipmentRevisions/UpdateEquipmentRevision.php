<?php

declare(strict_types=1);

namespace App\Actions\EquipmentRevisions;

use App\Models\EquipmentRevision;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateEquipmentRevision
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, EquipmentRevision $revision, array $data): EquipmentRevision
    {
        return DB::transaction(function () use ($actor, $revision, $data): EquipmentRevision {
            $revision = EquipmentRevision::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($revision->getKey());

            $ids = collect(['preparer', 'reviewer', 'approver', 'releaser'])
                ->mapWithKeys(fn (string $role): array => [$role => (int) ($data[$role.'_id'] ?? 0)]);
            $users = User::query()
                ->where('organization_id', $this->tenant->id())
                ->whereIn('id', $ids->values())
                ->get()
                ->keyBy('id');

            if ($users->count() !== $ids->unique()->count()) {
                throw ValidationException::withMessages([
                    'users' => 'Todos os responsáveis precisam pertencer à organização.',
                ]);
            }

            $values = [
                'emission_type' => $data['emission_type'],
                'revision_date' => $data['revision_date'],
                'updated_by' => $actor->getKey(),
            ];

            foreach (['preparer', 'reviewer', 'approver', 'releaser'] as $role) {
                $id = $ids[$role];
                $currentId = (int) $revision->{$role.'_id'};
                $user = $users->get($id);

                if ($id !== $currentId && ! $user->isActive()) {
                    throw ValidationException::withMessages([
                        $role.'_id' => 'Selecione um usuário ativo para alterar este responsável.',
                    ]);
                }

                $values[$role.'_id'] = $id;
                if ($id !== $currentId) {
                    $values[$role.'_name'] = $user->name;
                }
            }

            $revision->update($values);

            return $revision->refresh();
        });
    }
}
