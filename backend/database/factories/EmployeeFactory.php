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
            'specialty' => $this->faker->randomElement([
                'Estilista Principal',
                'Manicurista',
                'Colorista Expert',
                'Barbero',
                'Maquilladora',
                'Esteticista',
            ]),
            'phone' => '+58414' . $this->faker->numerify('#######'),
            'commission_percentage' => $this->faker->randomElement([20.00, 30.00, 40.00, 50.00]),
            'is_active' => true,
        ];
    }

    /**
     * Crea un usuario con rol 'employee' para este empleado, en el mismo negocio.
     */
    public function withUser(): static
    {
        return $this->afterCreating(function (Employee $employee) {
            $user = User::factory()->create([
                'business_id' => $employee->business_id,
                'name' => $employee->name,
                'role' => 'employee',
            ]);

            $employee->update(['user_id' => $user->id]);
        });
    }
}
