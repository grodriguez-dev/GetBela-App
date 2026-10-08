<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Service;
use App\Models\Product;
use App\Models\BlockTime;
use App\Models\BusinessHour;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * LISTAR CITAS: Recupera la agenda inyectando el servicio,
     * el estilista asignado y los insumos reservados.
     */
    public function index()
    {
        $businessId = Auth::user()->business_id;
        $now = Carbon::now(); // Fecha y hora actual exacta

        // 1. AUTO-CANCELAR: Si la cita es de un día anterior o, siendo hoy, ya pasó su hora de fin 
        // y sigue pendiente o confirmada, la pasamos automáticamente a 'cancelled'.
        Appointment::where('business_id', $businessId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($now) {
                $query->whereDate('date', '<', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->whereDate('date', '=', $now->toDateString())
                            ->where('end_time', '<', $now->toTimeString());
                    });
            })
            ->update(['status' => 'cancelled']);

        // 2. RETORNAR EL LISTADO ACTUALIZADO
        $appointments = Appointment::where('business_id', $businessId)
            ->with(['service.employee', 'client', 'consumedProducts'])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
            
        return response()->json($appointments, 200);
    }

    /**
     * AGENDAR CITA: Valida jornada laboral, ejecuta escudo anti-solapamiento
     * y hereda la "receta de insumos" automáticamente.
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'service_id' => 'required|exists:services,id',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:20',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'start_time' => 'required|date_format:H:i:s',
        ]);

        $businessId = $request->business_id;

        $client = Client::firstOrCreate(
            [
                'business_id' => $businessId,
                'phone'       => $request['client_phone'],
            ],
            [
                'name'        => $request['client_name'],
            ]
        );

        $service = Service::where('id', $request->service_id)
            ->where('business_id', $businessId)
            ->firstOrFail();
        
        // 1. Instanciar rangos horarios usando la fecha de la cita para que mantengan consistencia de calendario
        $startTime = Carbon::parse($request->date . ' ' . $request->start_time);
        $duration = $service->duration_minutes ?? 60;
        $endTime = $startTime->copy()->addMinutes($duration);

        // Formateamos los strings limpios que se guardarán en las columnas TIME de la BD
        $requestedStartTime = $startTime->format('H:i:s');
        $calculatedEndTime = $endTime->format('H:i:s');

        // 2. ESCUDO 1: Validar jornada laboral
        $dayOfWeek = Carbon::parse($request->date)->dayOfWeekIso;
        $businessHour = BusinessHour::where('business_id', $businessId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$businessHour || $businessHour->is_closed) {
            return response()->json(['message' => 'El negocio se encuentra cerrado el día solicitado.'], 422);
        }

        if ($requestedStartTime < $businessHour->opening_time || $calculatedEndTime > $businessHour->closing_time) {
            return response()->json([
                'message' => 'La información proporcionada no es válida.',
                'errors' => [
                    'start_time' => ['La cita excede las horas de atención operativas del salón.']
                ]
            ], 422);
        }

        // Creamos instancias Carbon de los límites usando el mismo día de la cita
        $openTime = Carbon::parse($request->date . ' ' . $businessHour->opening_time);
        $closeTime = Carbon::parse($request->date . ' ' . $businessHour->closing_time);

        // Comparación matemática cronológica estricta (detecta cruces de día y medianoche)
        if ($startTime->lt($openTime) || $endTime->gt($closeTime)) {
            return response()->json(['message' => 'La cita se sale del horario de atención del establecimiento.'], 422);
        }

        // 3. ESCUDO 2: Algoritmo Avanzado Anti-Solapamiento (Inmune a Drivers de BD)
        $existingAppointments = Appointment::withoutGlobalScopes() // <--- ¡AÑADE ESTA LÍNEA AQUÍ!
        ->where('service_id', $service->id)
        ->where('business_id', $businessId)
        ->whereDate('date', $request->date)
        ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
        ->where(function($query) use ($request) {
            if ($request->service_id) {
                $query->where('service_id', $request->service_id);
            }
        })
        ->get();

        $overlapping = false;

        foreach ($existingAppointments as $existing) {
            // Parseamos los tiempos de la base de datos usando la misma fecha del request para consistencia
            $existingStart = Carbon::parse($request->date . ' ' . $existing->start_time);
            $existingEnd = Carbon::parse($request->date . ' ' . $existing->end_time);

            // Caso 1: La nueva cita empieza antes o al mismo tiempo, y termina después de que la existente empiece
            // Caso 2: La nueva cita empieza antes de que la existente termine, y termina después o al mismo tiempo
            // Caso 3: La nueva cita engloba completamente a la existente
            if (($startTime->gte($existingStart) && $startTime->lt($existingEnd)) ||
                ($endTime->gt($existingStart) && $endTime->lte($existingEnd)) ||
                ($startTime->lte($existingStart) && $endTime->gte($existingEnd))) {
                $overlapping = true;
                break;
            }
        }

        if ($overlapping) {
            return response()->json(['message' => 'El horario solicitado o la especialista se encuentra ocupada en ese rango de tiempo.'], 422);
        }

        // 4. ESCUDO 3: Verificar Bloqueos Manuales en la Agenda (Universal)
        $isBlocked = BlockTime::where('business_id', $businessId)
            ->where('date', $request->date)
            ->where(function($query) use ($request) {
                if ($request->service_id) {
                    $query->where('service_id', $request->service_id)
                        ->orWhereNull('service_id');
                }
            })
            ->where(function ($query) use ($requestedStartTime, $calculatedEndTime) {
                $query->where(function ($q) use ($requestedStartTime, $calculatedEndTime) {
                    $q->where('start_time', '<=', $requestedStartTime)
                    ->where('end_time', '>', $requestedStartTime);
                })
                ->orWhere(function ($q) use ($requestedStartTime, $calculatedEndTime) {
                    $q->where('start_time', '<', $calculatedEndTime)
                    ->where('end_time', '>=', $calculatedEndTime);
                });
            })
            ->exists();

        if ($isBlocked) {
            return response()->json(['message' => 'El horario solicitado se encuentra bloqueado por la administración (Ausencia, almuerzo o descanso).'], 422);
        }

        // 5. Registrar la cita heredando el precio base del servicio para Finanzas
        $appointment = Appointment::create([
            'business_id' => $businessId,
            'service_id' => $request->service_id,
            'client_id'   => $client->id,
            'date' => $request->date,
            'start_time' => $requestedStartTime,
            'end_time' => $calculatedEndTime,
            'status' => 'pending',
            'total_price' => $service->price // <-- IMPORTANTE: Seteamos el precio para el FinanceController
        ]);

        // 6. MOTOR DE INVENTARIO: Vincular la receta por defecto y escanear alertas de quiebre
        $serviceRecipe = $service->products;
        $stockAlerts = [];

        foreach ($serviceRecipe as $product) {
            // Evaluamos si el producto está en cero o menos antes de vincularlo
            if ($product->stock_current <= 0) {
                $stockAlerts[] = "El servicio '{$service->name}' requiere '{$product->name}', pero actualmente no hay existencias en el inventario.";
            }

            $appointment->consumedProducts()->attach($product->id, [
                'quantity_used' => $product->pivot->quantity_default
            ]);
        }

        if ($service && $service->business_id) {

            Notification::create([
                'business_id' => $service->business_id,
                'appointment_id' => $appointment->id,
                'message' => "¡Nueva cita agendada para el servicio: {$service->name}!",
            ]);
        }

        return response()->json([
            'message' => 'Cita agendada con éxito. Insumos base vinculados a la agenda.',
            'appointment' => $appointment->load('consumedProducts', 'client'),
            'alerts' => $stockAlerts // <-- La mina de oro para el Frontend mañana 🚀
        ], 201);
    }

    /**
     * ACTUALIZAR citas 
     * 
     */
    public function update(Request $request, Appointment $appointment)
    {   
        $appointment = Appointment::where('business_id', Auth::user()->business_id)
        ->findOrFail($appointment->id);

        // REGRA DE INTEGRIDAD HISTÓRICA:
        if ($appointment->status === 'completed') {
            return response()->json([
                'message' => 'Acción no permitida.',
                'errors' => [
                    'status' => ['No se puede modificar una cita que ya ha sido completada o cobrada.']
                ]
            ], 422);
        }

        // 1. Validamos los datos de entrada (puedes usar un Form Request si prefieres)
        $validated = $request->validate([
            'service_id'  => 'sometimes|required|exists:services,id',
            'date'        => 'sometimes|required|date|after_or_equal:today',
            'start_time'  => 'sometimes|required',
        ]);

        // Conservamos los valores actuales si no se envían en la petición de actualización
        $serviceId  = $request->get('service_id', $appointment->service_id);
        $date       = $request->get('date', $appointment->date);
        $startTime  = $request->get('start_time', $appointment->start_time);

        // Calculamos el end_time dinámico basado en la duración del servicio
        $service = Service::findOrFail($serviceId);
        $start = Carbon::parse($startTime);
        $end = $start->copy()->addMinutes($service->duration ?? 60); // Asume 60 min si no viene duración
        $endTime = $end->format('H:i:s');

        $businessId = $appointment->business_id; // Mantenemos el mismo negocio

        // ------------------------------------------------------------------------
        // 🛡️ ESCUDO 1: Validar dentro de la Jornada Laboral (Omitido aquí por brevedad, o añade tu lógica)
        // ------------------------------------------------------------------------

        // ------------------------------------------------------------------------
        // 🛡️ ESCUDO 2: Anti-Solapamiento con otras citas del mismo especialista
        // ------------------------------------------------------------------------
        $existingAppointments = Appointment::where('business_id', $businessId)
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where('service_id', $serviceId)
            ->where('id', '!=', $appointment->id)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($existingAppointments) {
            return response()->json([
                'message' => 'El horario solicitado o la especialista se encuentra ocupada en ese rango de tiempo.'
            ], 422);
        }

        // ------------------------------------------------------------------------
        // 🛡️ ESCUDO 3: Bloqueos Administrativos (Manuales o Globales)
        // ------------------------------------------------------------------------
        $isBlocked = BlockTime::where('business_id', $businessId)
            ->whereDate('date', $date)
            ->where(function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId) // Bloqueo específico
                    ->orWhereNull('service_id');       // Bloqueo global del salón
            })
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($isBlocked) {
            return response()->json([
                'message' => 'El horario solicitado se encuentra blocked por la administración (Ausencia, almuerzo o descanso).'
            ], 422);
        }

        // Si pasa todos los escudos, actualizamos la cita
        $appointment->update(array_merge($validated, [
            'end_time' => $endTime
        ]));

        return response()->json($appointment, 200);
    }

    /**
     * AGREGAR INSUMO EXTRA: Permite añadir insumos no planificados en la receta
     * mientras el servicio esté activo.
     */
    public function addExtraProduct(Request $request, $id)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $appointment = Appointment::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        if ($appointment->status !== 'in_progress') {
            return response()->json(['message' => 'Solo puedes inyectar insumos extras a citas que estén en progreso.'], 422);
        }

        // Si el producto ya existía en la receta base, acumulamos la cantidad. Si no, lo agregamos de cero.
        $existingProduct = $appointment->consumedProducts()->where('product_id', $request->product_id)->first();

        if ($existingProduct) {
            $appointment->consumedProducts()->updateExistingPivot($request->product_id, [
                'quantity_used' => $existingProduct->pivot->quantity_used + $request->quantity
            ]);
        } else {
            $appointment->consumedProducts()->attach($request->product_id, ['quantity_used' => $request->quantity]);
        }

        return response()->json([
            'message' => 'Insumo extra añadido con éxito a la cita activa.',
            'appointment' => $appointment->load('consumedProducts')
        ], 200);
    }

    /**
     * INICIAR CITA: Pasa el estado a en progreso.
     */
    public function startAppointment($id)
    {
        $appointment = Appointment::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        if ($appointment->status !== 'pending' && $appointment->status !== 'confirmed') {
            return response()->json(['message' => 'La cita no se encuentra en un estado apto para iniciar.'], 422);
        }

        $appointment->update(['status' => 'in_progress']);

        return response()->json([
            'message' => 'Cita iniciada. El estilista está trabajando.',
            'appointment' => $appointment
        ], 200);
    }

    /**
     * FINALIZAR CITA: Cambia estado a completada, asienta minutos excedidos
     * y descuenta AUTOMÁTICAMENTE el inventario total consumido.
     */
    public function finishAppointment($id)
    {
        $appointment = Appointment::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        if ($appointment->status !== 'in_progress') {
            return response()->json(['message' => 'Solo se pueden finalizar citas que estén en progreso.'], 422);
        }

        // 1. LÓGICA DE TIEMPO: Control exacto de minutos excedidos (Timezone blindado)
        $now = Carbon::now();
        $theoreticalEnd = Carbon::createFromFormat(
            'Y-m-d H:i:s', 
            $appointment->date->format('Y-m-d') . ' ' . $appointment->end_time,
            config('app.timezone')
        );

        $extraNotes = "";
        if ($now->greaterThan($theoreticalEnd)) {
            $minutesExceeded = intval(abs($now->diffInMinutes($theoreticalEnd)));
            if ($minutesExceeded > 0) {
                $extraNotes = "\n[Cita finalizada con {$minutesExceeded} minutos de retraso]";
            }
        }

        // 2. AUTOMATIZACIÓN DE STOCK: Recorrer insumos de la cita y rebajarlos del inventario físico
        $consumedItems = $appointment->consumedProducts;
        foreach ($consumedItems as $item) {
            $productInventory = Product::where('id', $item->id)
                ->where('business_id', Auth::user()->business_id)
                ->first();

            if ($productInventory) {
                // Restamos asegurando matemáticamente que el stock no caiga en valores negativos ilegales
                $newStock = max(0, $productInventory->stock_current - $item->pivot->quantity_used);
                $productInventory->update(['stock_current' => $newStock]);
            }
        }

        // 3. Salvar el registro finalizando la operación
        $appointment->update([
            'status' => 'completed',
            'notes' => $appointment->notes . $extraNotes
        ]);

        return response()->json([
            'message' => 'Cita finalizada con éxito en GetBela. El inventario ha sido actualizado en tiempo real.',
            'appointment' => $appointment->load('consumedProducts')
        ], 200);
    }

    /**
     * ACTUALIZAR ESTADO MANUAL: Gestión de cancelaciones o inasistencias.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,confirmed,cancelled,completed,no_show']);

        $user = Auth::user();

        $appointment = Appointment::where('id', $id)
            ->where('business_id', $user->business_id)
            ->firstOrFail();

        if ($user->role === 'employee') {

            $employeeProfile = Employee::where('user_id', $user->id)->firstOrFail();

            // Si es empleado, la cita DEBE tenerlo asignado. No puede tocar citas ajenas o sin asignar.
            if ($appointment->employee_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes autorización para gestionar las citas de otros estilistas.'
                ], 403);
            }
        }
            $fechaCita = Carbon::parse($appointment->date);

        if ($fechaCita->isPast() && !$fechaCita->isToday()) {
            return response()->json([
                'message' => 'No es posible modificar o cancelar una cita que ya ha pasado.'
            ], 422);
        }

        $appointment->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Estado de la cita actualizado correctamente.',
            'appointment' => $appointment
        ], 200);
    }

    /**
     * BÚSQUEDA PÚBLICA: Buscar citas por número de teléfono del cliente.
     */
    public function searchByPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $appointments = Appointment::whereHas('client', function ($q) use ($request) {
            $q->where('phone', $request->phone);
        })
            ->with(['service.business', 'service.employee', 'service.category', 'client', 'consumedProducts'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'appointments' => $appointments
        ]);
    }
}