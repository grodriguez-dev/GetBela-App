<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use App\Models\Business;
use App\Models\Service;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $user;
    protected $service;
    protected $employee;

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

        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
            'email' => 'finance-tester@getbela.com'
        ]);

        $this->employee = Employee::create([
            'business_id' => $this->business->id,
            'name' => 'Juana Pérez',
            'commission_percentage' => 40.00
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Manicura Rusa',
            'employee_id' => $this->employee->id,
            'price' => 50.00
        ]);

    }

    /** @test */
    public function test_it_automatically_reflects_earnings_in_finance_dashboard_when_an_appointment_is_completed()
    {
        $this->actingAs($this->user, 'sanctum');

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // 1. Creamos la cita enlazada al negocio correcto
        $appointment = Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => now()->format('Y-m-d'), 
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'status' => 'confirmed',
            'total_price' => 50.00
        ]);

        // 2. Pasamos a completada (Ruta PATCH)
        $responseStatus = $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'completed'
        ]);
        
        $responseStatus->assertStatus(200);

        // 3. Consultamos finanzas (Ruta GET)
        $responseFinance = $this->getJson("/api/finances/dashboard?period=today");
        $responseFinance->assertStatus(200);

        // --- ASERCIONES ---
        $responseFinance->assertJsonFragment([
            'revenue_from_services' => 50,
            'total_gross_combined' => 50
        ]);
    }

    /** @test */
    public function test_it_removes_appointment_from_projected_earnings_when_status_is_no_show()
    {
        $this->actingAs($this->user, 'sanctum');

        // Sincronizamos el tiempo para las finanzas
        $this->travelTo(Carbon::parse('2026-07-03 12:00:00'));

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // 1. Creamos una cita 'confirmed' de 40.00 (Debería entrar en proyecciones inicialmente)
        $appointment = Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => '2026-07-03',
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'status' => 'confirmed',
            'total_price' => 40.00
        ]);

        // 2. La clienta no llegó, el administrador la marca como 'no_show'
        $responseStatus = $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'no_show'
        ]);
        $responseStatus->assertStatus(200);

        // 3. Consultamos el dashboard financiero para ver el impacto
        $responseFinance = $this->getJson("/api/finances/dashboard?period=today");
        $responseFinance->assertStatus(200);

        // 'total_projected_services' debe ser 0 (ya no está pendiente ni confirmada)
        // 'revenue_from_services' debe ser 0 (no se completó)
        $responseFinance->assertJsonFragment([
            'revenue_from_services' => 0,
            'total_projected_services' => 0,
            'total_gross_combined' => 0
        ]);
    }

    /** @test */
    public function test_it_correctly_calculates_staff_performance_and_commissions_on_dashboard()
    {
        $this->actingAs($this->user, 'sanctum');

        // Congelamos el tiempo
        $this->travelTo(Carbon::parse('2026-07-03 12:00:00'));

        // 1. Forzamos a que nuestro empleado del test tenga una comisión del 40%
        $this->employee->update([
            'name' => 'Juana Pérez',
            'commission_percentage' => 40.00
        ]);

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // 2. Creamos una cita 'completed' asignada a Juana por un valor de 100.00
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => '2026-07-03',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'total_price' => 100.00 // Matemática simple: 100 bruto -> 40 para Juana -> 60 para el salón
        ]);

        // 3. Consultamos el dashboard financiero
        $responseFinance = $this->getJson("/api/finances/dashboard?period=today");
        $responseFinance->assertStatus(200);

        // Validamos la estructura y la matemática del Staff
        $responseFinance->assertJsonFragment([
            'employee_name' => 'Juana Pérez',
            'commission_rate' => 40,
            'total_appointments' => 1,
            'gross_generated' => 100,
            'employee_payout' => 40,   // El 40% de 100
            'salon_net_profit' => 60   // 100 - 40
        ]);
    }

    /** @test */
    public function test_it_correctly_calculates_earnings_for_the_whole_month_period()
    {
        $this->actingAs($this->user, 'sanctum');

        // Viajamos al 15 de Julio de 2026
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Cita 1: Hoy (15 de Julio) -> 50.00
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => '2026-07-15',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'total_price' => 50.00
        ]);

        $client2 = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Cita 2: Hace una semana (8 de Julio, sigue siendo el mismo mes) -> 30.00
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client2->id,
            'date' => '2026-07-08',
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'status' => 'completed',
            'total_price' => 30.00
        ]);

        // Consultamos el período 'month'
        $responseFinance = $this->getJson("/api/finances/dashboard?period=month");
        $responseFinance->assertStatus(200);

        // DEBE SUMAR AMBAS CITAS (50 + 30 = 80)
        $responseFinance->assertJsonFragment([
            'revenue_from_services' => 80,
            'total_gross_combined' => 80
        ]);
    }

    /** @test */
    public function test_it_correctly_calculates_earnings_for_the_whole_year_period()
    {
        $this->actingAs($this->user, 'sanctum');

        // Viajamos a Diciembre de 2026
        $this->travelTo(Carbon::parse('2026-12-01 12:00:00'));

        $client = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Cita 1: En Diciembre -> 100.00
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => '2026-12-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'total_price' => 100.00
        ]);

        $client2 = Client::factory()->create([
            'business_id' => $this->business->id,
        ]);


        // Cita 2: En Enero de este mismo año (2026-01-15) -> 200.00
        Appointment::create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'date' => '2026-01-15',
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'status' => 'completed',
            'total_price' => 200.00
        ]);

        // Consultamos el período 'year'
        $responseFinance = $this->getJson("/api/finances/dashboard?period=year");
        $responseFinance->assertStatus(200);

        // DEBE SUMAR AMBAS CITAS DEL AÑO (100 + 200 = 300)
        $responseFinance->assertJsonFragment([
            'revenue_from_services' => 300,
            'total_gross_combined' => 300
        ]);
    }

}