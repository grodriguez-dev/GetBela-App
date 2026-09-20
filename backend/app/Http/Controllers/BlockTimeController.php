<?php

namespace App\Http\Controllers;

use App\Models\BlockTime;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockTimeController extends Controller
{
    public function index()
    {
        $blocks = BlockTime::where('business_id', Auth::user()->business_id)
            ->with('service')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($blocks, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'service_id' => 'nullable|exists:services,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'reason' => 'nullable|string|max:255'
        ]);

        $businessId = Auth::user()->business_id;

        // Seguridad: Validar que no existan citas activas en el rango que se quiere bloquear
        $hasAppointments = Appointment::where('business_id', $businessId)
            ->where('date', $request->date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where(function($query) use ($request) {
                if ($request->service_id) {
                    $query->where('service_id', $request->service_id);
                }
            })
            ->where(function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('start_time', '<=', $request->start_time)
                      ->where('end_time', '>', $request->start_time);
                })
                ->orWhere(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>=', $request->end_time);
                });
            })
            ->exists();

        if ($hasAppointments) {
            return response()->json([
                'message' => 'No se puede bloquear este horario porque ya existen citas agendadas en ese rango.'
            ], 422);
        }

        $block = BlockTime::create([
            'business_id' => $businessId,
            'service_id' => $request->service_id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason
        ]);

        return response()->json([
            'message' => 'Horario bloqueado correctamente en la agenda.',
            'block' => $block
        ], 201);
    }

    public function destroy($id)
    {
        $block = BlockTime::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        $block->delete();

        return response()->json(['message' => 'Bloqueo liberado. Horario disponible nuevamente.'], 200);
    }
}
