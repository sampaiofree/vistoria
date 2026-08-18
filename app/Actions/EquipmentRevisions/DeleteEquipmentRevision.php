<?php

declare(strict_types=1);

namespace App\Actions\EquipmentRevisions;

use App\Models\EquipmentRevision;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class DeleteEquipmentRevision
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, EquipmentRevision $revision): void
    {
        DB::transaction(function () use ($revision): void {
            EquipmentRevision::query()
                ->forOrganization($this->tenant->id())
                ->whereKey($revision->getKey())
                ->firstOrFail()
                ->delete();
        });
    }
}
