<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'name' => $companyName,
            'legal_name' => $companyName.' LTDA',
            'document' => fake()->unique()->numerify('##############'),
            'status' => OrganizationStatus::Active->value,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationStatus::Active->value,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationStatus::Suspended->value,
        ]);
    }
}
