<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectCategory> */
final class DefectCategoryFactory extends Factory
{
    protected $model = DefectCategory::class;

    public function definition(): array
    {
        $code = fake()->unique()->lexify('CAT???');

        return [
            'public_id' => null,
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'code' => strtoupper($code),
            'description' => null,
            'status' => RegistrationStatus::Active,
            'requires_location_map' => false,
            'position' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
