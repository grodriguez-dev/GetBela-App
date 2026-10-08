<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\BusinessHour;
use App\Models\Business;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Service;
use App\Models\Product;
use App\Models\Appointment;
use App\Models\Sale;
use App\Models\BlockTime;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{   
    use WithoutModelEvents;
    
    /**
     * Seed the application's database.
     */
    public function run(): void
    {   
        $this->call([
            CategorySeeder::class,
        ]);
        /**
        *$this->call([
        *    InitialSetupSeeder::class,
        *]);
        */
        // 1. CREAR EL NEGOCIO DE PRUEBA (Multi-tenant principal)
        $business = Business::create([
            'name' => 'Bela Studio Salon',
            'slug' => Str::slug('Bela Studio Salon'),
            'address' => 'Av. Principal Lechería, CC Plaza',
            'phone' => '+584120000000'
        ]);

        // Configurar Horario Comercial (Lunes a Sábado Abierto de 08:00 a 19:00)
        for ($i = 1; $i <= 6; $i++) {
            BusinessHour::create([
                'business_id' => $business->id,
                'day_of_week' => $i, // 1 = Lunes, 2 = Martes... 6 = Sábado
                'opening_time' => '08:00:00',
                'closing_time' => '19:00:00',
                'is_closed' => false
            ]);
        }
        
        // Domingo Cerrado
        BusinessHour::create([
            'business_id' => $business->id,
            'day_of_week' => 7,
            'opening_time' => '08:00:00',
            'closing_time' => '17:00:00',
            'is_closed' => true
        ]);

        // CREAR EL USUARIO
        $admin = User::create([
            'business_id' => $business->id,
            'name' => 'Carlos Administrador',
            'email' => 'admin@getbela.com',
            'role' => 'admin',
            'password' => Hash::make('password123'), // Contraseña fácil para pruebas
        ]);

        // 3. CREAR EMPLEADOS (STAFF)
        $employee1 = Employee::factory()->create([
            'business_id' => $business->id,
            'name' => 'María Estilista',
            'user_id' => null,
            'specialty' => 'Corte y Secado',
            'commission_percentage' => 40.00 // 40% de comisión
        ]);

        $employee2 = Employee::factory()->create([
            'business_id' => $business->id,
            'name' => 'Lucía Manicurista',
            'user_id' => null,
            'specialty' => 'Sistemas de Uñas',
            'commission_percentage' => 30.00 // 30% de comisión
        ]);

        // Creamos 1 empleado con acceso al sistema
        $employee3 = Employee::factory()->withUser()->create([
            'business_id' => $business->id,
            'name' => 'Karla Estilista',
        ]);

        // 4. CREAR PRODUCTOS (Inventario) - Nombres de columnas corregidos
        $shampoo = Product::factory()->create([
            'business_id' => $business->id, 
            'name' => 'Shampoo Kerastase Hidratante', 
            'stock_current' => 15, 
            'sale_price' => 35.00,
            'type' => 'venta_publico'
        ]);

        $tinte = Product::factory()->create([
            'business_id' => $business->id, 
            'name' => 'Tinte Tubo Igora Royal', 
            'stock_current' => 20, 
            'sale_price' => 15.00,
            'type' => 'uso_interno'
        ]);

        $aceite = Product::factory()->create([
            'business_id' => $business->id, 
            'name' => 'Aceite de Argán Premium', 
            'stock_current' => 8, 
            'sale_price' => 50.00,
            'type' => 'venta_publico'
        ]);

        // 5. CREAR SERVICIOS
        $servicioCorte = Service::create([
            'business_id' => $business->id,
            'category_id' => 1,
            'employee_id' => $employee1->id,
            'name' => 'Corte Dama + Hidratación Express',
            'price' => 40.00,
            'duration_minutes' => 60
        ]);

        $servicioColor = Service::create([
            'business_id' => $business->id,
            'category_id' => 2,
            'employee_id' => $employee2->id,
            'name' => 'Balayage Premium',
            'price' => 120.00,
            'duration_minutes' => 180
        ]);

        $servicio3 = Service::create([
            'business_id' => $business->id,
            'category_id' => 3,
            'employee_id' => $employee3->id,
            'name' => 'De Prueba',
            'price' => 10.00,
            'duration_minutes' => 80
        ]);

        $client1 = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        $client2 = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        $client3 = Client::factory()->create([
            'business_id' => $business->id,
        ]);

        // Asociar Insumos a los Servicios (Receta Base)
        // El corte gasta 1 unidad de Shampoo
        $servicioCorte->products()->attach($shampoo->id, ['quantity_default' => 1]);
        // El Balayage gasta 2 tintes y 1 shampoo
        $servicioColor->products()->attach($tinte->id, ['quantity_default' => 2]);
        $servicioColor->products()->attach($shampoo->id, ['quantity_default' => 1]);


        // 6. HISTORIAL DE CITAS (Para alimentar las finanzas)
        // Cita 1: Completada la semana pasada (María)
        Appointment::create([
            'business_id' => $business->id,
            'service_id' => $servicioCorte->id,
            'client_id' => $client1->id,
            'date' => Carbon::now()->subDays(4)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'total_price' => 40.00
        ]);

        // Cita 2: Completada hoy en la mañana (Lucía)
        Appointment::create([
            'business_id' => $business->id,
            'service_id' => $servicioCorte->id,
            'client_id' => $client2->id,
            'date' => Carbon::now()->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'completed',
            'total_price' => 40.00
        ]);

        // Cita 3: Proyectada (Pendiente para mañana)
        Appointment::create([
            'business_id' => $business->id,
            'service_id' => $servicio3->id,
            'client_id' => $client3->id,
            'date' => Carbon::now()->addDay()->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
            'status' => 'confirmed',
            'total_price' => 120.00
        ]);


        // 7. VENTAS DE VITRINA DIRECTAS (Ingresos por Producto)
        // Venta de 1 aceite de argán hoy
        Sale::create([
            'business_id' => $business->id,
            'product_id' => $aceite->id,
            'quantity' => 1,
            'unit_price' => 30.00,
            'total_price' => 50.00,
        ]);

        // Venta de 2 shampoos ayer
        Sale::create([
            'business_id' => $business->id,
            'product_id' => $shampoo->id,
            'quantity' => 2,
            'unit_price' => 40.00,
            'total_price' => 70.00,
        ]);


        // 8. BLOQUEOS DE AGENDA (Prueba de horas muertas)
        // Almuerzo de María hoy
        BlockTime::create([
            'business_id' => $business->id,
            'service_id' => $servicio3->id,
            'date' => Carbon::now()->format('Y-m-d'),
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'reason' => 'Hora de Almuerzo Oficial'
        ]);
    }
}
