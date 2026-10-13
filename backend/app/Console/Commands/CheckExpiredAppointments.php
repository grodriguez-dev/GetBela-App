<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use Carbon\Carbon;

class CheckExpiredAppointments extends Command
{
    // El comando se ejecutará con: php artisan appointments:check
    protected $signature = 'appointments:check';
    protected $description = 'Verifica citas vencidas o retrasadas para actualizar su estado de forma automática';

    public function handle()
    {
        $now = Carbon::now();
        $todayStr = $now->format('Y-m-d');

        // -----------------------------------------------------------------
        // REGLA 1: Tolerancia de 15 minutos para iniciar o cancelar
        // -----------------------------------------------------------------
        $fifteenMinutesAgo = $now->copy()->subMinutes(15);

        Appointment::where('status', 'pending')
            ->where('date', $todayStr)
            ->get()
            ->each(function ($appointment) use ($fifteenMinutesAgo) {
                $start = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->start_time);

                if ($start->lte($fifteenMinutesAgo)) {
                    $appointment->update([
                        'status' => 'cancelled',
                        'notes' => 'Cancelada automáticamente: El cliente no se presentó tras 15 min de tolerancia.'
                    ]);
                }
            });

        // -----------------------------------------------------------------
        // REGLA 2: Pasar a estado "Excedida" en el sistema de tiempo
        // -----------------------------------------------------------------
        Appointment::where('status', 'in_progress')
            ->where('date', $todayStr)
            ->get()
            ->each(function ($appointment) use ($now) {
                $end = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->end_time);

                if ($end->lt($now)) {
                    $appointment->update(['status' => 'cancelled']);
                }
            });

        $this->info('Estados de citas actualizados correctamente.');
    }
}
