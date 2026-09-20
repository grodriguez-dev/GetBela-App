<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $owner;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Creamos el entorno base del salón actual
        $this->business = Business::create([
            'name' => 'Salon Bela Test',
            'slug' => 'salon-bela-test',
            'address' => 'Dirección de Prueba',
            'phone' => '+584129944000'
        ]);
        
        // El usuario que opera SIEMPRE es un owner
        $this->owner = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'owner'
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
        ]);
    }

    /** @test */
    public function test_it_creates_a_user_account_when_giving_access_to_an_existing_operative_employee()
    {
        $this->actingAs($this->owner, 'sanctum');

        // Empleado nace solo operativo (user_id => null)
        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Luis Barbero',
            'user_id' => null
        ]);

        // Ejecutamos el update activando el acceso
        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => 'Luis Barbero',
            'specialty' => 'Barbería Avanzada',
            'commission_percentage' => 30.00,
            'is_active' => true,
            'give_access' => true,
            'email' => 'luis@bela.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200);

        // El empleado ahora debe tener un user_id asociado
        $employee->refresh();
        $this->assertNotNull($employee->user_id);

        // El usuario debe existir en la tabla users con el rol correcto
        $this->assertDatabaseHas('users', [
            'id' => $employee->user_id,
            'email' => 'luis@bela.com',
            'role' => 'employee'
        ]);
    }

    /** @test */
    public function test_it_removes_user_account_completely_when_revoking_access()
    {
        $this->actingAs($this->owner, 'sanctum');

        // Empleado nace con acceso de una vez (usando el estado de nuestro factory)
        $employee = Employee::factory()->withUser()->create([
            'business_id' => $this->business->id,
            'name' => 'Ana Manicurista'
        ]);

        $oldUserId = $employee->user_id;

        // Ejecutamos el update apagando el acceso
        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => 'Ana Manicurista',
            'specialty' => 'Manicura',
            'commission_percentage' => 20.00,
            'is_active' => true,
            'give_access' => false
        ]);

        $response->assertStatus(200);

        // El empleado operativo queda libre de credenciales
        $employee->refresh();
        $this->assertNull($employee->user_id);

        // Las credenciales viejas debieron ser eliminadas de la tabla users (no dejamos basura)
        $this->assertDatabaseMissing('users', ['id' => $oldUserId]);
    }

    /** @test */
    public function test_it_updates_only_operative_data_without_touching_user_state()
    {
        $this->actingAs($this->owner, 'sanctum');

        $employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Carlos Estilista',
            'specialty' => 'Corte',
            'user_id' => null
        ]);

        // Cambiamos solo datos de salón, manteniendo give_access en false
        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => 'Carlos Delgado',
            'specialty' => 'Colorimetría',
            'commission_percentage' => 35.00,
            'is_active' => true,
            'give_access' => false
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'Carlos Delgado',
            'specialty' => 'Colorimetría',
            'user_id' => null
        ]);
    }

    /** @test */
    public function test_it_deletes_both_employee_and_user_account_on_cascade_when_destroyed()
    {
        $this->actingAs($this->owner, 'sanctum');

        $employee = Employee::factory()->withUser()->create([
            'business_id' => $this->business->id
        ]);

        $userId = $employee->user_id;

        // Mandamos a eliminar al empleado
        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(200);

        // Se borra el registro operativo y su cuenta de acceso simultáneamente
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    /** @test */
    public function test_it_handles_pending_appointments_safely_when_an_employee_is_deleted()
    {
        $client = Client::factory()->create([
            'business_id' => $this->business->id
        ]);

        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
        ]);

        // 1. Creamos la cita asignada al empleado de la suite ($this->employee)
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $service->id,
            'client_id' => $client->id,
            'status' => 'pending',
            'date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00',
        ]);

        // 2. El dueño elimina al empleado a través del endpoint de destrucción
        // (Ajusta la URL si usas /api/employees/{id})
        $response = $this->actingAs($this->owner, 'sanctum')
                        ->deleteJson("/api/employees/{$this->employee->id}");

        // Esperamos un estatus exitoso (200 o 204) del borrado del empleado
        $response->assertStatus(200);

        // 3. VERIFICACIÓN DE INTEGRIDAD:
        // La cita NO debe desaparecer de la base de datos, pero su 'employee_id' debe pasar a null
        // o el estado debe cambiar según tu regla para que el salón no pierda el registro del cliente.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'employee_id' => null, // 👈 Forzamos a que la cita quede "por asignar" sin romper la BD
        ]);
    }

    /** @test */
    public function test_an_employee_cannot_update_commission_percentages()
    {
        // El empleado que intentará hacer la modificación (Atacante)
        $attackerUser = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);
        Employee::factory()->create([
            'user_id' => $attackerUser->id,
            'business_id' => $this->business->id,
            'commission_percentage' => 30.00,
        ]);

        // El empleado objetivo (Víctima)
        $targetEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'commission_percentage' => 40.00,
        ]);

        // 2. Payload malicioso: Intentar subir la comisión al 80%
        $payload = [
            'commission_percentage' => 80.00,
        ];

        // 3. Petición ejecutada por el empleado atacante hacia el endpoint de actualización
        $response = $this->actingAs($attackerUser, 'sanctum')
                        ->putJson("/api/employees/{$targetEmployee->id}", $payload);

        // 4. El sistema DEBE denegar la acción con un 403 Forbidden
        $response->assertStatus(403);

        // 5. Verificamos que la base de datos se mantiene intacta
        $this->assertDatabaseHas('employees', [
            'id' => $targetEmployee->id,
            'commission_percentage' => 40.00,
        ]);
    }

    /** @test */
    public function test_an_employee_cannot_view_an_employee_profile_from_another_business()
    {
        // 1. SALÓN A (El salón del empleado que hace la consulta)
        $businessA = Business::factory()->create();
        $employeeUserA = User::factory()->create([
            'role' => 'employee',
            'business_id' => $businessA->id,
        ]);

        // 2. SALÓN B (Un salón ajeno completamente)
        $businessB = Business::factory()->create();
        $foreignEmployee = Employee::factory()->create([
            'business_id' => $businessB->id, // 👈 Pertenece al salón B
        ]);

        // 3. El empleado del Salón A intenta consultar el perfil del empleado del Salón B
        $response = $this->actingAs($employeeUserA, 'sanctum')
                        ->getJson("/api/employees/{$foreignEmployee->id}");

        // 4. El sistema debe responder que no existe para él (404) o que está prohibido (403)
        // Usar 404 es una excelente práctica para ni siquiera confirmar que el ID existe en el otro salón.
        $response->assertStatus(403);
    }
}
