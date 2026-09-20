<?php

namespace App\Http\Controllers\admin;

use App\Models\Category;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Todas las categorias.
     */
    public function index()
    {
        $categories = Category::all();

        return response()->json([
            'message' => 'Categorias recuperadas con éxito.',
            'categories' => $categories
        ], 200);
    }
}