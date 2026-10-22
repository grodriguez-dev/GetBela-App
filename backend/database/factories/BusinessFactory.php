<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company() . ' Salon';

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(100, 9999),
            'type' => $this->faker->randomElement(['salon', 'independent']),
            'modality' => $this->faker->randomElement(['local', 'domicilio', 'hibrido']),
            'phone' => '+58412' . $this->faker->numerify('#######'),
            'address' => $this->faker->address(),
            'logo' => null,
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Negocio suspendido.
     */
    public function suspended(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'suspended_at' => now(),
            'suspension_reason' => 'Suspendido por falta de pago.',
        ]);
    }
}
