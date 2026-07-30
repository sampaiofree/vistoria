<?php

namespace Database\Factories;

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => UserAccountType::Member->value,
            'status' => UserStatus::Active->value,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'organization_id' => null,
            'account_type' => UserAccountType::SuperAdmin->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Inactive->value,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Suspended->value,
            'suspended_at' => now(),
            'suspension_reason' => 'Suspenso para teste.',
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
