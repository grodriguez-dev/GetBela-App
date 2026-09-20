<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index()
    {
        // 1. Capturamos el negocio del usuario autenticado (Multitenancy)
        $businessId = Auth::user()->business_id;

        // 2. Consultamos solo los clientes que pertenecen a este negocio
        $clients = Client::where('business_id', $businessId)
            ->withCount('appointments') // Agrega una columna virtual 'appointments_count'
            ->with([
                'appointments' => function ($query) {
                    // Ordenamos las citas de la más reciente a la más antigua
                    $query->latest('date')
                          ->latest('start_time')
                          ->with('service:id,name'); // Solo cargamos el id y nombre del servicio para optimizar
                }
            ])
            ->orderBy('name', 'asc') // Ordenados alfabéticamente
            ->get();

        // 3. Retornamos la respuesta estructurada
        return response()->json([
            'clients' => $clients
        ], 200);
    }
}
