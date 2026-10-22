<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    /**
     * Configuración inicial mínima: categorías y super administrador.
     *
     * Es idempotente, por lo que se puede ejecutar varias veces sin duplicar
     * registros. No crea negocios de prueba (eso lo hace DemoDataSeeder).
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@getbela.com'],
            [
                'name' => 'Admin GetBela',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'business_id' => null,
            ]
        );

        // El super admin siempre debe estar fuera de cualquier negocio.
        if ($admin->role !== 'admin' || $admin->business_id !== null) {
            $admin->update([
                'role' => 'admin',
                'business_id' => null,
            ]);
        }
    }
}
