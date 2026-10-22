<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Panel del super admin: resumen de negocios registrados, citas completadas
     * en el período (día/semana/mes), empleados con y sin acceso, y estado de
     * acceso de cada negocio a la plataforma.
     */
    public function index(Request $request)
    {
        $period = $request->input('period', 'today');
        if (!in_array($period, ['today', 'week', 'month'], true)) {
            $period = 'today';
        }

        $search = trim((string) $request->input('search', ''));

        [$from, $to] = $this->periodRange($period);
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');

        // Citas completadas por negocio dentro del período (una sola consulta).
        $completedByBusiness = Appointment::query()
            ->where('status', 'completed')
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('business_id, COUNT(*) as total')
            ->groupBy('business_id')
            ->pluck('total', 'business_id');

        $businesses = Business::with([
            'users' => function ($q) {
                $q->where('role', 'owner')
                    ->select('id', 'name', 'email', 'role', 'business_id');
            },
            'employees' => function ($q) {
                $q->select('id', 'business_id', 'user_id', 'name', 'specialty', 'is_active');
            },
        ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            })
            ->orderBy('name')
            ->get()
            ->map(function (Business $business) use ($completedByBusiness) {
                $owner = $business->users->first();
                $employees = $business->employees;

                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'logo' => $business->logo,
                    'created_at' => $business->created_at,
                    'is_active' => (bool) $business->is_active,
                    'suspended_at' => $business->suspended_at,
                    'suspension_reason' => $business->suspension_reason,
                    'owner' => $owner ? [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                    ] : null,
                    'completed_appointments' => (int) ($completedByBusiness[$business->id] ?? 0),
                    'employees_total' => $employees->count(),
                    'employees_with_access' => $employees->whereNotNull('user_id')->count(),
                    'employees_without_access' => $employees->whereNull('user_id')->count(),
                    'employees' => $employees->map(fn (Employee $employee) => [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'specialty' => $employee->specialty,
                        'is_active' => (bool) $employee->is_active,
                        'has_access' => $employee->user_id !== null,
                    ])->values(),
                ];
            })
            ->values();

        $totals = [
            'businesses' => Business::count(),
            'active_businesses' => Business::where('is_active', true)->count(),
            'suspended_businesses' => Business::where('is_active', false)->count(),
            // El super admin no se cuenta como usuario registrado del dashboard.
            'users' => User::where('role', '!=', 'admin')->count(),
            'owners' => User::where('role', 'owner')->count(),
            'employees' => Employee::count(),
            'employees_with_access' => Employee::whereNotNull('user_id')->count(),
            'employees_without_access' => Employee::whereNull('user_id')->count(),
            'completed_appointments' => Appointment::where('status', 'completed')
                ->whereBetween('date', [$fromDate, $toDate])
                ->count(),
        ];

        return response()->json([
            'period' => [
                'key' => $period,
                'from' => $fromDate,
                'to' => $toDate,
                'label' => $this->periodLabel($period, $from, $to),
            ],
            'totals' => $totals,
            'businesses' => $businesses,
        ]);
    }

    /**
     * Activa o suspende el acceso de un negocio (y de todo lo asociado a él).
     */
    public function updateAccess(Request $request, Business $business)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
            'suspension_reason' => 'nullable|string|max:255',
        ]);

        $business->update([
            'is_active' => $data['is_active'],
            'suspended_at' => $data['is_active'] ? null : now(),
            'suspension_reason' => $data['is_active']
                ? null
                : ($data['suspension_reason'] ?? null),
        ]);

        return response()->json([
            'message' => $data['is_active']
                ? 'Acceso al negocio reactivado correctamente.'
                : 'Acceso al negocio suspendido correctamente.',
            'business' => $business->fresh(),
        ]);
    }

    /**
     * Rango de fechas del período en la zona horaria del negocio.
     *
     * - today: día de hoy.
     * - week: semana actual (lunes a domingo).
     * - month: mes calendario actual.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'week' => [
                $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ],
            'month' => [
                $now->copy()->startOfMonth()->startOfDay(),
                $now->copy()->endOfMonth()->endOfDay(),
            ],
            default => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ],
        };
    }

    private function periodLabel(string $period, Carbon $from, Carbon $to): string
    {
        $months = [
            1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];

        return match ($period) {
            'week' => 'Semana del ' . $from->format('d/m/Y') . ' al ' . $to->format('d/m/Y'),
            'month' => 'Mes de ' . $months[(int) $from->format('n')] . ' ' . $from->format('Y'),
            default => 'Hoy, ' . $from->format('d/m/Y'),
        };
    }
}
