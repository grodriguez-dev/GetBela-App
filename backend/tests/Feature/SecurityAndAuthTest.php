<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAndAuthTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Creamos la base para las pruebas de acceso
        $this->business = Business::create([
            'name' => 'Bela Salón Test',
            'slug' => 'bela-test-studio',
            'address' => 'Dirección de Prueba',
            'phone' => '+584120000000'
        ]);

        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
            'email' => 'tester@getbela.com',
            'password' => Hash::make('password123'),
        ]);
    }

    /** @test */
    public function test_an_unauthenticated_user_cannot_access_protected_routes()
    {
        // Intentamos entrar al dashboard de finanzas sin loguearnos
        $response = $this->getJson('/api/finances/dashboard');

        // Debe dar un rotundo 401 Unauthorized
        $response->assertStatus(401);
    }

    /** @test */
    public function test_a_user_can_login_successfully_with_correct_credentials()
    {
        // Enviamos las credenciales correctas a tu endpoint de login
        $response = $this->postJson('/api/login', [
            'email' => 'tester@getbela.com',
            'password' => 'password123'
        ]);

        // Debe responder exitosamente y retornar el token de Sanctum
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'access_token',
                     'user' => ['id', 'name', 'email']
                 ]);
    }

    /** @test */
    public function test_a_user_cannot_login_with_incorrect_credentials()
    {
        // Intentamos loguearnos con una clave errónea
        $response = $this->postJson('/api/login', [
            'email' => 'tester@getbela.com',
            'password' => 'clave_falsa_123'
        ]);

        // Debe denegar el acceso
        $response->assertStatus(401); 
    }

    /** @test */
    public function test_a_new_user_can_register_a_salon_or_independent_profile()
    {
        // Simulamos el flujo donde alguien llega a la landing y se registra
        $response = $this->postJson('/api/register', [
            'business_name' => 'Studio Creativo Glam', // El backend creará el Business automáticamente
            'business_type' => 'salon', // o 'independent'
            'name' => 'Propietaria Bela',
            'modality' => 'local',
            'email' => 'propietaria@getbela.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->assertStatus(201);

        // Verificamos que se crearon ambas entidades amarradas correctamente
        $this->assertDatabaseHas('businesses', ['name' => 'Studio Creativo Glam']);
        $this->assertDatabaseHas('users', ['email' => 'propietaria@getbela.com']);
    }
}