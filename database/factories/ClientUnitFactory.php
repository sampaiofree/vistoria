<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientUnit>
 */
class ClientUnitFactory extends Factory
{
    protected $model = ClientUnit::class;

    public function definition(): array
    {
        $code = fake()->unique()->bothify('UN-###');

        return [
            'organization_id' => function (array $attributes): int {
                $client = $attributes['client_id'];

                if ($client instanceof Client) {
                    return $client->organization_id;
                }

                return Client::query()->findOrFail($client)->organization_id;
            },
            'client_id' => Client::factory(),
            'name' => 'Unidade '.fake()->city(),
            'code' => $code,
            'normalized_code' => TextNormalizer::technicalCode($code),
            'timezone' => 'America/Sao_Paulo',
            'address_line' => fake()->streetName(),
            'address_number' => fake()->buildingNumber(),
            'district' => fake()->word(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'country_code' => 'BR',
            'status' => RegistrationStatus::Active,
            'notes' => null,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
