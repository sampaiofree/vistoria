<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GutCriterion;
use App\Models\DefectCategory;
use App\Models\DefectCategoryGutOption;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectCategoryGutOption> */
final class DefectCategoryGutOptionFactory extends Factory
{
    protected $model = DefectCategoryGutOption::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'defect_category_id' => DefectCategory::factory(),
            'criterion' => GutCriterion::Gravity,
            'score' => fake()->numberBetween(0, 5),
            'color' => fake()->randomElement(['#0EA5E9', '#22C55E', '#EAB308', '#F59E0B', '#EF4444']),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
