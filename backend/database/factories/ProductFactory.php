<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => $this->faker->randomElement([
                'Shampoo Matizador Kerastase', 'Tinte Igora Royal 7-77', 'Olaplex N3', 
                'Aceite de Argán Moroccanoil', 'Polvo Decolorante Schwarzkopf', 'Crema Hidratante Loreal'
            ]) . ' ' . $this->faker->unique()->numberBetween(100, 999),
            'stock_current' => $this->faker->numberBetween(10, 30), // <-- Corregido
            'sale_price' => $this->faker->randomFloat(2, 15, 80),    // <-- Corregido
            'type' => $this->faker->randomElement(['uso_interno', 'venta_publico']), // <-- Agregado
        ];
    }
}
