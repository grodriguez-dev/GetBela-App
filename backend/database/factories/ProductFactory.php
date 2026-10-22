<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
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
            'business_id' => Business::query()->exists()
                ? Business::query()->first()->id
                : Business::factory()->create()->id,
            'name' => $this->faker->randomElement([
                'Shampoo Matizador Kerastase',
                'Tinte Igora Royal 7-77',
                'Olaplex N3',
                'Aceite de Argán Moroccanoil',
                'Polvo Decolorante Schwarzkopf',
                'Crema Hidratante Loreal',
            ]) . ' ' . $this->faker->unique()->numberBetween(100, 999),
            'brand' => $this->faker->randomElement(['Kerastase', 'Schwarzkopf', 'Loreal', 'Olaplex', 'Moroccanoil']),
            'description' => $this->faker->sentence(),
            'stock_current' => $this->faker->numberBetween(10, 30),
            'stock_min' => 5,
            'purchase_price' => $this->faker->randomFloat(2, 5, 30),
            'sale_price' => $this->faker->randomFloat(2, 15, 80),
            'type' => $this->faker->randomElement(['uso_interno', 'venta_publico', 'mixto']),
        ];
    }
}
