<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{   
    /**
     * 1. LISTAR SERVICIOS: Devuelve solo los servicios del negocio del usuario logueado.
     */
    public function index()
    {
        // Cargamos los servicios con su receta de productos asociada
        $services = Service::where('business_id', Auth::user()->business_id)
            ->with('category', 'employee', 'products')
            ->orderBy('name')
            ->get();

        return response()->json($services, 200);
    }

    /**
     * 2. CREAR SERVICIO: Registra un nuevo servicio amarrado al negocio del usuario.
     */
    public function store(Request $request)
    {
        if ($request->has('products') && is_string($request->products)) {
            $request->merge([
                'products' => json_decode($request->products, true)
            ]);
        }
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            // Validación de la receta (opcional al crear el servicio)
            'products' => 'nullable|array',
            'products.*.id' => 'required_with:products|exists:products,id',
            'products.*.quantity' => 'required_with:products|integer|min:1'
        ]);

        $data = $request->except(['image']);
        $data['business_id'] = $request->user()->business->id;

        if ($request->hasFile('image_url')) {
            $path = $request->file('image_url')->store('services', 'public');
            $data['image_url'] = $path;
        }

        $service = Service::create($data);

        // Si se enviaron productos para la receta, los vinculamos en la tabla intermedia
        if ($request->has('products') && is_array($request->products)) {
            foreach ($request->products as $prod) {
                if (isset($prod['id']) && isset($prod['quantity'])) {
                    $service->products()->attach($prod['id'], ['quantity_default' => $prod['quantity']]);
                }
            }
        }

        return response()->json([
            'message' => 'Servicio creado con su receta base con éxito.',
            'service' => $service->load('products')
        ], 201);
    }

    /**
     * 3. Mostrar Detalles del Servicios
     */
    public function show($id)
    {
        // Buscamos el servicio incluyendo relaciones como 'business' o 'category'
        $service = Service::with(['business', 'category', 'products', 'employee'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'service' => $service
        ], 200);
    }

    /**
     * 4. ACTUALIZAR SERVICIO: Modifica un servicio existente (comprobando que pertenezca al negocio).
     */
    public function update(Request $request, $id)
    {
        $service = Service::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        if ($request->has('products') && is_string($request->products)) {
            $request->merge([
                'products' => json_decode($request->products, true)
            ]);
        }

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'duration_minutes' => 'required|integer|min:1',
            'products' => 'nullable|array',
            'products.*.id' => 'required_with:products|exists:products,id',
            'products.*.quantity' => 'required_with:products|integer|min:1'
        ]);

        // 2. Preparar datos sin incluir la imagen ni los productos todavía
        $data = $request->except(['image_url', 'products']);

        // 3. Lógica de Imagen (si viene una nueva)
        if ($request->hasFile('image_url')) {
            // Primero borramos la vieja si existe
            if ($service->image_url) {
                Storage::disk('public')->delete($service->image_url);
            }
            // Guardamos la nueva
            $data['image_url'] = $request->file('image_url')->store('services', 'public');
        }

        // 4. Actualizar el modelo Service
        $service->update($data);

        // 5. Sincronizar productos (pivote)
        if ($request->has('products') && is_array($request->products)) {
            $syncData = [];
            foreach ($request->products as $prod) {
                $syncData[$prod['id']] = ['quantity_default' => $prod['quantity']];
            }
            $service->products()->sync($syncData);
        }

        return response()->json([
            'message' => 'Servicio actualizado correctamente.',
            'service' => $service->load('products')
        ], 200);

    }

    /**
     * 5. ELIMINAR SERVICIO: Borra el servicio de la base de datos de manera segura.
     */
    public function destroy($id)
    {
        $service = Service::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        if ($service->image_url) {
            Storage::disk('public')->delete($service->image_url);
        }

        $service->delete();

        return response()->json(['message' => 'Servicio eliminado correctamente.'], 200);
    }
}
