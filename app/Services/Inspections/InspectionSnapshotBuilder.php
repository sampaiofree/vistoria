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
            'unit',
            'area',
            'subarea',
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
            'unit' => [
                'public_id' => $equipment->unit->public_id,
                'name' => $equipment->unit->name,
                'code' => $equipment->unit->code,
            ],
            'area' => [
                'public_id' => $equipment->area->public_id,
                'name' => $equipment->area->name,
                'code' => $equipment->area->code,
            ],
            'subarea' => $equipment->subarea === null
                ? null
                : [
                    'public_id' => $equipment->subarea->public_id,
                    'name' => $equipment->subarea->name,
                    'code' => $equipment->subarea->code,
                ],
            'equipment' => [
                'public_id' => $equipment->public_id,
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
