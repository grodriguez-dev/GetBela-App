<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Employee;
use App\Models\Service;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Lista de servicios comunes para que los datos faker tengan sentido en GetBela
        $services = ['Corte de Cabello Caballero', 'Tinte Completo', 'Manicura Spa', 'Pedicura', 'Secado y Planchado', 'Barba Premium'];

        return [
            // 🏢 Conexión directa al negocio multi-tenant
            'business_id' => Business::query()->exists()
                ? Business::query()->first()->id
                : Business::factory()->create()->id,

            'category_id' => Category::inRandomOrder()->first()?->id ?? Category::factory(),

            // 💈 Relación al empleado/estilista asignado
            'employee_id' => Employee::query()->exists() ? Employee::query()->first()->id : Employee::factory()->create()->id,
            
            // 🏷️ Nombre del servicio aleatorio de la lista
            'name' => $this->faker->randomElement($services),

            // 💰 Precio base razonable para un salón
            'price' => $this->faker->randomFloat(2, 10, 80), // Ej: entre 10.00 y 80.00

            // ⏱️ Tu columna nativa: duración en minutos (múltiplos de 30 para la agenda)
            'duration_minutes' => $this->faker->randomElement([30, 60, 90, 120]),

            // 📝 Una descripción opcional por si la usas
            'description' => $this->faker->sentence(),
        ];
    }
}