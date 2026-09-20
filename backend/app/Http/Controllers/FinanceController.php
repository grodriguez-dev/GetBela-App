<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinanceController extends Controller
{
    public function getDashboard(Request $request)
    {
        $businessId = Auth::user()->business_id;
        $period = $request->query('period', 'month'); 
        
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        if ($period === 'today') { $startDate = Carbon::now()->startOfDay(); $endDate = Carbon::now()->endOfDay(); }
        elseif ($period === 'week') { $startDate = Carbon::now()->startOfWeek(); $endDate = Carbon::now()->endOfWeek(); }
        elseif ($period === 'year') { $startDate = Carbon::now()->startOfYear(); $endDate = Carbon::now()->endOfYear(); }
        elseif ($period === 'all') { $startDate = Carbon::parse('2020-01-01'); $endDate = Carbon::now()->addYears(5); }

        $startStr = $startDate->format('Y-m-d H:i:s');
        $endStr = $endDate->format('Y-m-d H:i:s');


        // 1. INGRESOS POR SERVICIOS (Citas Completadas)
        $earningsServices = Appointment::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('date', [
                $startDate->startOfDay()->format('Y-m-d H:i:s'), 
                $endDate->endOfDay()->format('Y-m-d H:i:s')
        ])
        ->sum('total_price');

        // Forzamos a que sea un float explícito con decimales en la respuesta
        $earningsServices = floatval($earningsServices);

        // 2. INGRESOS POR VITRINA (Ventas Directas de Productos)
        $earningsSales = Sale::where('business_id', $businessId)
            ->whereBetween('created_at', [$startStr, $endStr])
            ->sum('total_price');

        // 3. PROYECCIÓN DE INGRESOS (Citas futuras o por confirmar)
        $earningsProjected = Appointment::where('business_id', $businessId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('date', [$startDate->startOfDay()->format('Y-m-d H:i:s'), 
                $endDate->endOfDay()->format('Y-m-d H:i:s')
            ])
            ->sum('total_price');

        // 4. RENDIMIENTO Y LIQUIDACIÓN DE COMISIONES DEL STAFF
        $staffPerformance = Appointment::where('appointments.business_id', $businessId)
            ->where('appointments.status', 'completed')
            ->whereBetween('appointments.date', [$startStr, $endStr]) // 👈 Usamos los strings fijos
            ->leftJoin('employees', 'appointments.employee_id', '=', 'employees.id')
            ->select(
                DB::raw('COALESCE(employees.name, "Personal no asignado o eliminado") as employee_name'),
                DB::raw('COALESCE(employees.commission_percentage, 0) as commission_rate'),
                DB::raw('COUNT(appointments.id) as total_appointments'),
                DB::raw('SUM(appointments.total_price) as gross_generated')
            )
            ->groupBy('appointments.employee_id', 'employees.name', 'employees.commission_percentage')
            ->get()
            ->map(function ($row) {
                // Mantenemos los números puros como flotantes/enteros según PHP decida
                $row->gross_generated = floatval($row->gross_generated);
                $gross = $row->gross_generated;
                $commissionRate = floatval($row->commission_rate);
                
                $row->employee_payout = ($gross * $commissionRate) / 100;
                $row->salon_net_profit = $gross - $row->employee_payout;
                
                return $row;
        });

        // 5. TOP PRODUCTOS MÁS VENDIDOS EN VITRINA (Estadística de inventario)
        $topProductsSold = Sale::where('sales.business_id', $businessId)
            ->whereBetween('sales.created_at', [$startStr, $endStr])
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->select(
                'products.name as product_name',
                DB::raw('SUM(sales.quantity) as total_units_sold'),
                DB::raw('SUM(sales.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // 6. MATEMÁTICA GLOBAL
        $totalGrossEarned = floatval($earningsServices) + floatval($earningsSales);

        return response()->json([
            'message' => 'Métricas financieras unificadas recuperadas con éxito.',
            'filter_applied' => $period,
            'range' => [
                'start' => $startStr,
                'end' => $endStr
            ],
            'summary' => [
                'total_gross_combined' => $totalGrossEarned, // Total General (Citas + Vitrina)
                'revenue_from_services' => floatval($earningsServices), // Dinero por Citas
                'revenue_from_vitrina' => floatval($earningsSales),    // Dinero por Productos
                'total_projected_services' => floatval($earningsProjected),
            ],
            'performance_by_staff' => $staffPerformance,
            'top_products_sold' => $topProductsSold
        ], 200);
    }
}