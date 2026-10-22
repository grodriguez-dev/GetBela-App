<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // DemoDataSeeder garantiza las categorías, el super admin y los
        // negocios de prueba con sus dueños, empleados y servicios.
        $this->call(DemoDataSeeder::class);
    }
}
