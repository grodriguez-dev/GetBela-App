<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Servicios comunes para que los datos faker tengan sentido en GetBela.
     */
    private const SERVICE_NAMES = [
        'Corte de Cabello Caballero',
        'Tinte Completo',
        'Manicura Spa',
        'Pedicura',
        'Secado y Planchado',
        'Barba Premium',
        'Lifting de Pestañas',
        'Maquillaje Social',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business = Business::query()->first() ?? Business::factory()->create();

        // El empleado SIEMPRE debe pertenecer al negocio del servicio.
        $employee = Employee::query()
            ->where('business_id', $business->id)
            ->first()
            ?? Employee::factory()->create(['business_id' => $business->id]);

        $category = Category::query()->inRandomOrder()->first()
            ?? Category::factory()->create();

        return [
            'business_id' => $business->id,
            'category_id' => $category->id,
            'employee_id' => $employee->id,
            'name' => $this->faker->randomElement(self::SERVICE_NAMES),
            'price' => $this->faker->randomFloat(2, 10, 80),
            'duration_minutes' => $this->faker->randomElement([30, 60, 90, 120]),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Si el negocio se sobrescribe al crear el servicio, reasignamos el
     * empleado para que pertenezca al negocio correcto.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Service $service) {
            if (!$service->business_id) {
                return;
            }

            $employee = $service->employee_id
                ? Employee::find($service->employee_id)
                : null;

            if (!$employee || (int) $employee->business_id !== (int) $service->business_id) {
                $employee = Employee::query()
                    ->where('business_id', $service->business_id)
                    ->inRandomOrder()
                    ->first()
                    ?? Employee::factory()->create(['business_id' => $service->business_id]);

                $service->employee_id = $employee->id;
            }
        });
    }
}
