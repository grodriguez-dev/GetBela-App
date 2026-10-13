<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
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
            'user_id' => null,
            'name' => $this->faker->name(),
            'specialty' => $this->faker->randomElement(['Estilista Principal', 'Manicurista', 'Colorista Expert', 'Barbero']),
            'commission_percentage' => $this->faker->randomElement([20.00, 30.00, 40.00, 50.00]),
        ];
    }

    /**
     * Estado especial: Fuerza al factory a crearle un usuario con rol 'employee'
     */
    public function withUser(): static
    {
        return $this->state(function (array $attributes) {
            // Creamos el usuario amarrado al mismo negocio del empleado
            $user = User::factory()->create([
                'business_id' => $attributes['business_id'] ?? Business::factory(),
                'name' => $attributes['name'] ?? $this->faker->name(),
                'role' => 'employee',
            ]);

            return [
                'user_id' => $user->id,
            ];
        });
    }
}
