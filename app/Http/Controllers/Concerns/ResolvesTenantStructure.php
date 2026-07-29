<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Subarea;
use App\Services\Tenancy\TenantContext;

trait ResolvesTenantStructure
{
    protected function tenantClient(TenantContext $tenant, Client $client): Client
    {
        return Client::query()
            ->forOrganization($tenant->id())
            ->whereKey($client->getKey())
            ->firstOrFail();
    }

    protected function tenantUnit(TenantContext $tenant, ClientUnit $unit): ClientUnit
    {
        return ClientUnit::query()
            ->forOrganization($tenant->id())
            ->whereKey($unit->getKey())
            ->firstOrFail();
    }

    protected function tenantArea(TenantContext $tenant, Area $area): Area
    {
        return Area::query()
            ->forOrganization($tenant->id())
            ->whereKey($area->getKey())
            ->firstOrFail();
    }

    protected function tenantSubarea(TenantContext $tenant, Subarea $subarea): Subarea
    {
        return Subarea::query()
            ->forOrganization($tenant->id())
            ->whereKey($subarea->getKey())
            ->firstOrFail();
    }
}
