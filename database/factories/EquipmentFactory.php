<?php

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Organization;
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
            'maintenance_plan_code' => null,
            'maintenance_item_code' => fake()->unique()->numerify('##########'),
            'area_code' => null,
            'subarea_code' => null,
            'task_list_group' => null,
            'task_list_group_counter' => null,
            'area_name' => null,
            'subarea_name' => null,
            'tag' => TextNormalizer::equipmentTag($tag),
            'normalized_tag' => TextNormalizer::equipmentTag($tag),
            'defect_code_prefix' => fake()->unique()->bothify('PF-########'),
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
    ): static {
        return $this->state(fn (): array => [
            'organization_id' => $client->organization_id,
            'client_id' => $client->getKey(),
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
        $organizationId = $this->organizationId($attributes);

        return Client::query()
            ->where('organization_id', $organizationId)
            ->first()
            ?? Client::factory()->create(['organization_id' => $organizationId]);
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
}
