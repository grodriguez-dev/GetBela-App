<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Business;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InitialSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. CREAR AL SUPER ADMINISTRADOR (TÚ)
        User::create([
            'name' => 'Admin GetBela',
            'email' => 'admin@getbela.com',
            'password' => Hash::make('admin123'), // Cambia esto en producción
            'role' => 'super_admin',
            'business_id' => null, // El súper admin no pertenece a ningún salón
        ]);

        // 2. CREAR UN NEGOCIO DE PRUEBA (Salón de Estética)
        $business = Business::create([
            'name' => 'Bella Glow Studio',
            'slug' => Str::slug('Bella Glow Studio') . '-' . rand(100, 999),
            'type' => 'salon',
            'modality' => 'hibrido',
            'phone' => '+584120000000',
            'address' => 'Av. Principal, Local 4, Guanta',
            'description' => 'Especialistas en cejas, pestañas y sistemas de uñas perfectas.',
        ]);

        // 3. CREAR EL USUARIO DUEÑO DE ESE NEGOCIO
        User::create([
            'name' => 'Valentina Silva',
            'email' => 'valentina@bellaglow.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'business_id' => $business->id, // Amarrado a Bella Glow Studio
        ]);

        // 4. CREAR SERVICIOS INICIALES PARA ESTE NEGOCIO
        Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Manicura Rusa + Gel',
            'description' => 'Limpieza profunda de cutículas con torno y esmaltado permanente.',
            'duration_minutes' => 60,
            'price' => 20.00,
        ]);

        Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Lifting de Pestañas',
            'description' => 'Curvatura natural de tus pestañas con tratamiento de queratina incluido.',
            'duration_minutes' => 45,
            'price' => 15.00,
        ]);

        Service::factory()->create([
            'business_id' => $business->id,
            'name' => 'Diseño y Depilación de Cejas',
            'description' => 'Perfilado personalizado según tu tipo de rostro y depilación con cera elástica.',
            'duration_minutes' => 30,
            'price' => 8.00,
        ]);

        for ($i = 0; $i <= 6; $i++) {
            \App\Models\BusinessHour::create([
                'business_id' => $business->id,
                'day_of_week' => $i,
                'opening_time' => '08:00:00',
                'closing_time' => '17:00:00',
                'is_closed' => ($i === 0),
            ]);
        }
    }
}