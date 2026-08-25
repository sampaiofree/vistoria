<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class UpdateGlobalOrganization
{
    /** @param array{name:string, legal_name:?string, document:?string} $data */
    public function handle(Organization $organization, array $data): Organization
    {
        return DB::transaction(function () use ($organization, $data): Organization {
            $organization->update($data);

            return $organization->refresh();
        });
    }
}
