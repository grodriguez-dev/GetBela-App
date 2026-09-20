<?php

namespace Tests\Feature;



use App\Models\Category;
use App\Models\User;
use App\Models\Business;
use App\Models\Service;
use App\Models\Employee;
use App\Models\Product; // Asegúrate de tener el modelo Product creado
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $user;
    protected $category;

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

        $this->category = Category::factory()->create();
    }

    /** @test */
    public function test_an_admin_can_create_and_manage_services()
    {
        $this->actingAs($this->user, 'sanctum');

        $employee = Employee::factory()->create();
        
        // 1. CREACIÓN (POST)
        $response = $this->postJson('/api/services', [
            'name' => 'Balayage Premium',
            'category_id' => $this->category->id,
            'employee_id' => $employee->id,
            'price' => 120.00,
            'duration_minutes' => 180 // en minutos
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('services', ['name' => 'Balayage Premium', 'business_id' => $this->business->id]);

        $serviceId = $response->json('service.id');

        // 2. ACTUALIZACIÓN (PUT)
        $this->putJson("/api/services/{$serviceId}", [
            'name' => 'Balayage Premium Ajustado',
            'category_id' => $this->category->id,
            'price' => 135.00,
            'duration_minutes' => 150
        ])->assertStatus(200);

        $this->assertDatabaseHas('services', ['name' => 'Balayage Premium Ajustado', 'price' => 135.00]);
    }

    /** @test */
    public function test_an_admin_can_create_and_manage_employees()
    {
        $this->actingAs($this->user, 'sanctum');

        // 1. CREACIÓN (POST)
        $response = $this->postJson('/api/employees', [
            'name' => 'Carlos Estilista',
            'specialty' => 'Secador',
            'give_access' => false,
            'commission_percentage' => 45.00
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['name' => 'Carlos Estilista', 'business_id' => $this->business->id]);

        $employeeId = $response->json('employee.id');

        // 2. ACTUALIZACIÓN (PUT)
        $this->putJson("/api/employees/{$employeeId}", [
            'name' => 'Carlos Master Estilista',
            'commission_percentage' => 50.00,
            'give_access' => false,
            'is_active' => true,
        ])->assertStatus(200);
    }

    /** @test */
    public function test_an_admin_can_create_and_update_products_vitrina()
    {
        $this->actingAs($this->user, 'sanctum');

        // 1. CREACIÓN (POST)
        $response = $this->postJson('/api/products', [
            'name' => 'Óleo de Argán Kérastase',
            'price' => 45.00,
            'stock' => 10,
            'stock_current' => 9,
            'stock_min' => 2,
            'purchase_price' => 3.80,
            'type' => 'uso_interno'
            ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Óleo de Argán Kérastase', 'business_id' => $this->business->id]);

        $productId = $response->json('product.id');

        // 2. ACTUALIZACIÓN DE STOCK / PRECIO (PUT)
        $this->putJson("/api/products/{$productId}", [
            'name' => 'Óleo de Argán Kérastase',
            'price' => 50.00, // Subió de precio
            'stock' => 8,      // Se vendieron 2
            'type' => 'venta_publico',
            'purchase_price' => 14.00
        ])->assertStatus(200);
    }

    /** @test */
    public function test_it_prevents_creating_products_with_negative_initial_stock()
    {
        // 1. Estructuramos un payload con stock erróneo e inválido
        $payload = [
            'name' => 'Shampoo Kerastase 250ml',
            'price' => 25.00,
            'stock_current' => -5, // ❌ Stock negativo que debe ser rechazado
        ];

        // 2. Intentamos crearlo como administrador (Owner)
        // Ajusta la URL si tu endpoint de productos es diferente (ej: /api/products)
        $response = $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/products', $payload);

        // 3. El sistema debe responder con un 422 de validación en la llave del stock
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['stock_current']);
    }

    /** @test */
    public function test_an_employee_cannot_delete_a_service_from_the_catalog()
    {
        // 1. Creamos un servicio en el salón
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'price' => 10.00,
            'name' => 'Pestañas postisa',
            'duration_minutes' => 60,
        ]);

        // 2. Creamos un usuario con rol de empleado en el mismo salón
        $employeeUser = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);

        // 3. Intentamos eliminar el servicio usando la cuenta del empleado
        $response = $this->actingAs($employeeUser, 'sanctum')
                        ->deleteJson("/api/services/{$service->id}");

        // 4. El sistema debe denegar el acceso con un 403 Forbidden
        $response->assertStatus(403);
        
        // Confirmamos que el servicio sigue intacto en la base de datos
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    /** @test */
    public function test_an_employee_cannot_delete_a_product_vitrina()
    {
        // 1. Creamos un producto de vitrina
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // 2. Creamos el usuario empleado
        $employeeUser = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);

        // 3. Intentamos eliminar el producto como empleado
        $response = $this->actingAs($employeeUser, 'sanctum')
                        ->deleteJson("/api/products/{$product->id}");

        // 4. Debe rebotar con 403 Forbidden
        $response->assertStatus(403);
        
        // Confirmamos que el producto no se borró
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** @test */
    public function test_it_prevents_selling_a_product_when_it_is_out_of_stock()
    {
        // 1. Creamos un producto de vitrina con stock AGOTADO (0 unidades)
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'stock_current' => 0, // No hay existencias
            'sale_price' => 15.00
        ]);

        // 2. Intentamos realizar una venta de este producto
        // (Ajusta el endpoint y la estructura al flujo real de tus ventas de vitrina)
        $payload = [
            'product_id' => $product->id,
            'quantity' => 1,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/sales', $payload);

        // 3. El sistema debe responder con un 422 indicando el error de validación por falta de stock
        $response->assertStatus(422)
                ->assertJsonFragment([
                    'message' => 'No hay suficiente stock disponible para realizar la venta.'
                ]);
    }
}