<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Equipment> */
final class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'tag' => fake()->unique()->bothify('EQ-####'),
            'name' => fake()->word(),
        ];
    }
}
