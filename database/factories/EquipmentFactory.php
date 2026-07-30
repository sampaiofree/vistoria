<?php

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\Organization;
use App\Models\Subarea;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        $tag = fake()->unique()->bothify('EQ-####');

        return [
            'organization_id' => Organization::factory(),
            'client_id' => fn (array $attributes): int => $this->createClient($attributes)->getKey(),
            'client_unit_id' => fn (array $attributes): int => $this->createUnit($attributes)->getKey(),
            'area_id' => fn (array $attributes): int => $this->createArea($attributes)->getKey(),
            'subarea_id' => fn (array $attributes): int => $this->createSubarea($attributes)->getKey(),
            'tag' => TextNormalizer::equipmentTag($tag),
            'normalized_tag' => TextNormalizer::equipmentTag($tag),
            'defect_code_prefix' => null,
            'name' => fake()->randomElement([
                'Ventilador',
                'Bomba',
                'Transportador',
                'Motor',
                'Redutor',
            ]),
            'description' => null,
            'manufacturer' => fake()->company(),
            'model' => fake()->bothify('MDL-###'),
            'serial_number' => fake()->bothify('SN-########'),
            'asset_code' => null,
            'abc_code' => fake()->randomElement(['A', 'B', 'C']),
            'installation_location' => null,
            'commissioned_at' => null,
            'status' => EquipmentStatus::Active,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function inStructure(
        Client $client,
        ClientUnit $unit,
        Area $area,
        ?Subarea $subarea = null,
    ): static {
        return $this->state(fn (): array => [
            'organization_id' => $client->organization_id,
            'client_id' => $client->getKey(),
            'client_unit_id' => $unit->getKey(),
            'area_id' => $area->getKey(),
            'subarea_id' => $subarea?->getKey(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => EquipmentStatus::Inactive,
        ]);
    }

    public function decommissioned(): static
    {
        return $this->state(fn (): array => [
            'status' => EquipmentStatus::Decommissioned,
        ]);
    }

    private function organizationId(array $attributes): int
    {
        $organization = $attributes['organization_id'];

        if ($organization instanceof Organization) {
            return (int) $organization->getKey();
        }

        if ($organization instanceof Factory) {
            return (int) $organization->create()->getKey();
        }

        return (int) $organization;
    }

    private function createClient(array $attributes): Client
    {
        return Client::factory()->create([
            'organization_id' => $this->organizationId($attributes),
        ]);
    }

    private function createUnit(array $attributes): ClientUnit
    {
        $clientId = $this->clientId($attributes);

        return ClientUnit::factory()->create([
            'organization_id' => Client::query()->findOrFail($clientId)->organization_id,
            'client_id' => $clientId,
        ]);
    }

    private function createArea(array $attributes): Area
    {
        $unitId = $this->clientUnitId($attributes);

        return Area::factory()->create([
            'organization_id' => ClientUnit::query()->findOrFail($unitId)->organization_id,
            'client_unit_id' => $unitId,
        ]);
    }

    private function createSubarea(array $attributes): Subarea
    {
        $areaId = $this->areaId($attributes);

        return Subarea::factory()->create([
            'organization_id' => Area::query()->findOrFail($areaId)->organization_id,
            'area_id' => $areaId,
        ]);
    }

    private function clientId(array $attributes): int
    {
        $client = $attributes['client_id'];

        if ($client instanceof Client) {
            return (int) $client->getKey();
        }

        if ($client instanceof Factory) {
            return (int) $client->create([
                'organization_id' => $this->organizationId($attributes),
            ])->getKey();
        }

        return (int) $client;
    }

    private function clientUnitId(array $attributes): int
    {
        $unit = $attributes['client_unit_id'];

        if ($unit instanceof ClientUnit) {
            return (int) $unit->getKey();
        }

        if ($unit instanceof Factory) {
            $clientId = $this->clientId($attributes);

            return (int) $unit->create([
                'organization_id' => Client::query()->findOrFail($clientId)->organization_id,
                'client_id' => $clientId,
            ])->getKey();
        }

        return $unit instanceof ClientUnit
            ? (int) $unit->getKey()
            : (int) $unit;
    }

    private function areaId(array $attributes): int
    {
        $area = $attributes['area_id'];

        if ($area instanceof Area) {
            return (int) $area->getKey();
        }

        if ($area instanceof Factory) {
            $unitId = $this->clientUnitId($attributes);

            return (int) $area->create([
                'organization_id' => ClientUnit::query()->findOrFail($unitId)->organization_id,
                'client_unit_id' => $unitId,
            ])->getKey();
        }

        return $area instanceof Area
            ? (int) $area->getKey()
            : (int) $area;
    }
}
