<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(), // Crea un negocio por defecto si no se le pasa uno
            'name' => $this->faker->name(),
            'phone' => $this->faker->e164PhoneNumber(), // Genera un teléfono limpio tipo +58...
        ];
    }
}
