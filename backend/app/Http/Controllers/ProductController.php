<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * LISTAR INVENTARIO: Devuelve los productos del negocio autenticado
     * y marca automáticamente cuáles necesitan reposición urgente.
     */
    public function index()
    {
        $products = Product::where('business_id', Auth::user()->business_id)
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($product) {
                // Añadimos una bandera lógica en tiempo real para el frontend
                $product->low_stock = $product->stock_current <= $product->stock_min;
                return $product;
            });

        return response()->json([
            'message' => 'Inventario recuperado con éxito.',
            'products' => $products
        ], 200);
    }

    /**
     * REGISTRAR PRODUCTO: Añade un nuevo artículo al almacén o vitrina.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'stock_current' => 'required|integer|min:0',
            'stock_min' => 'required|integer|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'type' => 'required|in:uso_interno,venta_publico,mixto',
        ]);

        $product = Product::create([
            'business_id' => Auth::user()->business_id,
            'name' => $request->name,
            'brand' => $request->brand,
            'description' => $request->description,
            'stock_current' => $request->stock_current,
            'stock_min' => $request->stock_min,
            'purchase_price' => $request->purchase_price,
            'sale_price' => $request->sale_price,
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Producto registrado con éxito en el inventario.',
            'product' => $product
        ], 201);
    }

    /**
     * ACTUALIZAR DETALLES: Modifica información básica del producto.
     */
    public function update(Request $request, $id)
    {
        $product = Product::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
        ]);

        $product->update($request->only(['name', 'brand', 'description', 'stock_min', 'purchase_price', 'sale_price', 'type']));

        return response()->json([
            'message' => 'Producto actualizado con éxito.',
            'product' => $product
        ], 200);
    }

    /**
     * ELIMINAR PRODUCTO: Saca un artículo del catálogo definitivamente.
     */
    public function destroy($id)
    {
        $product = Product::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        $product->delete();

        return response()->json([
            'message' => 'Producto eliminado del inventario correctamente.'
        ], 200);
    }

    /**
     * AJUSTAR STOCK MÁS/MENOS: Suma o resta unidades de forma rápida.
     * Ideal para cuando el estilista gasta material o ingresa un pedido nuevo.
     */
    public function adjustStock(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:add,remove',
            'quantity' => 'required|integer|min:1'
        ]);

        $product = Product::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        if ($request->action === 'add') {
            $product->increment('stock_current', $request->quantity);
        } else {
            // Validación de seguridad para que el stock no quede en números negativos
            if ($product->stock_current < $request->quantity) {
                return response()->json([
                    'message' => 'Operación rechazada. No hay suficientes existencias en inventario.'
                ], 422);
            }
            $product->decrement('stock_current', $request->quantity);
        }

        return response()->json([
            'message' => 'Stock ajustado correctamente.',
            'product' => $product
        ], 200);
    }
}
