<?php

declare(strict_types=1);

namespace App\Actions\EquipmentRevisions;

use App\Models\EquipmentRevision;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

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

            $values = [
                'emission_type' => $data['emission_type'],
                'revision_date' => $data['revision_date'],
                'preparer_name' => $data['preparer_name'],
                'reviewer_name' => $data['reviewer_name'],
                'approver_name' => $data['approver_name'],
                'releaser_name' => $data['releaser_name'],
                'updated_by' => $actor->getKey(),
            ];

            $revision->update($values);

            return $revision->refresh();
        });
    }
}
