<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generamos una hora aleatoria de inicio (ej: 10:00:00)
        $startHour = $this->faker->numberBetween(9, 17);
        $startTime = sprintf('%02d:00:00', $startHour);

        return [
            // 🏢 Relación al negocio multi-tenant
            'business_id' => Business::query()->exists()
                ? Business::query()->first()->id
                : Business::factory()->create()->id,

            // 📦 Relación al servicio solicitado
            'service_id' => Service::query()->exists() ? Service::query()->first()->id : Service::factory()->create()->id,

            // 📅 Formato de fecha consistente (Y-m-d 00:00:00) como maneja tu BD
            'date' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d 00:00:00'),

            // 🕒 Horas de inicio y fin limpias en strings
            'start_time' => $startTime,
            'end_time' => sprintf('%02d:00:00', $startHour + 1), // Por defecto asume 1 hora de duración

            // 👤 Datos del cliente
            'client_id' => Client::query()->exists() ? Client::query()->first()->id : Client::factory()->create()->id,

            // ⚙️ Estado por defecto
            'status' => 'pending',

            'total_price' => $this->faker->randomFloat(2, 10, 80),
        ];
    }
}
