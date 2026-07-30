<?php

namespace App\Actions\Equipments;

use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\Subarea;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, Equipment $equipment, array $data): Equipment
    {
        return DB::transaction(function () use ($actor, $equipment, $data): Equipment {
            $equipment = Equipment::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($equipment->getKey());

            $client = Client::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['client_id']);

            $unit = ClientUnit::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['client_unit_id']);

            $area = Area::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['area_id']);

            $subarea = isset($data['subarea_id'])
                ? Subarea::query()
                    ->forOrganization($this->tenant->id())
                    ->findOrFail($data['subarea_id'])
                : null;

            $this->validateHierarchy($client, $unit, $area, $subarea);

            $tag = TextNormalizer::equipmentTag($data['tag']);

            $equipment->update([
                'client_id' => $client->id,
                'client_unit_id' => $unit->id,
                'area_id' => $area->id,
                'subarea_id' => $subarea?->id,
                'tag' => $tag,
                'normalized_tag' => $tag,
                'name' => TextNormalizer::text((string) $data['name']),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'manufacturer' => TextNormalizer::nullableText($data['manufacturer'] ?? null),
                'model' => TextNormalizer::nullableText($data['model'] ?? null),
                'serial_number' => TextNormalizer::nullableText($data['serial_number'] ?? null),
                'asset_code' => TextNormalizer::technicalCode($data['asset_code'] ?? null),
                'abc_code' => TextNormalizer::technicalCode($data['abc_code'] ?? null),
                'installation_location' => TextNormalizer::nullableText($data['installation_location'] ?? null),
                'commissioned_at' => $data['commissioned_at'] ?? null,
                'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
                'updated_by' => $actor->id,
            ]);

            return $equipment->refresh();
        });
    }

    private function validateHierarchy(
        Client $client,
        ClientUnit $unit,
        Area $area,
        ?Subarea $subarea,
    ): void {
        if ($unit->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'client_unit_id' => 'A unidade não pertence ao cliente informado.',
            ]);
        }

        if ($area->client_unit_id !== $unit->id) {
            throw ValidationException::withMessages([
                'area_id' => 'A área não pertence à unidade informada.',
            ]);
        }

        if ($subarea !== null && $subarea->area_id !== $area->id) {
            throw ValidationException::withMessages([
                'subarea_id' => 'A subárea não pertence à área informada.',
            ]);
        }
    }
}
