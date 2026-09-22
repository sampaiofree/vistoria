<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Equipment;

final class InspectionSnapshotBuilder
{
    public const VERSION = 1;

    public function build(Equipment $equipment): array
    {
        $equipment->loadMissing([
            'organization',
            'client',
        ]);

        return [
            'organization' => [
                'public_id' => $equipment->organization->public_id,
                'name' => $equipment->organization->name,
                'legal_name' => $equipment->organization->legal_name,
                'document' => $equipment->organization->document,
            ],
            'client' => [
                'public_id' => $equipment->client->public_id,
                'name' => $equipment->client->name,
                'legal_name' => $equipment->client->legal_name,
                'document' => $equipment->client->document,
            ],
            'equipment' => [
                'public_id' => $equipment->public_id,
                'numero_cliente' => $equipment->numero_cliente,
                'numero_interno' => $equipment->numero_interno,
                'maintenance_plan_code' => $equipment->maintenance_plan_code,
                'maintenance_item_code' => $equipment->maintenance_item_code,
                'area_code' => $equipment->area_code,
                'subarea_code' => $equipment->subarea_code,
                'task_list_group' => $equipment->task_list_group,
                'task_list_group_counter' => $equipment->task_list_group_counter,
                'area_name' => $equipment->area_name,
                'subarea_name' => $equipment->subarea_name,
                'tag' => $equipment->tag,
                'normalized_tag' => $equipment->normalized_tag,
                'name' => $equipment->name,
                'description' => $equipment->description,
                'manufacturer' => $equipment->manufacturer,
                'model' => $equipment->model,
                'serial_number' => $equipment->serial_number,
                'asset_code' => $equipment->asset_code,
                'abc_code' => $equipment->abc_code,
                'installation_location' => $equipment->installation_location,
                'commissioned_at' => $equipment->commissioned_at?->toDateString(),
                'status' => $equipment->status->value,
            ],
        ];
    }
}
