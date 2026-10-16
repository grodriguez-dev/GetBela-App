<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    /**
     * LISTAR EMPLEADOS: Devuelve solo los trabajadores del salón autenticado.
     */
    public function index()
    {
        $employees = Employee::where('business_id', Auth::user()->business_id)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'message' => 'Lista de empleados recuperada con éxito.',
            'employees' => $employees
        ], 200);
    }

    /**
     * REGISTRAR EMPLEADO: Añade una nueva estilista/manicurista con su comisión.
     */
    public function store(Request $request)
    {
        // Normalizamos el teléfono del empleado al formato venezolano (+58XXXXXXXXXX).
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'regex:/^\+58\d{10}$/'],
            'commission_percentage' => 'required|numeric|min:0|max:100',
            // El interruptor del formulario frontend (booleano)
            'give_access' => 'nullable|boolean',
            // Estos campos SOLO se validan si 'give_access' es true
            'email' => 'required_if:give_access,true|nullable|email|unique:users,email',
            'password' => 'required_if:give_access,true|nullable|string|min:8',
        ]);

        $businessId = Auth::user()->business_id;
        $userId = null;

        // 2. Si el Owner decidió crearle acceso, registramos primero sus credenciales
        if ($request['give_access']) {
            $user = User::create([
                'business_id' => $businessId,
                'name'        => $request['name'],
                'email'       => $request['email'],
                'password'    => Hash::make($request['password']),
                'role'        => 'employee',
            ]);

            $userId = $user->id;
        }

        $employee = Employee::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'name' => $request->name,
            'specialty' => $request->specialty,
            'phone' => $request->phone,
            'commission_percentage' => $request->commission_percentage,
            'is_active' => true
        ]);

        return response()->json([
            'message' => 'Empleado registrado con éxito.',
            'employee' => $employee->load('user')
        ], 201);
    }

    /**
     * MOSTRAR DETALLE: Obtiene la información de un empleado específico.
     */
    public function show($id)
    {
        $employee = Employee::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        return response()->json([
            'employee' => $employee
        ], 200);
    }

    /**
     * ACTUALIZAR EMPLEADO: Modifica datos o cambia el porcentaje de comisión.
     */
    public function update(Request $request, $id)
    {
        $employee = Employee::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        // Normalizamos el teléfono del empleado al formato venezolano (+58XXXXXXXXXX).
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'phone' => ['nullable', 'string', 'regex:/^\+58\d{10}$/'],
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'required|boolean',
            'give_access' => 'nullable|boolean',
            // El email solo se valida si se va a CREAR un usuario nuevo (Escenario A)
            'email' => 'required_if:give_access,true|nullable|email|unique:users,email,' . $employee->user_id,
            'password' => 'nullable|string|min:8'
        ]);

        $businessId = Auth::user()->business_id;

        if (!$employee->user_id && $request['give_access']) {

            $request->validate(['password' => 'required|string|min:8']);

            $user = User::create([
                'business_id' => $businessId,
                'name'        => $request['name'],
                'email'       => $request['email'],
                'password'    => Hash::make($request->password),
                'role'        => 'employee',
            ]);

            $employee->user_id = $user->id;

        }elseif ($employee->user_id && !$request['give_access']) {

            $oldUserId = $employee->user_id;
            
            // Primero desvinculamos al empleado en su tabla para evitar errores de llave foránea
            $employee->user_id = null;
            $employee->save();

            User::destroy($oldUserId);

        }else {
            // Si ya tenía usuario y el dueño decidió actualizarle el correo o la contraseña
            if ($employee->user_id) {
                $user = User::find($employee->user_id);
                $user->update([
                    'name'  => $request['name'],
                    'email' => $request['email'] ?? $user->email,
                ]);

                // Si el dueño escribió una nueva contraseña en el formulario de edición, se cambia
                if ($request->filled('password')) {
                    $user->update(['password' => Hash::make($request->password)]);
                }
            }
        }

        $employee->update($request->only([
            'name', 'specialty', 'phone', 'commission_percentage', 'is_active'
        ]));

        return response()->json([
            'message' => 'Información del empleado actualizada.',
            'employee' => $employee->load('user')
        ], 200);
    }

    /**
     * ELIMINAR/DAR DE BAJA: Borra el registro físico del empleado.
     * (Gracias al 'nullOnDelete' en la migración, sus citas no se borrarán, solo quedarán sin estilista asignada).
     */
    public function destroy($id)
    {
        $employee = Employee::where('id', $id)
            ->where('business_id', Auth::user()->business_id)
            ->firstOrFail();

        $userId = $employee->user_id;

        if ($userId) {
            User::destroy($userId);
        }

        $employee->delete();

        return response()->json([
            'message' => 'Empleado eliminado del sistema correctamente.'
        ], 200);
    }
}
