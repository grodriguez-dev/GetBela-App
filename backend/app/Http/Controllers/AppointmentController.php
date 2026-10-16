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
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Minutos de margen que se añaden al final de una cita para considerar que
     * el empleado sigue ocupado (limpieza, preparación del siguiente cliente, etc.).
     * Durante ese rango NO se puede agendar otra cita para ese empleado.
     */
    private const EMPLOYEE_BUFFER_MINUTES = 5;

    /**
     * Segundos de más que ha durado (o duró) una cita con respecto a su hora de
     * fin programada. Devuelve 0 si aún no se ha pasado del fin previsto.
     */
    private function overtimeSeconds(Appointment $appointment, ?Carbon $at = null): int
    {
        if (!$appointment->date || !$appointment->end_time) {
            return 0;
        }

        $at = $at ?? Carbon::now();
        $scheduledEnd = Carbon::parse(
            $appointment->date->format('Y-m-d') . ' ' . $appointment->end_time,
            config('app.timezone')
        );

        return $at->greaterThan($scheduledEnd)
            ? (int) abs($at->diffInSeconds($scheduledEnd))
            : 0;
    }

    /**
     * ¿La cita ya alcanzó (o superó) su hora de finalización aproximada?
     * Se usa para bloquear confirmar/iniciar una cita vencida.
     */
    private function hasPassedEndTime(Appointment $appointment, ?Carbon $at = null): bool
    {
        if (!$appointment->date || !$appointment->end_time) {
            return false;
        }

        $at = $at ?? Carbon::now();
        $scheduledEnd = Carbon::parse(
            $appointment->date->format('Y-m-d') . ' ' . $appointment->end_time,
            config('app.timezone')
        );

        return $scheduledEnd->lte($at);
    }

    /**
     * LISTAR CITAS: Recupera la agenda inyectando el servicio,
     * el estilista asignado y los insumos reservados.
     */
    public function index()
    {
        $businessId = Auth::user()->business_id;

        // 1. CIERRE AUTOMÁTICO: las citas pending/confirmed que alcanzaron su hora
        // de finalización sin iniciarse (pending -> cancelled, confirmed -> no_show).
        Appointment::autoResolveExpired($businessId);

        // 2. RETORNAR EL LISTADO ACTUALIZADO
        $appointments = Appointment::where('business_id', $businessId)
            ->with(['service.employee', 'client', 'consumedProducts'])
            ->orderBy('created_at', 'desc')
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();
            
        return response()->json($appointments, 200);
    }

    /**
     * AGENDAR CITA: Valida jornada laboral, ejecuta escudo anti-solapamiento
     * y hereda la "receta de insumos" automáticamente.
     */
    public function store(Request $request)
    {
        // Normalizamos el teléfono del cliente al formato venezolano (+58XXXXXXXXXX).
        $request->merge([
            'client_phone' => Phone::normalize($request->input('client_phone')),
        ]);

        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'service_id' => 'required|exists:services,id',
            'client_name' => 'required|string|max:255',
            'client_phone' => ['required', 'string', 'regex:/^\+58\d{10}$/'],
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
        $now = Carbon::now();

        if ($startTime->lt($now->copy()->subMinutes(15)) && Carbon::parse($request->date)->isToday()) {
            return response()->json([
                'message' => 'La hora seleccionada ya pasó. Elige un horario futuro para hoy.',
                'errors' => [
                    'start_time' => ['La cita no puede crearse en un horario ya vencido.']
                ]
            ], 422);
        }

        // Formateamos los strings limpios que se guardarán en las columnas TIME de la BD
        $requestedStartTime = $startTime->format('H:i:s');
        $calculatedEndTime = $endTime->format('H:i:s');

        // 2. ESCUDO 1: Validar jornada laboral
        $dayOfWeek = Carbon::parse($request->date)->dayOfWeekIso;
        $possibleDayValues = [$dayOfWeek];

        if ($dayOfWeek === 7) {
            $possibleDayValues[] = 0;
        }

        if ($dayOfWeek === 0) {
            $possibleDayValues[] = 7;
        }

        $businessHour = BusinessHour::where('business_id', $businessId)
            ->whereIn('day_of_week', array_values(array_unique($possibleDayValues)))
            ->orderByRaw('CASE WHEN day_of_week = ? THEN 0 ELSE 1 END', [$dayOfWeek])
            ->first();

        if (!$businessHour || $businessHour->is_closed) {
            return response()->json(['message' => 'El negocio se encuentra cerrado el día solicitado.'], 422);
        }

        // Creamos instancias Carbon de los límites usando el mismo día de la cita
        $openTime = Carbon::parse($request->date . ' ' . $businessHour->opening_time);
        $closeTime = Carbon::parse($request->date . ' ' . $businessHour->closing_time);

        // Comparación matemática cronológica estricta (detecta cruces de día y medianoche)
        // Permitir que termine exactamente a la hora de cierre
        if ($startTime->lt($openTime) || $endTime->gt($closeTime)) {
            return response()->json(['message' => 'La cita se sale del horario de atención del establecimiento.'], 422);
        }

        // 3. ESCUDO 2: Anti-Solapamiento (mismo servicio O mismo empleado ocupado)
        // IMPORTANTE: No filtramos por empleado en SQL, porque las citas creadas antes de
        // guardar employee_id lo tienen en NULL. Resolvemos el empleado efectivo en PHP
        // (columna employee_id o, si falta, el empleado del servicio de la cita existente).
        $employeeId = $service->employee_id;
        $serviceId = $service->id;

        $existingAppointments = Appointment::withoutGlobalScopes()
            ->with('service')
            ->where('business_id', $businessId)
            ->whereDate('date', $request->date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->get();

        $overlapping = false;
        $conflict = null;

        foreach ($existingAppointments as $existing) {
            // Empleado efectivo de la cita existente.
            $existingEmployeeId = $existing->employee_id
                ?? optional($existing->service)->employee_id;

            $sameService = $serviceId && (int) $existing->service_id === (int) $serviceId;
            $sameEmployee = $employeeId && $existingEmployeeId
                && (int) $existingEmployeeId === (int) $employeeId;

            if (!$sameService && !$sameEmployee) {
                continue;
            }

            // Parseamos los tiempos de la base de datos usando la misma fecha del request para consistencia
            $existingStart = Carbon::parse($request->date . ' ' . $existing->start_time);
            $existingEnd = Carbon::parse($request->date . ' ' . $existing->end_time);

            // Si el conflicto es por EMPLEADO OCUPADO, el rango ocupado se extiende
            // EMPLOYEE_BUFFER_MINUTES al final (el empleado sigue ocupado esos minutos).
            $existingBusyEnd = $sameEmployee
                ? $existingEnd->copy()->addMinutes(self::EMPLOYEE_BUFFER_MINUTES)
                : $existingEnd;

            // Intersección de rangos semiabiertos [inicio, fin):
            // la nueva cita empieza antes de que termine el rango ocupado Y termina después de que empiece.
            if ($startTime->lt($existingBusyEnd) && $endTime->gt($existingStart)) {
                $overlapping = true;
                $conflict = $existing;
                break;
            }
        }

        if ($overlapping) {
            // Resolvemos el empleado para informar quién está ocupado y su próxima disponibilidad.
            $employee = null;
            if ($employeeId) {
                $employee = Employee::find($employeeId);
            }
            if (!$employee && $conflict && $conflict->employee_id) {
                $employee = Employee::find($conflict->employee_id);
            }
            if (!$employee && $conflict && $conflict->service) {
                $conflictEmployeeId = $conflict->service->employee_id;
                if ($conflictEmployeeId) {
                    $employee = Employee::find($conflictEmployeeId);
                }
            }

            $availableAt = $conflict
                ? Carbon::parse($request->date . ' ' . $conflict->end_time)->addMinutes(self::EMPLOYEE_BUFFER_MINUTES)->format('H:i')
                : null;

            if ($employee && $availableAt) {
                return response()->json([
                    'message' => "El empleado {$employee->name} está ocupado. Estará disponible a las {$availableAt}.",
                ], 422);
            }

            return response()->json([
                'message' => $availableAt
                    ? "El horario solicitado se encuentra ocupado. Intenta después de las {$availableAt}."
                    : 'El horario solicitado se encuentra ocupado en ese rango de tiempo.',
            ], 422);
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
            'employee_id' => $service->employee_id,
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

        $dateStr = Carbon::parse($date)->format('Y-m-d');
        $service = Service::findOrFail($serviceId);
        $employeeId = $service->employee_id;
        $start = Carbon::parse($dateStr . ' ' . $startTime);
        $end = $start->copy()->addMinutes($service->duration_minutes ?? 60); // Duración real del servicio
        $startTimeStr = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        $businessId = $appointment->business_id; // Mantenemos el mismo negocio

        // ------------------------------------------------------------------------
        // 🛡️ ESCUDO 1: Validar dentro de la Jornada Laboral (Omitido aquí por brevedad, o añade tu lógica)
        // ------------------------------------------------------------------------

        // ------------------------------------------------------------------------
        // 🛡️ ESCUDO 2: Anti-Solapamiento (mismo servicio O mismo empleado ocupado)
        // Igual que en store(): resolvemos el empleado efectivo en PHP para cubrir
        // citas antiguas con employee_id = NULL.
        // ------------------------------------------------------------------------
        $existingAppointments = Appointment::withoutGlobalScopes()
            ->with('service')
            ->where('business_id', $businessId)
            ->whereDate('date', $dateStr)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where('id', '!=', $appointment->id)
            ->get();

        $conflict = null;
        foreach ($existingAppointments as $existing) {
            $existingEmployeeId = $existing->employee_id
                ?? optional($existing->service)->employee_id;

            $sameService = $serviceId && (int) $existing->service_id === (int) $serviceId;
            $sameEmployee = $employeeId && $existingEmployeeId
                && (int) $existingEmployeeId === (int) $employeeId;

            if (!$sameService && !$sameEmployee) {
                continue;
            }

            $existingStart = Carbon::parse($dateStr . ' ' . $existing->start_time);
            $existingEnd = Carbon::parse($dateStr . ' ' . $existing->end_time);

            // Mismo margen que en store(): el rango ocupado del empleado incluye +5 min.
            $existingBusyEnd = $sameEmployee
                ? $existingEnd->copy()->addMinutes(self::EMPLOYEE_BUFFER_MINUTES)
                : $existingEnd;

            if ($start->lt($existingBusyEnd) && $end->gt($existingStart)) {
                $conflict = $existing;
                break;
            }
        }

        if ($conflict) {
            $employee = null;
            if ($employeeId) {
                $employee = Employee::find($employeeId);
            }
            if (!$employee && $conflict->employee_id) {
                $employee = Employee::find($conflict->employee_id);
            }
            if (!$employee && $conflict->service) {
                $conflictEmployeeId = $conflict->service->employee_id;
                if ($conflictEmployeeId) {
                    $employee = Employee::find($conflictEmployeeId);
                }
            }

            $availableAt = Carbon::parse($dateStr . ' ' . $conflict->end_time)->addMinutes(self::EMPLOYEE_BUFFER_MINUTES)->format('H:i');
            $msg = $employee
                ? "El empleado {$employee->name} está ocupado. Estará disponible a las {$availableAt}."
                : "El horario solicitado se encuentra ocupado. Intenta después de las {$availableAt}.";

            return response()->json([
                'message' => $msg
            ], 422);
        }

        // ------------------------------------------------------------------------
        // 🛡️ ESCUDO 3: Bloqueos Administrativos (Manuales o Globales)
        // ------------------------------------------------------------------------
        $isBlocked = BlockTime::where('business_id', $businessId)
            ->whereDate('date', $dateStr)
            ->where(function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId) // Bloqueo específico
                    ->orWhereNull('service_id');       // Bloqueo global del salón
            })
            ->where(function ($query) use ($startTimeStr, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTimeStr);
            })
            ->exists();

        if ($isBlocked) {
            return response()->json([
                'message' => 'El horario solicitado se encuentra bloqueado por la administración (Ausencia, almuerzo o descanso).'
            ], 422);
        }

        // Si pasa todos los escudos, actualizamos la cita
        $appointment->update(array_merge($validated, [
            'end_time' => $endTime,
            'employee_id' => $service->employee_id,
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

        // No se puede iniciar una cita que ya llegó a su hora de finalización.
        if ($this->hasPassedEndTime($appointment)) {
            return response()->json([
                'message' => 'No se puede iniciar la cita: ya pasó su hora de finalización aproximada.'
            ], 422);
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
        $overtimeSeconds = $this->overtimeSeconds($appointment, $now);

        $extraNotes = "";
        if ($overtimeSeconds >= 60) {
            $minutesExceeded = intdiv($overtimeSeconds, 60);
            $extraNotes = "\n[Cita finalizada con {$minutesExceeded} minutos de retraso]";
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
            'overtime_seconds' => $overtimeSeconds,
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
        $request->validate(['status' => 'required|in:pending,confirmed,cancelled,completed,in_progress,no_show']);

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

        $newStatus = $request->status;
        $attributes = ['status' => $newStatus];

        // No se puede confirmar ni iniciar una cita que ya llegó a su hora de
        // finalización aproximada (estrictamente a esa hora).
        if (in_array($newStatus, ['confirmed', 'in_progress'], true) && $this->hasPassedEndTime($appointment)) {
            return response()->json([
                'message' => 'No se puede ' . ($newStatus === 'confirmed' ? 'confirmar' : 'iniciar') . ' la cita: ya pasó su hora de finalización aproximada.'
            ], 422);
        }

        // Al completar una cita guardamos cuánto se excedió respecto a su hora de
        // fin programada (0 = sin tiempo extra). Solo la primera vez que se completa.
        if ($newStatus === 'completed' && $appointment->status !== 'completed') {
            $attributes['overtime_seconds'] = $this->overtimeSeconds($appointment);
        }

        $appointment->update($attributes);

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
        // Normalizamos el teléfono buscado para que coincida con el formato guardado (+58...).
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
        ]);

        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+58\d{10}$/'],
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