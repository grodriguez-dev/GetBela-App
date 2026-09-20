<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Creamos el entorno base del salón actual
        $this->business = Business::create([
            'name' => 'Salon Bela Test',
            'slug' => 'salon-bela-test',
            'address' => 'Dirección de Prueba',
            'phone' => '+584120044000'
        ]);

        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'owner'
        ]);
    }

    /** @test */
    public function test_an_admin_can_view_their_clients_list_with_appointment_count_and_services()
    {
        $this->actingAs($this->user, 'sanctum');

        // 1. Creamos un cliente para nuestro negocio
        $client = Client::create([
            'business_id' => $this->business->id,
            'name' => 'Laura Pérez',
            'phone' => '+584121111111'
        ]);

        // 2. Creamos un servicio y le agendamos una cita a ese cliente
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Manicurista rusa',
            'price' => 20.00,
            'duration_minutes' => 50
        ]);
        
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $service->id,
            'client_id' => $client->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'total_price' => 40.00
        ]);

        // 3. Ejecutamos la petición GET
        $response = $this->getJson('/api/clients');

        $response->assertStatus(200);
        
        // Verificamos que viene el cliente con su conteo de citas
        $response->assertJsonFragment([
            'name' => 'Laura Pérez',
            'phone' => '+584121111111',
            'appointments_count' => 1
        ]);

        // Verificamos que dentro del historial del cliente viaja el nombre del servicio solicitado
        $response->assertJsonStructure([
            'clients' => [
                '*' => [
                    'id', 'name', 'phone', 'appointments_count',
                    'appointments' => [
                        '*' => [
                            'id', 'date', 'service' => ['id', 'name']
                        ]
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function test_a_salon_cannot_see_the_client_list_of_another_salon()
    {
        // 1. Creamos un salón rival y le asignamos un cliente
        $otherBusiness = Business::create([
            'name' => 'Salón Competencia',
            'slug' => 'salon-competencia',
            'address' => 'Dirección de test',
            'phone' => '+584120044880'
        ]);
        Client::create([
            'business_id' => $otherBusiness->id,
            'name' => 'Cliente Espía',
            'phone' => '+584122222222'
        ]);

        // 2. Nos autenticamos con el usuario de NUESTRO salón original
        $this->actingAs($this->user, 'sanctum');

        // 3. Ejecutamos la petición GET
        $response = $this->getJson('/api/clients');

        $response->assertStatus(200);

        // El JSON no debe contener bajo ninguna circunstancia al cliente del otro salón
        $response->assertJsonMissing(['name' => 'Cliente Espía']);
        $this->assertCount(0, $response->json('clients'));
    }
}
