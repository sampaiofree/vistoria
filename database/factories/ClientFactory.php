<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company(),
            'legal_name' => fake()->company().' LTDA',
            'document' => fake()->unique()->numerify('##############'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => RegistrationStatus::Active,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
