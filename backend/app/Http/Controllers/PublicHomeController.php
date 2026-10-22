<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Category;
use App\Models\Banner;
use App\Models\Contact;
use Illuminate\Http\Request;

class PublicHomeController extends Controller
{
    /**
     * 1. Obtener la lista de servicios públicos (con soporte para filtros de categoría y búsqueda).
     */
    public function index(Request $request)
    {
        // Cargamos la relación con el negocio/estilista y con su categoría
        $query = Service::with(['business', 'category'])
                        ->where('is_active', true) // Solo servicios activos para el público
                        // Ocultamos los servicios de negocios suspendidos.
                        ->whereHas('business', function ($b) {
                            $b->where('is_active', true);
                        });

        // Filtro por categoría (cuando hacen clic en las tarjetas de categoría)
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        // Buscador inteligente (busca por nombre del servicio o por el nombre del salón/estilista)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhereHas('business', function($bQuery) use ($search) {
                      $bQuery->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $services = $query->select('id', 'business_id', 'category_id', 'name', 'image_url', 'price', 'duration_minutes')
                          ->orderBy('created_at', 'desc')
                          ->get();

        return response()->json([
            'success' => true,
            'services' => $services
        ], 200);
    }

    /**
     * 2. Obtener las categorías para las tarjetas de filtro de la interfaz.
     */
    public function getCategories()
    {
        $categories = Category::select('id', 'name', 'slug')->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories
        ], 200);
    }

    /**
     * 3. Obtener los banners publicitarios para el hero a ancho completo.
     */
    public function getBanners()
    {
        $banners = Banner::all(['id', 'image', 'link']);

        return response()->json([
            'success' => true,
            'banners' => $banners
        ], 200);
    }

    /**
     * 4. Obtener los datos de contacto públicos (para la página de contacto).
     */
    public function getContacts()
    {
        $contacts = Contact::all(['id', 'name', 'icon', 'url']);

        return response()->json([
            'success' => true,
            'contacts' => $contacts
        ], 200);
    }
}