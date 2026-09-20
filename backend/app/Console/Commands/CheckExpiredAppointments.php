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
        $timeStr = $now->format('H:i:s');

        // -----------------------------------------------------------------
        // REGLA 1: Tolerancia de 15 minutos para iniciar o cancelar
        // -----------------------------------------------------------------
        // Buscamos citas pendientes de hoy que debieron empezar hace más de 15 minutos
        $fifteenMinutesAgo = $now->copy()->subMinutes(15)->format('H:i:s');

        Appointment::where('status', 'pending')
            ->where('date', $todayStr)
            ->where('start_time', '<=', $fifteenMinutesAgo)
            ->update(['status' => 'cancelled', 'notes' => 'Cancelada automáticamente: El cliente no se presentó tras 15 min de tolerancia.']);

        // -----------------------------------------------------------------
        // REGLA 2: Pasar a estado "Excedida" en el sistema de tiempo
        // -----------------------------------------------------------------
        // Citas en progreso cuya hora de finalización ya pasó
        Appointment::where('status', 'in_progress')
            ->where('date', $todayStr)
            ->where('end_time', '<', $timeStr)
            ->update(['status' => 'cancelled']); // Nota: En el frontend pintaremos visualmente como "Excedida en Rojo" cualquier cita 'in_progress' cuya hora ya venció.

        $this->info('Estados de citas actualizados correctamente.');
    }
}
