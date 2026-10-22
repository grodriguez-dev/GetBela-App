<?php

namespace Database\Factories;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => 'owner',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'business_id' => null,
        ];
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

    /**
     * Super administrador de la plataforma (sin negocio asociado).
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'admin',
            'business_id' => null,
        ]);
    }

    /**
     * Dueño de un negocio.
     */
    public function owner(?int $businessId = null): static
    {
        return $this->state(fn () => [
            'role' => 'owner',
            'business_id' => $businessId,
        ]);
    }

    /**
     * Empleado con acceso a la plataforma.
     */
    public function employee(?int $businessId = null): static
    {
        return $this->state(fn () => [
            'role' => 'employee',
            'business_id' => $businessId,
        ]);
    }
}
