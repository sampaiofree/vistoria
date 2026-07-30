<?php

declare(strict_types=1);

namespace App\Actions\Equipments;

use App\Enums\EquipmentStatus;
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

final class CreateEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, array $data): Equipment
    {
        return DB::transaction(function () use ($actor, $data): Equipment {
            $client = Client::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['client_id']);

            $unit = ClientUnit::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['client_unit_id']);

            $area = Area::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['area_id']);

            $subarea = isset($data['subarea_id']) && $data['subarea_id'] !== null
                ? Subarea::query()
                    ->forOrganization($this->tenant->id())
                    ->findOrFail($data['subarea_id'])
                : null;

            $this->validateHierarchy($client, $unit, $area, $subarea);

            $tag = TextNormalizer::equipmentTag((string) $data['tag']);

            $existing = Equipment::query()
                ->withTrashed()
                ->forOrganization($this->tenant->id())
                ->where('client_unit_id', $unit->getKey())
                ->where('normalized_tag', $tag)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'tag' => 'Já existe um equipamento com esse TAG nesta unidade.',
                ]);
            }

            return Equipment::query()->create([
                'organization_id' => $this->tenant->id(),
                'client_id' => $client->getKey(),
                'client_unit_id' => $unit->getKey(),
                'area_id' => $area->getKey(),
                'subarea_id' => $subarea?->getKey(),
                'tag' => $tag,
                'normalized_tag' => $tag,
                'defect_code_prefix' => TextNormalizer::technicalCode($data['defect_code_prefix'] ?? null),
                'name' => TextNormalizer::text((string) $data['name']),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'manufacturer' => TextNormalizer::nullableText($data['manufacturer'] ?? null),
                'model' => TextNormalizer::nullableText($data['model'] ?? null),
                'serial_number' => TextNormalizer::nullableText($data['serial_number'] ?? null),
                'asset_code' => TextNormalizer::technicalCode($data['asset_code'] ?? null),
                'abc_code' => TextNormalizer::technicalCode($data['abc_code'] ?? null),
                'installation_location' => TextNormalizer::nullableText($data['installation_location'] ?? null),
                'commissioned_at' => $data['commissioned_at'] ?? null,
                'status' => EquipmentStatus::Active,
                'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        });
    }

    private function validateHierarchy(
        Client $client,
        ClientUnit $unit,
        Area $area,
        ?Subarea $subarea,
    ): void {
        if (! $client->isActive()) {
            throw ValidationException::withMessages([
                'client_id' => 'O cliente está inativo.',
            ]);
        }

        if (
            $unit->client_id !== $client->getKey()
            || ! $unit->isOperationallyActive()
        ) {
            throw ValidationException::withMessages([
                'client_unit_id' => 'A unidade não pertence ao cliente ou está inativa.',
            ]);
        }

        if (
            $area->client_unit_id !== $unit->getKey()
            || ! $area->isOperationallyActive()
        ) {
            throw ValidationException::withMessages([
                'area_id' => 'A área não pertence à unidade ou está inativa.',
            ]);
        }

        if (
            $subarea !== null
            && (
                $subarea->area_id !== $area->getKey()
                || ! $subarea->isOperationallyActive()
            )
        ) {
            throw ValidationException::withMessages([
                'subarea_id' => 'A subárea não pertence à área ou está inativa.',
            ]);
        }
    }
}
