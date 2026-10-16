<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function show($id)
    {
        // Buscamos el negocio junto con sus relaciones clave
        $business = Business::with([
            'services.employee', // Los servicios que ofrece y quién los atiende
            'employees'          // El equipo de trabajo (si es un salón)
        ])->findOrFail($id);

        return response()->json([
            'business' => $business,
            'services' => $business->services,
            'employees' => $business->employees,
        ], 200);
    }

    public function update(Request $request)
    {
        // Normalizamos el teléfono del negocio al formato venezolano (+58XXXXXXXXXX).
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
        ]);

        // 1. Validar todos los campos de tu migración
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'type'        => 'sometimes|in:salon,independent',
            'modality'    => 'sometimes|in:local,domicilio,hibrido',
            'phone'       => ['required', 'string', 'regex:/^\+58\d{10}$/'],
            'address'     => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
        $business = $user->business;
        if (!$business && $user->business_id) {
            $business = Business::find($user->business_id);
        }
        if (!$business && $user->business_id) {
            $business = Business::where('id', $user->business_id)->first();
        }
        if (!$business) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }
        $slug = Str::slug($request->name ?? $business->name ?? 'business');

        // 2. Preparar los datos
        // Usamos all() o except para capturar todos los campos enviados
        $data = $request->only(['name','slug' => $slug, 'type', 'modality', 'phone', 'address', 'description']);

        // 3. Lógica del logo
        if ($request->hasFile('logo')) {
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = $path;
        }

        // 4. Actualizar
        $business->update($data);

        return response()->json([
            'message' => 'Perfil del negocio actualizado correctamente',
            'business' => $business
        ], 200);
    }
}
