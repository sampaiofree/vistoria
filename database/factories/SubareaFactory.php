<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use App\Models\Subarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subarea>
 */
class SubareaFactory extends Factory
{
    protected $model = Subarea::class;

    public function definition(): array
    {
        return [
            'organization_id' => function (array $attributes): int {
                $area = $attributes['area_id'];

                if ($area instanceof Area) {
                    return $area->organization_id;
                }

                return Area::query()->findOrFail($area)->organization_id;
            },
            'area_id' => Area::factory(),
            'name' => 'Subarea '.fake()->word(),
            'code' => null,
            'normalized_code' => null,
            'status' => RegistrationStatus::Active,
            'description' => null,
        ];
    }

    public function forArea(Area $area): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $area->organization_id,
            'area_id' => $area->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
