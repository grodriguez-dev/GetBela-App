<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SecurityAndRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $employee;
    private User $customer;
    private Business $business;

    /**
     * Configuración inicial antes de cada test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Creamos un negocio de prueba base
        $this->business = Business::factory()->create();

        // 2. Creamos los tres tipos de usuarios vinculados al negocio
        $this->owner = User::factory()->create([
            'role' => 'owner',
            'business_id' => $this->business->id,
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'business_id' => $this->business->id,
        ]);
    }

    /** @test */
    public function test_an_unauthenticated_user_cannot_access_any_protected_route()
    {
        // Intentar entrar a la ruta de empleados sin token debe responder 401
        $response = $this->getJson('/api/employees');

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function test_an_owner_can_access_owner_exclusive_routes()
    {
        // Actuamos como el Dueño (owner) y entramos al panel financiero
        $response = $this->actingAs($this->owner, 'sanctum')
                         ->getJson('/api/finances/dashboard');

        // Debe permitirle pasar (suponiendo que el controlador responde algo válido, ej: 200 o estructura)
        $response->assertStatus(200);
    }

    /** @test */
    public function test_an_employee_cannot_access_owner_exclusive_routes()
    {
        // Si el empleado intenta meterse a ver las finanzas del negocio...
        $response = $this->actingAs($this->employee, 'sanctum')
                         ->getJson('/api/finances/dashboard');

        // El middleware CheckRole debe rebotarlo con un 403 Forbidden
        $response->assertStatus(403)
                 ->assertJson([
                     'message' => 'Acceso denegado. No tienes los permisos necesarios para esta acción.'
                 ]);
    }

    /** @test */
    public function test_an_employee_can_access_shared_salon_operation_routes()
    {
        // El empleado debe poder consultar la lista de clientes operativos
        $response = $this->actingAs($this->employee, 'sanctum')
                         ->getJson('/api/clients');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_a_customer_cannot_access_salon_operation_routes()
    {
        // Si un cliente común intenta ver la lista de productos/inventario del salón...
        $response = $this->actingAs($this->customer, 'sanctum')
                         ->getJson('/api/products');

        // Debe ser bloqueado inmediatamente
        $response->assertStatus(403);
    }

    /** @test */
    public function test_unauthenticated_users_cannot_access_protected_salon_endpoints()
    {
        // 1. Intentamos golpear el endpoint de citas de vitrina o agenda SIN usar actingAs()
        $response = $this->getJson('/api/appointments');

        // 2. El sistema DEBE responder con un 401 Unauthorized de forma estricta
        $response->assertStatus(401);
    }

    /** @test */
    public function test_it_assigns_the_owner_role_automatically_upon_new_salon_registration()
    {
        // 1. Payload típico de registro de un nuevo dueño de salón
        $payload = [
            'name' => 'Alejandro Rossi',
            'email' => 'alejandro@getbela.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'business_name' => 'Rossi Barber Studio', // Nombre de su salón para crear el tenant
            'business_type' => 'salon',
            'modality' => 'local',
        ];

        // 2. Ejecutamos la petición al endpoint público de registro (ajusta la URL real si difiere)
        $response = $this->postJson('/api/register', $payload);

        // 3. Confirmamos que la cuenta se creó exitosamente (201 Created)
        $response->assertStatus(201);

        // 4. VERIFICACIÓN DE CONTROL:
        // Nos aseguramos de que el usuario exista en la BD y que su rol sea ESTRICTAMENTE 'owner'
        $this->assertDatabaseHas('users', [
            'email' => 'alejandro@getbela.com',
            'role' => 'owner', // Tu backend debe forzar esto internamente
        ]);
    }

    /** @test */
    public function test_only_owner_can_update_business_profile()
    {
        // 1. Caso: Usuario que NO es owner (ej. un empleado o cliente)
        $notOwner = User::factory()->create(['role' => 'employee']); 
        $this->actingAs($notOwner);

        $response = $this->postJson('/api/business/update', [
            'name' => 'Intento ilegal'
        ]);
        $response->assertStatus(403); // Forbidden

        // 2. Caso: Usuario que SÍ es owner
        $owner = User::factory()->create(['business_id' => $this->business->id,'role' => 'owner']);
        $this->actingAs($owner);

        $response = $this->postJson('/api/business/update', [
            'name' => 'Nombre del Salón Actualizado',
            'phone' => '+584223332655',
            'address' => 'c/ Av. prueva',
        ]);
        
        $response->assertStatus(200);
        $this->assertDatabaseHas('businesses', [
            'id' => $owner->business->id,
            'name' => 'Nombre del Salón Actualizado'
        ]);
    }
}