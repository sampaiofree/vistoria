<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectClassification> */
final class DefectClassificationFactory extends Factory
{
    protected $model = DefectClassification::class;

    public function definition(): array
    {
        return [
            'public_id' => null,
            'organization_id' => null,
            'defect_category_id' => DefectCategory::factory(),
            'code' => strtoupper(fake()->unique()->lexify('CL-?')),
            'name' => fake()->words(2, true),
            'description' => null,
            'color' => null,
            'status' => RegistrationStatus::Active,
            'position' => 1,
            'severity_rank' => null,
            'lower_limit' => null,
            'upper_limit' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DefectClassification $classification): void {
            if ($classification->defect_category_id !== null) {
                $classification->organization_id = DefectCategory::query()->find($classification->defect_category_id)?->organization_id;
            }
        });
    }
}
