<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            
            // Campos requeridos SOLO si se registra como dueño de negocio
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|',
            'modality' => 'required|',
        ]);

        $business = Business::create([
            'name' => $request->business_name,
            'slug' => Str::slug($request->business_name),
            'type' => $request->business_type,
            'modality' => $request->modality ?? 'local',
        ]);
            $businessId = $business->id;
        
        // Creamos el usuario vinculándolo al negocio (si aplica)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'owner',
            'business_id' => $businessId,
        ]);

        return response()->json([
            'message' => 'Registro completado con éxito en GetBela',
            'data' => [
                'user'     => $user,
                'business' => $business
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        // 1. Validar las credenciales de entrada
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // 2. Intentar autenticar al usuario
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // 3. Obtener el usuario autenticado
        $user = User::where('email', $request->email)->firstOrFail();

        $user->load('business');

        // 4. Crear el token de acceso usando Sanctum
        // El nombre del token puede ser cualquier string, ej: 'auth_token'
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. Retornar el token y los datos del usuario (¡incluyendo su rol!)
        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'business' => $user->business,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Sesión cerrada correctamente en GetBela. Token destruido.'
        ], 200);
    }
}
