<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\InspectionResponsible;
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

    protected function tenantEquipment(TenantContext $tenant, Equipment $equipment): Equipment
    {
        return Equipment::query()
            ->forOrganization($tenant->id())
            ->whereKey($equipment->getKey())
            ->firstOrFail();
    }

    protected function tenantEquipmentDocument(
        TenantContext $tenant,
        EquipmentDocument $document,
    ): EquipmentDocument {
        return EquipmentDocument::query()
            ->forOrganization($tenant->id())
            ->whereKey($document->getKey())
            ->firstOrFail();
    }

    protected function tenantInspection(
        TenantContext $tenant,
        Inspection $inspection,
    ): Inspection {
        return Inspection::query()
            ->forOrganization($tenant->id())
            ->whereKey($inspection->getKey())
            ->firstOrFail();
    }

    protected function tenantInspectionResponsible(
        TenantContext $tenant,
        Inspection $inspection,
        InspectionResponsible $responsible,
    ): InspectionResponsible {
        return InspectionResponsible::query()
            ->forOrganization($tenant->id())
            ->whereKey($responsible->getKey())
            ->where('inspection_id', $inspection->getKey())
            ->firstOrFail();
    }

    protected function tenantInspectionReferenceDocument(
        TenantContext $tenant,
        Inspection $inspection,
        InspectionReferenceDocument $referenceDocument,
    ): InspectionReferenceDocument {
        return InspectionReferenceDocument::query()
            ->forOrganization($tenant->id())
            ->whereKey($referenceDocument->getKey())
            ->where('inspection_id', $inspection->getKey())
            ->firstOrFail();
    }
}
