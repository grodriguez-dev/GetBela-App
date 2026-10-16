<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;

class CheckExpiredAppointments extends Command
{
    // El comando se ejecutará con: php artisan appointments:check
    protected $signature = 'appointments:check';
    protected $description = 'Cancela automáticamente las citas (pending/confirmed) que no se iniciaron dentro del margen de tolerancia desde su hora de inicio.';

    public function handle()
    {
        // Cancelar las citas que no se iniciaron dentro del margen de tolerancia
        // (pending/confirmed pasado su hora de inicio + tolerancia).
        $cancelled = Appointment::cancelNoShows();

        // NOTA: las citas en_progress NO se cancelan aquí. Si se pasan de su hora
        // de fin deben permanecer activas para registrar el tiempo extra hasta que
        // el estilista pulse "Completar".

        $this->info("Estados de citas actualizados. Citas canceladas por inasistencia: {$cancelled}.");
    }
}
