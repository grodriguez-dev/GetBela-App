<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BusinessHourController extends Controller
{
    /**
     * OBTENER HORARIOS: Muestra la jornada laboral actual del negocio.
     */
    public function index()
    {
        $hours = BusinessHour::where('business_id', Auth::user()->business_id)
            ->orderBy('day_of_week', 'asc')
            ->get();

        return response()->json([
            'message' => 'Horarios de atención recuperados con éxito',
            'hours' => $hours
        ], 200);
    }

    /**
     * CONFIGURAR/ACTUALIZAR HORARIOS EN MASA: Guarda la semana completa.
     */
    public function updateWeeklyHours(Request $request)
    {
        $businessId = Auth::user()->business_id;

        // 1. Validar únicamente el formato y que sean requeridos si no están cerrados
        $request->validate([
            'hours' => 'required|array|min:1|max:7',
            'hours.*.day_of_week' => 'required|integer|min:0|max:6',
            'hours.*.is_closed' => 'required|boolean',
            'hours.*.opening_time' => 'required_if:hours.*.is_closed,false|nullable|date_format:H:i',
            'hours.*.closing_time' => 'required_if:hours.*.is_closed,false|nullable|date_format:H:i',
        ]);

        // 2. Procesar y comparar directamente como texto/horas en PHP puro
        DB::transaction(function () use ($request, $businessId) {
            foreach ($request->hours as $hourData) {
                // Si el día no está cerrado, validamos lógicamente que el cierre sea mayor a la apertura
                if (!filter_var($hourData['is_closed'], FILTER_VALIDATE_BOOLEAN)) {
                    if ($hourData['closing_time'] <= $hourData['opening_time']) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "hours.{$hourData['day_of_week']}.closing_time" => "La hora de cierre debe ser posterior a la de apertura."
                        ]);
                    }
                }

                BusinessHour::updateOrCreate(
                    [
                        'business_id' => $businessId,
                        'day_of_week' => $hourData['day_of_week']
                    ],
                    [
                        'opening_time' => $hourData['is_closed'] ? '00:00:00' : $hourData['opening_time'] . ':00',
                        'closing_time' => $hourData['is_closed'] ? '00:00:00' : $hourData['closing_time'] . ':00',
                        'is_closed' => $hourData['is_closed'],
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Jornada laboral actualizada correctamente en GetBela.',
            'hours' => BusinessHour::where('business_id', $businessId)->orderBy('day_of_week', 'asc')->get()
        ], 200);
    }
}
