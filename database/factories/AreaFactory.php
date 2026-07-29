<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use App\Models\ClientUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'organization_id' => function (array $attributes): int {
                $unit = $attributes['client_unit_id'];

                if ($unit instanceof ClientUnit) {
                    return $unit->organization_id;
                }

                return ClientUnit::query()->findOrFail($unit)->organization_id;
            },
            'client_unit_id' => ClientUnit::factory(),
            'name' => 'Area '.fake()->word(),
            'code' => null,
            'normalized_code' => null,
            'status' => RegistrationStatus::Active,
            'description' => null,
        ];
    }

    public function forUnit(ClientUnit $unit): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $unit->organization_id,
            'client_unit_id' => $unit->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
