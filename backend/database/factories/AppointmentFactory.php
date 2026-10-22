<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Client;
use App\Models\Service;
use Carbon\Carbon;
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
        $business = Business::query()->first() ?? Business::factory()->create();

        $service = Service::query()
            ->where('business_id', $business->id)
            ->first()
            ?? Service::factory()->create(['business_id' => $business->id]);

        $client = Client::query()
            ->where('business_id', $business->id)
            ->first()
            ?? Client::factory()->create(['business_id' => $business->id]);

        // Hora de inicio y fin coherentes con la duración real del servicio.
        $start = Carbon::createFromTime($this->faker->numberBetween(9, 16), 0, 0);
        $duration = (int) ($service->duration_minutes ?? 60);
        $end = $start->copy()->addMinutes($duration);

        return [
            'business_id' => $business->id,
            'service_id' => $service->id,
            'client_id' => $client->id,
            'date' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
            'status' => 'pending',
            'total_price' => $service->price,
        ];
    }

    /**
     * Alinea servicio y cliente con el negocio de la cita cuando se
     * sobrescribe `business_id` al crearla.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Appointment $appointment) {
            $businessId = $appointment->business_id;
            if (!$businessId) {
                return;
            }

            $service = $appointment->service_id
                ? Service::find($appointment->service_id)
                : null;

            if (!$service || (int) $service->business_id !== (int) $businessId) {
                $service = Service::query()
                    ->where('business_id', $businessId)
                    ->inRandomOrder()
                    ->first()
                    ?? Service::factory()->create(['business_id' => $businessId]);

                $appointment->service_id = $service->id;
            }

            $client = $appointment->client_id
                ? Client::find($appointment->client_id)
                : null;

            if (!$client || (int) $client->business_id !== (int) $businessId) {
                $client = Client::query()
                    ->where('business_id', $businessId)
                    ->inRandomOrder()
                    ->first()
                    ?? Client::factory()->create(['business_id' => $businessId]);

                $appointment->client_id = $client->id;
            }
        });
    }
}
