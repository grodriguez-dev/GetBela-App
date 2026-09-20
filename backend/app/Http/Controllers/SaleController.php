<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * LISTAR VENTAS: Muestra el historial de productos vendidos en vitrina.
     */
    public function index()
    {
        $sales = Sale::where('business_id', Auth::user()->business_id)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($sales, 200);
    }

    /**
     * EJECUTAR VENTA DIRECTA: Valida, descuenta stock y registra la ganancia.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $businessId = Auth::user()->business_id;

        // Buscar el producto y asegurar que pertenezca al negocio autenticado
        $product = Product::where('id', $request->product_id)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($product->stock_current < $request->quantity) {
            return response()->json([
                'message' => 'No hay suficiente stock disponible para realizar la venta.'
            ], 422);
        }

        // Seguridad: Evitar vender productos configurados estrictamente como 'uso_interno'
        if ($product->type === 'uso_interno') {
            return response()->json([
                'message' => 'Operación rechazada. Este producto está reservado exclusivamente para uso interno del salón.'
            ], 422);
        }

        // Seguridad: Verificar existencias suficientes
        if ($product->stock_current < $request->quantity) {
            return response()->json([
                'message' => "Stock insuficiente. Solo quedan {$product->stock_current} unidades disponibles de este producto."
            ], 422);
        }

        // Transacción de Base de Datos para asegurar que todo pase o falle junto
        $sale = DB::transaction(function () use ($request, $product, $businessId) {
            $totalPrice = $request->quantity * $product->sale_price;

            // 1. Restar las existencias del inventario
            $product->decrement('stock_current', $request->quantity);

            // 2. Crear el registro contable de la venta
            return Sale::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'unit_price' => $product->sale_price,
                'total_price' => $totalPrice,
            ]);
        });

        return response()->json([
            'message' => 'Venta de vitrina procesada con éxito.',
            'sale' => $sale->load('product')
        ], 201);
    }
}
