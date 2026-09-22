<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
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

    protected function tenantEquipment(TenantContext $tenant, Equipment $equipment): Equipment
    {
        return Equipment::query()
            ->forOrganization($tenant->id())
            ->whereKey($equipment->getKey())
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

    protected function tenantDefect(
        TenantContext $tenant,
        Defect $defect,
    ): Defect {
        return Defect::query()
            ->forOrganization($tenant->id())
            ->whereKey($defect->getKey())
            ->firstOrFail();
    }

    protected function tenantDefectAssessment(
        TenantContext $tenant,
        DefectAssessment $assessment,
    ): DefectAssessment {
        return DefectAssessment::query()
            ->forOrganization($tenant->id())
            ->whereKey($assessment->getKey())
            ->firstOrFail();
    }

    protected function tenantDefectAssessmentQuantity(
        TenantContext $tenant,
        DefectAssessmentQuantity $quantity,
    ): DefectAssessmentQuantity {
        return DefectAssessmentQuantity::query()
            ->forOrganization($tenant->id())
            ->whereKey($quantity->getKey())
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

}
