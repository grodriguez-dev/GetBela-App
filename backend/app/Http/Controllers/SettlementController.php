<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Settlement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SettlementController extends Controller
{
    /**
     * Muestra la liquidación PENDIENTE de un período (día / semana / mes).
     *
     * Solo cuenta citas completadas que todavía no fueron incluidas en otra
     * liquidación, de modo que nunca se paga dos veces la misma cita.
     */
    public function preview(Request $request)
    {
        $period = $request->query('period', 'day');
        if (!in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $businessId = Auth::user()->business_id;
        [$start, $end] = $this->resolveRange($period, $request->query('date'));

        $items = $this->pendingItems($businessId, $start, $end);
        $days = $this->daysBreakdown($businessId, $start, $end);

        // Liquidaciones que tocan este período, aunque sean de otro rango
        // (p. ej. al ver la semana después de liquidar un día).
        $overlappingSettlements = Settlement::where('business_id', $businessId)
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->count();

        return response()->json([
            'message' => 'Liquidación pendiente calculada con éxito.',
            'period' => $period,
            'range' => $this->rangeMeta($period, $start, $end),
            'previous_settlements' => $overlappingSettlements,
            'summary' => $this->buildTotals($items),
            'items' => $items,
            'days' => $days,
        ], 200);
    }

    /**
     * Registra la liquidación (pago) de TODO el pendiente del período y la
     * guarda en el historial.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'period_type' => 'required|in:day,week,month',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $businessId = Auth::user()->business_id;
        [$start, $end] = $this->resolveRange($data['period_type'], $data['date']);

        $items = $this->pendingItems($businessId, $start, $end);

        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'No hay movimientos pendientes por liquidar en este período.',
            ], 422);
        }

        $totals = $this->buildTotals($items);

        $settlement = DB::transaction(function () use ($businessId, $data, $start, $end, $items, $totals) {
            $settlement = Settlement::create([
                'business_id' => $businessId,
                'period_type' => $data['period_type'],
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'appointments_count' => $totals['appointments_count'],
                'total_gross' => $totals['total_gross'],
                'total_commission' => $totals['total_commission'],
                'business_net' => $totals['business_net'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'paid_at' => Carbon::now(),
            ]);

            foreach ($items as $item) {
                $settlement->items()->create($item);
            }

            // Marcamos como liquidadas SOLO las citas pendientes del rango.
            Appointment::where('business_id', $businessId)
                ->where('status', 'completed')
                ->whereNull('settlement_id')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->update(['settlement_id' => $settlement->id]);

            return $settlement;
        });

        return response()->json([
            'message' => 'Liquidación registrada con éxito.',
            'settlement' => $settlement->load(['items', 'creator:id,name']),
        ], 201);
    }

    /**
     * Historial de liquidaciones del negocio (más recientes primero).
     */
    public function index(Request $request)
    {
        $businessId = Auth::user()->business_id;

        $settlements = Settlement::where('business_id', $businessId)
            ->with(['items', 'creator:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json($settlements, 200);
    }

    /**
     * Calcula los límites del período a partir de una fecha de referencia.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(string $period, ?string $date): array
    {
        $base = ($date && strtotime($date)) ? Carbon::parse($date) : Carbon::now();

        switch ($period) {
            case 'day':
                $start = $base->copy()->startOfDay();
                $end = $base->copy()->endOfDay();
                break;
            case 'week':
                // Semana de lunes a domingo.
                $start = $base->copy()->startOfWeek();
                $end = $base->copy()->endOfWeek();
                break;
            case 'month':
            default:
                $start = $base->copy()->startOfMonth();
                $end = $base->copy()->endOfMonth();
                break;
        }

        return [$start, $end];
    }

    /**
     * Metadatos del rango para mostrar en la interfaz (fechas + etiqueta).
     */
    private function rangeMeta(string $period, Carbon $start, Carbon $end): array
    {
        $label = match ($period) {
            'day' => $start->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'week' => 'Semana del '
                . $start->locale('es')->isoFormat('D MMM')
                . ' al '
                . $end->locale('es')->isoFormat('D MMM YYYY'),
            default => ucfirst($start->locale('es')->isoFormat('MMMM [de] YYYY')),
        };

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $label,
        ];
    }

    /**
     * Agrupa las citas completadas y aún no liquidadas por trabajador efectivo.
     *
     * El trabajador efectivo es `appointments.employee_id` o, si viene vacío,
     * el `services.employee_id`. Así las citas históricas no caen en un cajón
     * "sin asignar" cuando sí se puede inferir la estilista.
     */
    private function pendingItems(int $businessId, Carbon $start, Carbon $end)
    {
        $effectiveEmployee = 'COALESCE(appointments.employee_id, services.employee_id)';

        return Appointment::query()
            ->where('appointments.business_id', $businessId)
            ->where('appointments.status', 'completed')
            ->whereNull('appointments.settlement_id')
            ->whereBetween('appointments.date', [$start->toDateString(), $end->toDateString()])
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->leftJoin('employees', DB::raw($effectiveEmployee), '=', 'employees.id')
            ->select(
                DB::raw($effectiveEmployee . ' as employee_id'),
                DB::raw('COALESCE(employees.name, "Sin asignar") as employee_name'),
                DB::raw('COALESCE(employees.commission_percentage, 0) as commission_percentage'),
                DB::raw('COUNT(appointments.id) as appointments_count'),
                DB::raw('SUM(appointments.total_price) as gross_generated')
            )
            ->groupBy(
                DB::raw($effectiveEmployee),
                'employees.name',
                'employees.commission_percentage'
            )
            ->get()
            ->map(function ($row) {
                $gross = round((float) $row->gross_generated, 2);
                $rate = round((float) $row->commission_percentage, 2);
                $payout = round($gross * $rate / 100, 2);

                return [
                    'employee_id' => $row->employee_id !== null ? (int) $row->employee_id : null,
                    'employee_name' => $row->employee_name,
                    'commission_percentage' => $rate,
                    'appointments_count' => (int) $row->appointments_count,
                    'gross_generated' => $gross,
                    'commission_payout' => $payout,
                    'business_net' => round($gross - $payout, 2),
                ];
            })
            ->sortByDesc('gross_generated')
            ->values();
    }

    /**
     * Desglose por día dentro del período: cuánto está pendiente y cuánto ya
     * se liquidó, para que el usuario vea exactamente qué días faltan.
     */
    private function daysBreakdown(int $businessId, Carbon $start, Carbon $end)
    {
        return Appointment::query()
            ->where('appointments.business_id', $businessId)
            ->where('appointments.status', 'completed')
            ->whereBetween('appointments.date', [$start->toDateString(), $end->toDateString()])
            ->select(
                DB::raw('DATE(appointments.date) as day'),
                DB::raw('COUNT(*) as appointments_count'),
                DB::raw('SUM(appointments.total_price) as gross'),
                DB::raw('SUM(CASE WHEN appointments.settlement_id IS NULL THEN 1 ELSE 0 END) as pending_count'),
                DB::raw('SUM(CASE WHEN appointments.settlement_id IS NULL THEN appointments.total_price ELSE 0 END) as pending_gross'),
                DB::raw('SUM(CASE WHEN appointments.settlement_id IS NOT NULL THEN 1 ELSE 0 END) as settled_count'),
                DB::raw('SUM(CASE WHEN appointments.settlement_id IS NOT NULL THEN appointments.total_price ELSE 0 END) as settled_gross')
            )
            ->groupBy(DB::raw('DATE(appointments.date)'))
            ->orderBy('day')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->day,
                    'appointments_count' => (int) $row->appointments_count,
                    'gross' => round((float) $row->gross, 2),
                    'pending_count' => (int) $row->pending_count,
                    'pending_gross' => round((float) $row->pending_gross, 2),
                    'settled_count' => (int) $row->settled_count,
                    'settled_gross' => round((float) $row->settled_gross, 2),
                ];
            })
            ->values();
    }

    /**
     * Totales del período a partir de las filas por trabajador.
     */
    private function buildTotals($items): array
    {
        return [
            'appointments_count' => (int) $items->sum('appointments_count'),
            'total_gross' => round((float) $items->sum('gross_generated'), 2),
            'total_commission' => round((float) $items->sum('commission_payout'), 2),
            'business_net' => round((float) $items->sum('business_net'), 2),
        ];
    }
}
