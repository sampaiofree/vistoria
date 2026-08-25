<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class SetGlobalOrganizationStatus
{
    public function handle(Organization $organization, OrganizationStatus $status): Organization
    {
        return DB::transaction(function () use ($organization, $status): Organization {
            $organization->update([
                'status' => $status,
                'suspended_at' => $status === OrganizationStatus::Suspended ? now() : null,
                'suspension_reason' => $status === OrganizationStatus::Suspended
                    ? 'Suspensa pelo Administrador Master.'
                    : null,
            ]);

            return $organization->refresh();
        });
    }
}
