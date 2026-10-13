<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\BlockTime;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $business = Business::firstOrCreate(
            ['slug' => Str::slug('Bela Studio Salon')],
            [
                'name' => 'Bela Studio Salon',
                'address' => 'Av. Principal Lechería, CC Plaza',
                'phone' => '+584120000000',
            ]
        );

        $this->createBusinessHours($business);

        User::firstOrCreate(
            ['email' => 'admin@getbela.com'],
            [
                'business_id' => $business->id,
                'name' => 'Carlos Administrador',
                'role' => 'admin',
                'password' => Hash::make('password123'),
            ]
        );

        $employeeMaria = Employee::factory()->create([
            'business_id' => $business->id,
            'name' => 'María Estilista',
            'user_id' => null,
            'specialty' => 'Corte y Secado',
            'commission_percentage' => 40.00,
        ]);

        $employeeLucia = Employee::factory()->create([
            'business_id' => $business->id,
            'name' => 'Lucía Manicurista',
            'user_id' => null,
            'specialty' => 'Sistemas de Uñas',
            'commission_percentage' => 30.00,
        ]);

        $employeeKarla = Employee::factory()->withUser()->create([
            'business_id' => $business->id,
            'name' => 'Karla Estilista',
            'specialty' => 'Color y brillo',
            'commission_percentage' => 35.00,
        ]);

        $shampoo = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Shampoo Kerastase Hidratante',
            'stock_current' => 15,
            'stock_min' => 5,
            'sale_price' => 35.00,
            'type' => 'venta_publico',
        ]);

        $tinte = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Tinte Tubo Igora Royal',
            'stock_current' => 20,
            'stock_min' => 5,
            'sale_price' => 15.00,
            'type' => 'uso_interno',
        ]);

        $aceite = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Aceite de Argán Premium',
            'stock_current' => 8,
            'stock_min' => 3,
            'sale_price' => 50.00,
            'type' => 'venta_publico',
        ]);

        $categoryMap = Category::query()->pluck('id', 'name');

        $servicioCorte = Service::create([
            'business_id' => $business->id,
            'category_id' => $categoryMap->get('Cabello') ?? $categoryMap->first(),
            'employee_id' => $employeeMaria->id,
            'name' => 'Corte Dama + Hidratación Express',
            'description' => 'Corte moderno con hidratación y acabado profesional.',
            'duration_minutes' => 60,
            'price' => 40.00,
            'is_active' => true,
        ]);

        $servicioColor = Service::create([
            'business_id' => $business->id,
            'category_id' => $categoryMap->get('Cabello') ?? $categoryMap->first(),
            'employee_id' => $employeeKarla->id,
            'name' => 'Balayage Premium',
            'description' => 'Coloración personalizada en balayage con cuidado intensivo.',
            'duration_minutes' => 180,
            'price' => 120.00,
            'is_active' => true,
        ]);

        $servicioManicura = Service::create([
            'business_id' => $business->id,
            'category_id' => $categoryMap->get('Uñas') ?? $categoryMap->first(),
            'employee_id' => $employeeLucia->id,
            'name' => 'Manicura Spa Premium',
            'description' => 'Limpieza, esmaltado y cuidado de cutículas.',
            'duration_minutes' => 90,
            'price' => 55.00,
            'is_active' => true,
        ]);

        $servicioCorte->products()->sync([
            $shampoo->id => ['quantity_default' => 1],
        ]);

        $servicioColor->products()->sync([
            $tinte->id => ['quantity_default' => 2],
            $shampoo->id => ['quantity_default' => 1],
        ]);

        $clients = Client::factory()->count(6)->create([
            'business_id' => $business->id,
        ]);

        Appointment::create([
            'business_id' => $business->id,
            'service_id' => $servicioCorte->id,
            'client_id' => $clients[0]->id,
            'date' => Carbon::now()->subDays(4)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'completed',
            'total_price' => 40.00,
        ]);

        Appointment::create([
            'business_id' => $business->id,
            'service_id' => $servicioManicura->id,
            'client_id' => $clients[1]->id,
            'date' => Carbon::now()->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'status' => 'completed',
            'total_price' => 55.00,
        ]);

        Appointment::create([
            'business_id' => $business->id,
            'service_id' => $servicioColor->id,
            'client_id' => $clients[2]->id,
            'date' => Carbon::now()->addDay()->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
            'status' => 'confirmed',
            'total_price' => 120.00,
        ]);

        Appointment::create([
            'business_id' => $business->id,
            'service_id' => $servicioCorte->id,
            'client_id' => $clients[3]->id,
            'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'start_time' => '11:30:00',
            'end_time' => '12:30:00',
            'status' => 'pending',
            'total_price' => 40.00,
        ]);

        Sale::create([
            'business_id' => $business->id,
            'product_id' => $aceite->id,
            'quantity' => 1,
            'unit_price' => 50.00,
            'total_price' => 50.00,
        ]);

        Sale::create([
            'business_id' => $business->id,
            'product_id' => $shampoo->id,
            'quantity' => 2,
            'unit_price' => 35.00,
            'total_price' => 70.00,
        ]);

        BlockTime::create([
            'business_id' => $business->id,
            'service_id' => $servicioCorte->id,
            'date' => Carbon::now()->format('Y-m-d'),
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'reason' => 'Hora de Almuerzo Oficial',
        ]);
    }

    protected function createBusinessHours(Business $business): void
    {
        foreach (range(1, 6) as $day) {
            BusinessHour::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'day_of_week' => $day,
                ],
                [
                    'opening_time' => '08:00:00',
                    'closing_time' => '19:00:00',
                    'is_closed' => false,
                ]
            );
        }

        BusinessHour::updateOrCreate(
            [
                'business_id' => $business->id,
                'day_of_week' => 7,
            ],
            [
                'opening_time' => '08:00:00',
                'closing_time' => '17:00:00',
                'is_closed' => true,
            ]
        );
    }
}
