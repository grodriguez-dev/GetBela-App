<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use App\Models\BlockTime;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\Product;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AppointmentFlowTest extends TestCase
{
    use RefreshDatabase; // Vacía la base de datos en memoria antes de cada test

    private $user;
    private $business;
    private $service;
    private $employee;
    private $product;

    /**
     * Este método se ejecuta ANTES de cada test individual.
     * Aquí preparamos el escenario común (Seed base).
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear el negocio
        $this->business = Business::create([
            'name' => 'Bela Test Studio',
            'slug' => 'bela-test-studio',
            'address' => 'Dirección de Prueba',
            'phone' => '+584120000000'
        ]);

        // 2. Crear el usuario administrador asociado al negocio
        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'owner',
            'email' => 'tester@getbela.com',
            'password' => bcrypt('password123')
        ]);

        // 3. Configurar horario comercial (Abierto todos los días de 08:00 a 19:00)
        for ($i = 1; $i <= 7; $i++) {
            BusinessHour::create([
                'business_id' => $this->business->id,
                'day_of_week' => $i,
                'opening_time' => '08:00:00',
                'closing_time' => '19:00:00',
                'is_closed' => false
            ]);
        }

        // 4. Crear un empleado
        $this->employee = Employee::create([
            'business_id' => $this->business->id,
            'name' => 'María Estilista',
            'phone' => '+584121111111',
            'email' => 'maria@getbela.com'
        ]);

        // 5. Crear un producto de prueba (Insumo)
        $this->product = Product::create([
            'business_id' => $this->business->id,
            'name' => 'Shampoo Kerastase',
            'stock_current' => 15,
            'sale_price' => 35.00,
            'type' => 'venta_publico'
        ]);

        // 6. Crear un servicio y vincularle el producto en la receta
        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Corte Express',
            'price' => 40.00,
            'duration_minutes' => 60
        ]);
        
        // Adjuntar el producto a la receta base del servicio
        $this->service->products()->attach($this->product->id, ['quantity_default' => 1]);
    }

    /** @test */
    public function test_an_authenticated_user_can_create_a_valid_appointment_with_its_recipe()
    {
        // Simulamos que estamos autenticados como el administrador
        $this->actingAs($this->user, 'sanctum');

        
        // Datos exactos que enviaríamos desde Postman/Vue
        $payload = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta Nueva',
            'client_phone' => '+584125555555',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00:00'
        ];

        // Ejecutamos la petición POST
        $response = $this->postJson('/api/appointments', $payload);

        // --- ASERCIONES (Las verdaderas pruebas de software) ---
        
        // 1. Validar que la respuesta sea 201 Created
        $response->assertStatus(201);

        // 2. Validar la estructura JSON de retorno
        $response->assertJsonStructure([
            'message',
            'appointment' => [
                'id',
                'business_id',
                'status',
                'total_price',
                'consumed_products'
            ]
        ]);

        $client = Client::where('phone', '+584125555555')->first();

        // 3. Validar que la cita se guardó correctamente en la Base de Datos
        $this->assertDatabaseHas('appointments', [
            'business_id' => $this->business->id,
            'client_id' => $client->id,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00', // 9:00 + 60 min de duración del servicio
            'status' => 'pending',
            'total_price' => 40.00
        ]);

        // 4. Validar el Motor de Inventario: Que la receta se vinculó a la tabla pivote de consumos
        $this->assertDatabaseHas('appointment_product', [
            'product_id' => $this->product->id,
            'quantity_used' => 1
        ]);
    }

    /** @test */
    public function test_it_keeps_valid_today_appointments_as_pending_when_they_are_still_inside_the_business_hours()
    {
        $this->actingAs($this->user, 'sanctum');

        $today = now()->format('Y-m-d');
        $startTime = now()->copy()->addMinutes(30)->format('H:i:s');
        $endTime = now()->copy()->addMinutes(90)->format('H:i:s');

        $appointment = Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => Client::factory()->create(['business_id' => $this->business->id])->id,
            'date' => $today,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'total_price' => 40.00,
        ]);

        $response = $this->getJson('/api/appointments');

        $response->assertStatus(200);
        $this->assertSame('pending', $appointment->fresh()->status);
        $response->assertJsonFragment([
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function test_it_rejects_same_day_appointments_in_the_past()
    {
        $this->actingAs($this->user, 'sanctum');

        $payload = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Cliente Pasado',
            'client_phone' => '+584125555111',
            'date' => now()->format('Y-m-d'),
            'start_time' => now()->copy()->subMinutes(30)->format('H:i:s'),
        ];

        $response = $this->postJson('/api/appointments', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'ya pasó',
            (string) $response->json('message'),
        );
    }

    /** @test */
    public function test_it_forces_the_first_shield_and_rejects_appointments_outside_business_hours()
    {
        // 1. Nos autenticamos como el administrador del salón
        $this->actingAs($this->user, 'sanctum');

        // Escenario A: Intentar agendar a una hora donde el salón está CERRADO (Ej: 11:00 PM)
        $payloadCitaNocturna = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta uno',
            'client_phone' => '+584125555555',
            'date' => now()->addDay()->format('Y-m-d'), 
            'start_time' => '23:00:00' // El salón cierra a las 19:00:00
        ];

        $responseNocturna = $this->postJson('/api/appointments', $payloadCitaNocturna);

        // Aserciones para el Escenario A
        $responseNocturna->assertStatus(422);
        $responseNocturna->assertJsonFragment([
            'message' => 'La cita se sale del horario de atención del establecimiento.'
        ]);


        // Escenario B: Intentar agendar en un día de la semana que está marcado como cerrado
        // Primero, vamos a modificar el Domingo (Día 7) en nuestra base de datos en memoria para que esté cerrado
        BusinessHour::where('business_id', $this->business->id)
            ->where('day_of_week', 7)
            ->update([
                'is_closed' => true
            ]);

        $payloadDomingo = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta dos',
            'client_phone' => '+584125555777',
            'date' => now()->next(Carbon::SUNDAY)->format('Y-m-d'), // Domingo
            'start_time' => '10:00:00'
        ];

        $responseDomingo = $this->postJson('/api/appointments', $payloadDomingo);

        // Aserciones para el Escenario B
        $responseDomingo->assertStatus(422);
        $responseDomingo->assertJsonFragment([
            'message' => 'El negocio se encuentra cerrado el día solicitado.'
        ]);

        // 2. Aserción de seguridad final: Verificar que NINGUNA de estas dos citas se guardó en la BD
        $this->assertDatabaseCount('appointments', 0);

    }

    /** @test */
    public function test_it_accepts_sunday_business_hours_when_the_frontend_uses_zero_based_weekday()
    {
        $this->actingAs($this->user, 'sanctum');

        BusinessHour::where('business_id', $this->business->id)
            ->where('day_of_week', 7)
            ->update([
                'day_of_week' => 0,
                'opening_time' => '08:00:00',
                'closing_time' => '19:00:00',
                'is_closed' => false,
            ]);

        $payload = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Cliente Domingo',
            'client_phone' => '+584125555999',
            'date' => now()->next(Carbon::SUNDAY)->format('Y-m-d'),
            'start_time' => '09:00:00',
        ];

        $response = $this->postJson('/api/appointments', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function test_it_forces_the_second_shield_and_prevents_overlapping_appointments_for_the_same_employee()
    {
        $this->actingAs($this->user, 'sanctum');

        $fechaCita = now()->addDay()->format('Y-m-d');

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Forzamos los IDs exactos que el controlador va a buscar (1 y 1)
        Appointment::create([
            'business_id' => $this->business->id, // Asegura que sea el mismo del administrador
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => $fechaCita,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'confirmed', // Debe estar dentro de los estados válidos del Escudo 2
            'total_price' => 40.00
        ]);

        // 2. Intentamos crear la SEGUNDA cita que se solapa
        $payloadSolapado = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta Solapada',
            'client_phone' => '+584125599555',
            'date' => $fechaCita, 
            'start_time' => '09:30:00' 
        ];

        $response = $this->postJson('/api/appointments', $payloadSolapado);

        // --- ASERCIONES ---
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El horario solicitado o la especialista se encuentra ocupada en ese rango de tiempo.'
        ]);

        $this->assertDatabaseCount('appointments', 1);
    }

    /** @test */
    public function test_it_forces_the_third_shield_and_prevents_appointments_during_manually_blocked_times()
    {
        $this->actingAs($this->user, 'sanctum');

        $fechaBloqueo = now()->addDay()->format('Y-m-d');

        // 1. Simulamos un bloqueo manual de la administración en la base de datos
        // Bloqueamos el rango de 12:00 PM a 01:00 PM (Hora de almuerzo) para esa especialista
        BlockTime::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id, // Bloqueo específico para María
            'date' => $fechaBloqueo,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'reason' => 'Hora de Almuerzo de la Especialista'
        ]);

        // 2. Intentamos agendar una cita que choca con ese almuerzo (De 12:30 PM a 01:30 PM)
        $payloadEnAlmuerzo = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta Silva',
            'client_phone' => '+584125599775',
            'date' => $fechaBloqueo,
            'start_time' => '12:30:00' // Se mete dentro del rango bloqueado
        ];

        // Ejecutamos la petición POST
        $response = $this->postJson('/api/appointments', $payloadEnAlmuerzo);

        // --- ASERCIONES ---
        
        // Debe rebotar con 422 por culpa del bloqueo administrativo
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El horario solicitado se encuentra bloqueado por la administración (Ausencia, almuerzo o descanso).'
        ]);

        // La base de datos de citas debe seguir estando vacía para este escenario
        $this->assertDatabaseCount('appointments', 0);
    }

    /** @test */
    public function test_it_forces_the_third_shield_variation_and_prevents_appointments_during_global_salon_blocks()
    {
        $this->actingAs($this->user, 'sanctum');

        $fechaBloqueo = now()->addDay()->format('Y-m-d');

        // 1. Creamos un bloqueo GLOBAL (employee_id => null)
        // Todo el salón cierra de 02:00 PM a 03:00 PM por reunión de equipo
        BlockTime::create([
            'business_id' => $this->business->id,
            'service_id' => null, // Bloqueo general para todos
            'date' => $fechaBloqueo,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'reason' => 'Reunión General de Personal'
        ]);

        // 2. Intentamos agendar con María en ese rango (De 14:15 PM a 15:15 PM)
        $payloadEnBloqueoGlobal = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta Josefa',
            'client_phone' => '+584123399775',
            'date' => $fechaBloqueo,
            'start_time' => '14:15:00'
        ];

        $response = $this->postJson('/api/appointments', $payloadEnBloqueoGlobal);

        // --- ASERCIONES ---
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El horario solicitado se encuentra bloqueado por la administración (Ausencia, almuerzo o descanso).'
        ]);

        $this->assertDatabaseCount('appointments', 0);
    }

    /** @test */
    public function test_it_allows_appointments_if_the_overlapping_appointment_is_cancelled()
    {
        $this->actingAs($this->user, 'sanctum');

        $fechaLibre = now()->addDay()->format('Y-m-d');

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // 1. Creamos una cita en el mismo horario (09:00 a 10:00) pero CANCELADA
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => $fechaLibre,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'cancelled', // Estado cancelado: libera el espacio
            'total_price' => 40.00
        ]);

        // 2. Una nueva clienta intenta agendar exactamente en el mismo horario liberado
        $payloadNuevaCita = [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Clienta Aprovechada',
            'client_phone' => '+584125599775',
            'date' => $fechaLibre,
            'start_time' => '09:00:00'
        ];

        $response = $this->postJson('/api/appointments', $payloadNuevaCita);

        // --- ASERCIONES ---
        
        // Debería permitirlo con éxito (201 Created) porque el espacio quedó libre
        $response->assertStatus(201);
        
        // Ahora la base de datos debe tener 2 registros (La cancelada vieja + la nueva confirmada)
        $this->assertDatabaseCount('appointments', 2);
    }

    /** @test */
    public function test_it_returns_422_if_the_requested_service_does_not_exist()
    {
        $this->actingAs($this->user, 'sanctum');

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Enviamos un ID de servicio inexistente (999)
        $payloadServicioInvalido = [
            'business_id' => $this->business->id,
            'service_id' => 999, 
            'client_name' => 'Clienta Hernandez',
            'client_phone' => '+584125599445',
            'date' => now()->addDay()->format('Y-m-d'), // Aseguramos fecha futura dinámica
            'start_time' => '09:00:00'
        ];

        $response = $this->postJson('/api/appointments', $payloadServicioInvalido);

        // --- ASERCIONES ---
        
        // Cambiamos de 404 a 422, que es la respuesta real de tu Request Validation
        $response->assertStatus(422);
        
        // Verificamos que contenga el error específico de service_id
        $response->assertJsonValidationErrors(['service_id']);
        
        // Nos aseguramos de que no se haya insertado nada
        $this->assertDatabaseCount('appointments', 0);
    }

    /** @test */
    public function test_it_prevents_appointments_from_being_scheduled_in_the_past()
    {
        $this->actingAs($this->user, 'sanctum');

        // Intentamos agendar una cita para el año 2020 (Claramente el pasado)
        $payloadPasado = [
            'service_id' => $this->service->id,
            'client_name' => 'Clienta',
            'client_phone' => '+584122299775',
            'date' => now()->subDays(5)->format('Y-m-d'), 
            'start_time' => '09:00:00'
        ];

        $response = $this->postJson('/api/appointments', $payloadPasado);

        // Debe rebotar por validación de fecha inválida (Pasado)
        $response->assertStatus(422);
        
        // Dependiendo de cómo lo manejes en tu Request Validation, puedes validar el fragmento:
        $response->assertJsonValidationErrors(['date']);
    }

    /** @test */
    public function test_it_prevents_updating_an_appointment_to_a_time_that_is_already_occupied()
    {
        $this->actingAs($this->user, 'sanctum');
        $fechaCita = now()->addDay()->format('Y-m-d');

        $clientA = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Cita A: De 09:00 a 10:00 con María (La que intentaremos mover)
        $appointmentA = Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $clientA->id,
            'date' => $fechaCita,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'confirmed',
            'total_price' => 40.00
        ]);

        $clientB = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Cita B: De 11:00 a 12:00 con María (El horario ocupado)
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $clientB->id,
            'date' => $fechaCita,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'status' => 'confirmed',
            'total_price' => 40.00
        ]);

        // Intentamos mover la Cita A para las 11:30 (Chocando con la Cita B)
        $payloadInvalido = [
            'start_time' => '11:30:00'
        ];

        $response = $this->putJson("/api/appointments/{$appointmentA->id}", $payloadInvalido);

        // --- ASERCIONES ---
        // Debe rebotar con 422 porque el algoritmo anti-solapamiento también debe proteger el UPDATE
        $response->assertStatus(422);
    }

    /** @test */
    public function test_an_authenticated_user_can_cancel_an_appointment_successfully()
    {
        $this->actingAs($this->user, 'sanctum');

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // 1. Creamos una cita activa para mañana
        $appointment = Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'status' => 'confirmed',
            'total_price' => 40.00
        ]);

        // 2. Le pegamos a tu ruta real enviando status => 'cancelled'
        $response = $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'cancelled'
        ]);

        // --- ASERCIONES ---
        $response->assertStatus(200);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled'
        ]);
    }

    /** @test */
    public function test_it_prevents_canceling_past_appointments()
    {
        $this->actingAs($this->user, 'sanctum');

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // 1. Creamos una cita vieja en el pasado (Hace 3 días)
        $appointmentPasada = Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => now()->subDays(3)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'total_price' => 40.00
        ]);

        // 2. Intentamos cambiar su estado a cancelado
        $response = $this->patchJson("/api/appointments/{$appointmentPasada->id}/status", [
            'status' => 'cancelled'
        ]);

        // --- ASERCIONES ---
        // Esperamos un 422 porque el controlador va a bloquear modificaciones en el pasado
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'No es posible modificar o cancelar una cita que ya ha pasado.'
        ]);
    }

    /** @test */
    public function test_it_reuses_existing_client_profile_when_booking_a_second_time_with_same_phone()
    {
        $this->user->update(['business_id' => $this->business->id]);
        $this->actingAs($this->user, 'sanctum');

        // 1. Forzamos la existencia previa de un cliente en la base de datos
        $existingClient = Client::create([
            'business_id' => $this->business->id,
            'name' => 'María Recurrente',
            'phone' => '+584129999999'
        ]);

        // 2. Agendamos una cita usando exactamente ese mismo teléfono
        $response = $this->postJson('/api/appointments', [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'María Recurrente',
            'client_phone' => '+584129999999',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'total_price' => 30.00
        ]);

        $response->assertStatus(201);

        // No deben existir 2 Marías en la base de datos, solo 1
        $this->assertEquals(1, Client::where('phone', '+584129999999')->count());

        // Verificamos que la cita quedó amarrada al ID del cliente que ya existía originalmente
        $this->assertDatabaseHas('appointments', [
            'id' => $response->json('appointment.id'),
            'client_id' => $existingClient->id
        ]);
    }

    /** @test */
    public function test_it_does_not_share_client_profiles_between_different_businesses()
    {
        // 1. Salón Competidor crea a un cliente con un número específico
        $otherBusiness = Business::factory()->create(['name' => 'Salón Enemigo S.A.']);
        Client::create([
            'business_id' => $otherBusiness->id,
            'name' => 'Cliente Secreto',
            'phone' => '+584128888888'
        ]);

        // 2. Nuestro usuario del salón original intenta agendar con ese mismo número
        $this->user->update(['business_id' => $this->business->id]);
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/appointments', [
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_name' => 'Cliente Compartido',
            'client_phone' => '+584128888888', // El mismo número que el otro salón
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '17:00:00',
            'end_time' => '18:00:00',
            'total_price' => 30.00
        ]);

        $response->assertStatus(201);

        // Deben existir 2 registros con ese número en total (uno para cada negocio)
        $this->assertEquals(2, Client::where('phone', '+584128888888')->count());

        // Nos aseguramos que el cliente creado para NUESTRO negocio tenga nuestro business_id
        $this->assertDatabaseHas('clients', [
            'business_id' => $this->business->id,
            'phone' => '+584128888888',
            'name' => 'Cliente Compartido'
        ]);
    }

    /** @test */
    public function test_it_prevents_booking_if_the_appointment_duration_exceeds_closing_time()
    {
        // 1. Configuramos las horas de atención: abre a las 09:00 y cierra a las 18:00
        $this->business->hours()->update([
            'opening_time' => '09:00:00',
            'closing_time' => '18:00:00',
        ]);

        // 2. Creamos un servicio pesado que dura 120 minutos (2 horas)
        $longService = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Corte Dama + Hidratación Express',
            'price' => 40.00,
            'duration_minutes' => 120
        ]);


        // 3. Intentamos agendar a las 17:00 (Faltando solo 1 hora para el cierre)
        $payload = [
            'business_id' => $this->business->id,
            'service_id'  => $longService->id,
            'date'        => now()->addDay()->format('Y-m-d'),
            'start_time'  => '17:00:00',
            'end_time'    => '19:00:00',
            'client_name' => 'Fabiola Ramos',
            'client_phone'=> '04121112233'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/appointments', $payload);

        // 4. Esperamos que el sistema lo rechace con un 422 de validación
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['start_time']);
    }

    /** @test */
    public function test_it_prevents_modifying_a_completed_appointment()
    {
        $client = Client::create([
            'business_id' => $this->business->id,
            'name' => 'Cliente listo',
            'phone' => '+584128888118'
        ]);

        // 1. Creamos una cita que ya tiene el estado 'completed' en la base de datos
        $completedAppointment = Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'status' => 'completed',
            'date' => now()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'total_price' => 40.00
        ]);

        // 2. Intentamos alterar la hora de la cita a las 14:00:00
        $payload = [
            'business_id' => $this->business->id,
            'service_id'  => $completedAppointment->service_id,
            'date'        => now()->format('Y-m-d'),
            'start_time'  => '14:00:00', // Cambio de hora malicioso o por error
            'client_name' => $completedAppointment->client_name,
            'client_phone'=> $completedAppointment->client_phone,
        ];

        // Golpeamos el endpoint de actualización (ajusta la URL según tus rutas reales, ej: /api/appointments/{id})
        $response = $this->actingAs($this->user, 'sanctum')
                        ->putJson("/api/appointments/{$completedAppointment->id}", $payload);

        // 3. El sistema debe responder con un 403 Forbidden o un 422 indicando que está bloqueada
        $response->assertStatus(422)
                ->assertJsonFragment([
                    'status' => ['No se puede modificar una cita que ya ha sido completada o cobrada.']
                ]);
    }

    /** @test */
    public function test_it_allows_an_owner_to_update_the_status_of_an_independent_appointment_with_no_employee()
    {
        // 1. Creamos un Owner para tu negocio de pruebas
        $owner = User::factory()->create(['role' => 'owner', 'business_id' => $this->business->id]);
        $client = Client::factory()->create(['business_id' => $this->business->id]);

        // Cita sin empleado asignado (Estilista independiente / dueño directo)
        $appointmentOrphan = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id'   => $client->id,
            'status'      => 'pending',
            'date'        => now()->format('Y-m-d'),
            'start_time'  => '12:00:00',
            'end_time'    => '13:00:00'
        ]);

        // 2. El Owner intenta confirmar la cita independiente 
        $response = $this->actingAs($owner, 'sanctum')
                         ->patchJson("/api/appointments/{$appointmentOrphan->id}/status", [
                             'status' => 'confirmed'
                         ]);

        // 3. Debe permitirlo (200 OK) y el estado debe cambiar con éxito
        $response->assertStatus(200);
        $this->assertEquals('confirmed', $appointmentOrphan->fresh()->status);
    }

    /** @test */
    public function test_it_returns_stock_alerts_if_recipe_products_are_out_of_stock()
    {
        // 1. Creamos un producto SIN STOCK (stock_current = 0)
        $productOut = Product::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Tinte Rubio 9.1',
            'stock_current' => 0
        ]);

        // 2. Creamos el servicio y le vinculamos el producto agotado en su receta
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Balayage Premium',
            'duration_minutes' => 60
        ]);
        
        $service->products()->attach($productOut->id, [
            'quantity_default' => 1
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/appointments', [
                'business_id' => $this->business->id,
                'service_id' => $service->id,
                'client_name' => 'María Alejandra',
                'client_phone' => '+584141112233',
                'date' => now()->format('Y-m-d'),
                'start_time' => now()->copy()->addHours(2)->format('H:i:s'),
            ]);

        // Debe responder 201 (Creado)
        $response->assertStatus(201);

        // Debe contener la clave 'alerts' en el JSON y no estar vacía
        $response->assertJsonStructure(['alerts']);
        
        // Verificamos que el mensaje del string mapee que el producto está agotado
        $response->assertJsonFragment([
            'alerts' => [
                "El servicio 'Balayage Premium' requiere 'Tinte Rubio 9.1', pero actualmente no hay existencias en el inventario."
            ]
        ]);

        // Aseguramos que la cita sí se guardó en la base de datos a pesar del quiebre de stock
        $this->assertDatabaseHas('appointments', [
            'business_id' => $this->business->id,
            'service_id' => $service->id,
            'status' => 'pending'
        ]);
    }
}