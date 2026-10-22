<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Contraseña común para todos los usuarios de prueba.
     */
    private const PASSWORD = 'password123';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $this->ensureAdmin();
        $this->removeLegacyAdminBusiness();

        foreach ($this->testBusinesses() as $index => $data) {
            $this->createBusinessWithData($data, $index + 1);
        }

        $this->command?->info('Datos de prueba creados.');
        $this->command?->info('Dueños: dueno1@getbela.com ... dueno5@getbela.com / password123');
        $this->command?->info('Empleados con acceso: empleado1@getbela.com ... empleado5@getbela.com / password123');
    }

    /**
     * Garantiza que el super admin exista y esté fuera de cualquier negocio.
     */
    private function ensureAdmin(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@getbela.com'],
            [
                'name' => 'Admin GetBela',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'admin',
                'business_id' => null,
            ]
        );

        if ($admin->role !== 'admin' || $admin->business_id !== null) {
            $admin->update([
                'role' => 'admin',
                'business_id' => null,
            ]);
        }
    }

    /**
     * Elimina el negocio de prueba que pertenecía al admin (Bela Studio Salon)
     * junto con sus servicios y empleados (eliminación en cascada).
     */
    private function removeLegacyAdminBusiness(): void
    {
        Business::query()
            ->where('slug', Str::slug('Bela Studio Salon'))
            ->orWhere('name', 'Bela Studio Salon')
            ->get()
            ->each
            ->delete();
    }

    /**
     * Crea (o recrea) un negocio de prueba con su dueño, empleados, servicios,
     * productos, clientes y citas.
     */
    private function createBusinessWithData(array $data, int $n): void
    {
        $ownerEmail = "dueno{$n}@getbela.com";
        $employeeEmail = "empleado{$n}@getbela.com";

        // Idempotencia: al borrar el negocio se eliminan en cascada sus
        // empleados, servicios, citas, clientes, productos y usuarios.
        Business::query()->where('slug', $data['slug'])->get()->each->delete();
        User::query()->whereIn('email', [$ownerEmail, $employeeEmail])->delete();

        $business = Business::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'modality' => $data['modality'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'description' => $data['description'],
            'is_active' => true,
        ]);

        $this->createBusinessHours($business);

        User::create([
            'name' => $data['owner_name'],
            'email' => $ownerEmail,
            'password' => Hash::make(self::PASSWORD),
            'role' => 'owner',
            'business_id' => $business->id,
        ]);

        $employees = $this->createEmployees($business, $data['employees'], $employeeEmail, $n);
        $services = $this->createServices($business, $data['services'], $employees);

        $this->createProducts($business);
        $clients = $this->createClients($business);
        $this->createAppointments($business, $services, $clients);
    }

    /**
     * Horario de atención: lunes a sábado 8:00 a 19:00, domingo cerrado.
     */
    private function createBusinessHours(Business $business): void
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

    /**
     * Crea los empleados. Solo el primero tiene acceso a la plataforma.
     *
     * @return array<int, Employee>
     */
    private function createEmployees(Business $business, array $employees, string $employeeEmail, int $n): array
    {
        $models = [];

        foreach ($employees as $i => $employee) {
            $userId = null;

            if ($i === 0) {
                $user = User::create([
                    'name' => $employee['name'],
                    'email' => $employeeEmail,
                    'password' => Hash::make(self::PASSWORD),
                    'role' => 'employee',
                    'business_id' => $business->id,
                ]);

                $userId = $user->id;
            }

            $models[] = Employee::create([
                'business_id' => $business->id,
                'user_id' => $userId,
                'name' => $employee['name'],
                'specialty' => $employee['specialty'],
                'phone' => '+58414' . sprintf('%07d', ($n * 100) + $i),
                'commission_percentage' => $employee['commission'],
                'is_active' => true,
            ]);
        }

        return $models;
    }

    /**
     * Crea los servicios y los reparte entre los empleados del negocio.
     *
     * @param  array<int, Employee>  $employees
     * @return array<int, Service>
     */
    private function createServices(Business $business, array $services, array $employees): array
    {
        $categoryIds = Category::query()->pluck('id', 'name');
        $fallbackCategoryId = $categoryIds->first();

        $models = [];

        foreach ($services as $i => $service) {
            $employee = $employees[$i % count($employees)];

            $models[] = Service::create([
                'business_id' => $business->id,
                'category_id' => $categoryIds->get($service['category']) ?? $fallbackCategoryId,
                'employee_id' => $employee->id,
                'name' => $service['name'],
                'description' => $service['description'] ?? 'Servicio profesional de ' . $business->name . '.',
                'duration_minutes' => $service['duration'],
                'price' => $service['price'],
                'is_active' => true,
            ]);
        }

        return $models;
    }

    /**
     * Productos básicos para poder probar inventario y recetas.
     */
    private function createProducts(Business $business): void
    {
        $catalog = [
            ['name' => 'Shampoo Hidratante Profesional', 'brand' => 'Kerastase', 'purchase_price' => 12, 'sale_price' => 25, 'type' => 'venta_publico'],
            ['name' => 'Tinte Profesional', 'brand' => 'Schwarzkopf', 'purchase_price' => 6, 'sale_price' => 15, 'type' => 'uso_interno'],
            ['name' => 'Aceite de Argán', 'brand' => 'Moroccanoil', 'purchase_price' => 18, 'sale_price' => 40, 'type' => 'venta_publico'],
        ];

        foreach ($catalog as $product) {
            Product::create([
                'business_id' => $business->id,
                'name' => $product['name'],
                'brand' => $product['brand'],
                'description' => 'Producto de prueba para el inventario.',
                'stock_current' => 20,
                'stock_min' => 5,
                'purchase_price' => $product['purchase_price'],
                'sale_price' => $product['sale_price'],
                'type' => $product['type'],
            ]);
        }
    }

    /**
     * @return array<int, Client>
     */
    private function createClients(Business $business): array
    {
        $names = [
            'Ana Gómez',
            'Luis Ramírez',
            'María Torres',
            'Pedro Salazar',
            'Gabriela Rondón',
            'José Contreras',
            'Daniela Marcano',
            'Carlos Núñez',
        ];

        $clients = [];

        foreach ($names as $i => $name) {
            $clients[] = Client::create([
                'business_id' => $business->id,
                'name' => $name,
                'phone' => '+58424' . sprintf('%07d', ($business->id * 100) + $i),
            ]);
        }

        return $clients;
    }

    /**
     * Crea citas completadas (hoy, esta semana y este mes) y algunas futuras.
     *
     * @param  array<int, Service>  $services
     * @param  array<int, Client>  $clients
     */
    private function createAppointments(Business $business, array $services, array $clients): void
    {
        if (empty($services) || empty($clients)) {
            return;
        }

        $today = Carbon::today();

        $completedDates = [
            $today,
            $today,
            $today->copy()->startOfWeek(),
            $today->copy()->startOfWeek(),
            $today->copy()->startOfMonth(),
            $today->copy()->startOfMonth(),
        ];

        $startTimes = ['08:00:00', '09:00:00', '10:00:00', '11:00:00', '08:30:00', '09:30:00'];

        foreach ($completedDates as $i => $date) {
            $service = $services[$i % count($services)];
            $client = $clients[$i % count($clients)];
            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $startTimes[$i]);
            $end = $start->copy()->addMinutes((int) $service->duration_minutes);

            Appointment::create([
                'business_id' => $business->id,
                'service_id' => $service->id,
                'employee_id' => $service->employee_id,
                'client_id' => $client->id,
                'date' => $date->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'status' => 'completed',
                'total_price' => $service->price,
                'notes' => 'Cita de prueba generada por el seeder.',
            ]);
        }

        foreach (['confirmed', 'pending'] as $i => $status) {
            $date = $today->copy()->addDays($i + 1);
            $service = $services[($i + 2) % count($services)];
            $client = $clients[($i + 2) % count($clients)];
            $start = Carbon::parse($date->format('Y-m-d') . ' ' . ($i === 0 ? '10:00:00' : '15:00:00'));
            $end = $start->copy()->addMinutes((int) $service->duration_minutes);

            Appointment::create([
                'business_id' => $business->id,
                'service_id' => $service->id,
                'employee_id' => $service->employee_id,
                'client_id' => $client->id,
                'date' => $date->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'status' => $status,
                'total_price' => $service->price,
                'notes' => 'Cita futura de prueba generada por el seeder.',
            ]);
        }
    }

    /**
     * Definición de los 5 negocios de prueba.
     *
     * @return array<int, array<string, mixed>>
     */
    private function testBusinesses(): array
    {
        return [
            [
                'name' => 'Bella Glow Studio',
                'slug' => 'bella-glow-studio',
                'type' => 'salon',
                'modality' => 'local',
                'phone' => '+584121000001',
                'address' => 'Av. Principal, Local 4, Lechería',
                'description' => 'Especialistas en cejas, pestañas y uñas.',
                'owner_name' => 'Valentina Silva',
                'employees' => [
                    ['name' => 'María Fernanda Rojas', 'specialty' => 'Cejas y Pestañas', 'commission' => 45],
                    ['name' => 'Lucía Méndez', 'specialty' => 'Uñas', 'commission' => 40],
                    ['name' => 'Karla Pérez', 'specialty' => 'Maquillaje', 'commission' => 35],
                ],
                'services' => [
                    ['name' => 'Lifting de Pestañas', 'category' => 'Cejas y Pestañas', 'price' => 15, 'duration' => 45],
                    ['name' => 'Extensión de Pestañas Clásicas', 'category' => 'Cejas y Pestañas', 'price' => 30, 'duration' => 90],
                    ['name' => 'Diseño y Depilación de Cejas', 'category' => 'Cejas y Pestañas', 'price' => 8, 'duration' => 30],
                    ['name' => 'Manicura Rusa + Gel', 'category' => 'Uñas', 'price' => 20, 'duration' => 60],
                    ['name' => 'Pedicura Spa', 'category' => 'Uñas', 'price' => 22, 'duration' => 60],
                    ['name' => 'Sistema de Uñas Acrílicas', 'category' => 'Uñas', 'price' => 35, 'duration' => 90],
                    ['name' => 'Maquillaje Social', 'category' => 'Maquillaje', 'price' => 25, 'duration' => 60],
                    ['name' => 'Depilación con Cera', 'category' => 'Depilación', 'price' => 10, 'duration' => 30],
                ],
            ],
            [
                'name' => 'Estilo & Color',
                'slug' => 'estilo-color',
                'type' => 'salon',
                'modality' => 'local',
                'phone' => '+584121000002',
                'address' => 'Calle 5, CC Plaza, Puerto La Cruz',
                'description' => 'Cortes, color y tratamientos capilares.',
                'owner_name' => 'Andrea Pérez',
                'employees' => [
                    ['name' => 'Carlos Rivas', 'specialty' => 'Colorista', 'commission' => 45],
                    ['name' => 'Daniela Torres', 'specialty' => 'Estilista', 'commission' => 40],
                    ['name' => 'José Manuel Suárez', 'specialty' => 'Barbero', 'commission' => 35],
                ],
                'services' => [
                    ['name' => 'Corte Dama', 'category' => 'Cabello', 'price' => 15, 'duration' => 45],
                    ['name' => 'Corte Caballero', 'category' => 'Cabello', 'price' => 10, 'duration' => 30],
                    ['name' => 'Balayage Premium', 'category' => 'Cabello', 'price' => 120, 'duration' => 180],
                    ['name' => 'Tinte Completo', 'category' => 'Cabello', 'price' => 70, 'duration' => 120],
                    ['name' => 'Hidratación Profunda', 'category' => 'Cabello', 'price' => 35, 'duration' => 60],
                    ['name' => 'Secado y Planchado', 'category' => 'Cabello', 'price' => 18, 'duration' => 45],
                    ['name' => 'Keratina', 'category' => 'Cabello', 'price' => 90, 'duration' => 150],
                    ['name' => 'Peinado de Fiesta', 'category' => 'Cabello', 'price' => 30, 'duration' => 60],
                ],
            ],
            [
                'name' => 'Nails & Spa Elegance',
                'slug' => 'nails-spa-elegance',
                'type' => 'salon',
                'modality' => 'local',
                'phone' => '+584121000003',
                'address' => 'Av. Bolívar, Local 12, Barcelona',
                'description' => 'Manicura, pedicura y nail art.',
                'owner_name' => 'Camila Rodríguez',
                'employees' => [
                    ['name' => 'Sofía Luna', 'specialty' => 'Manicurista', 'commission' => 45],
                    ['name' => 'Valeria Cruz', 'specialty' => 'Pedicurista', 'commission' => 40],
                    ['name' => 'Paola Díaz', 'specialty' => 'Nail Artist', 'commission' => 35],
                ],
                'services' => [
                    ['name' => 'Manicura Clásica', 'category' => 'Uñas', 'price' => 12, 'duration' => 45],
                    ['name' => 'Manicura Spa', 'category' => 'Uñas', 'price' => 18, 'duration' => 60],
                    ['name' => 'Uñas Acrílicas', 'category' => 'Uñas', 'price' => 35, 'duration' => 90],
                    ['name' => 'Uñas en Gel', 'category' => 'Uñas', 'price' => 30, 'duration' => 75],
                    ['name' => 'Pedicura Spa', 'category' => 'Uñas', 'price' => 22, 'duration' => 60],
                    ['name' => 'Pedicura Clásica', 'category' => 'Uñas', 'price' => 15, 'duration' => 45],
                    ['name' => 'Diseño de Uñas', 'category' => 'Uñas', 'price' => 10, 'duration' => 30],
                    ['name' => 'Retiro de Gel', 'category' => 'Uñas', 'price' => 8, 'duration' => 30],
                ],
            ],
            [
                'name' => 'Barbería Don Daniel',
                'slug' => 'barberia-don-daniel',
                'type' => 'independent',
                'modality' => 'local',
                'phone' => '+584121000004',
                'address' => 'Calle Sucre, Local 3, Guanta',
                'description' => 'Cortes clásicos, barba y afeitado premium.',
                'owner_name' => 'Daniel Martínez',
                'employees' => [
                    ['name' => 'Miguel Ángel Rojas', 'specialty' => 'Barbero', 'commission' => 45],
                    ['name' => 'Luis Fernando Gómez', 'specialty' => 'Barbero', 'commission' => 40],
                    ['name' => 'Andrés Soto', 'specialty' => 'Barbero', 'commission' => 35],
                ],
                'services' => [
                    ['name' => 'Corte Clásico', 'category' => 'Cabello', 'price' => 10, 'duration' => 30],
                    ['name' => 'Corte + Barba', 'category' => 'Cabello', 'price' => 15, 'duration' => 45],
                    ['name' => 'Afeitado Premium', 'category' => 'Depilación', 'price' => 12, 'duration' => 30],
                    ['name' => 'Perfilado de Barba', 'category' => 'Cabello', 'price' => 8, 'duration' => 20],
                    ['name' => 'Tinte de Barba', 'category' => 'Cabello', 'price' => 12, 'duration' => 30],
                    ['name' => 'Corte Niño', 'category' => 'Cabello', 'price' => 8, 'duration' => 30],
                    ['name' => 'Tratamiento Capilar', 'category' => 'Cabello', 'price' => 20, 'duration' => 45],
                    ['name' => 'Ritual de Toalla Caliente', 'category' => 'Tratamientos Faciales y Corporales', 'price' => 15, 'duration' => 30],
                ],
            ],
            [
                'name' => 'Glam Studio',
                'slug' => 'glam-studio',
                'type' => 'salon',
                'modality' => 'hibrido',
                'phone' => '+584121000005',
                'address' => 'Av. Universidad, Local 8, Lechería',
                'description' => 'Maquillaje profesional, depilación y tratamientos faciales.',
                'owner_name' => 'Sofía Herrera',
                'employees' => [
                    ['name' => 'Isabella Romero', 'specialty' => 'Maquilladora', 'commission' => 45],
                    ['name' => 'Gabriela Núñez', 'specialty' => 'Depiladora', 'commission' => 40],
                    ['name' => 'Renata Flores', 'specialty' => 'Esteticista', 'commission' => 35],
                ],
                'services' => [
                    ['name' => 'Maquillaje Social', 'category' => 'Maquillaje', 'price' => 25, 'duration' => 60],
                    ['name' => 'Maquillaje de Novia', 'category' => 'Maquillaje', 'price' => 60, 'duration' => 90],
                    ['name' => 'Maquillaje Artístico', 'category' => 'Maquillaje', 'price' => 40, 'duration' => 75],
                    ['name' => 'Depilación de Cejas', 'category' => 'Depilación', 'price' => 8, 'duration' => 20],
                    ['name' => 'Depilación de Piernas', 'category' => 'Depilación', 'price' => 18, 'duration' => 45],
                    ['name' => 'Depilación Facial', 'category' => 'Depilación', 'price' => 10, 'duration' => 20],
                    ['name' => 'Tratamiento Facial', 'category' => 'Tratamientos Faciales y Corporales', 'price' => 30, 'duration' => 60],
                    ['name' => 'Limpieza Facial Profunda', 'category' => 'Tratamientos Faciales y Corporales', 'price' => 35, 'duration' => 75],
                ],
            ],
        ];
    }
}
