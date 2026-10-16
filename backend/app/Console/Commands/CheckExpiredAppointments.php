<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;

class CheckExpiredAppointments extends Command
{
    // El comando se ejecutará con: php artisan appointments:check
    protected $signature = 'appointments:check';
    protected $description = 'Resuelve las citas que llegaron a su hora de finalización sin iniciarse (pending -> cancelled, confirmed -> no_show).';

    public function handle()
    {
        $result = Appointment::autoResolveExpired();

        // NOTA: las citas en_progress NO se tocan aquí. Si se pasan de su hora de
        // fin deben permanecer activas para registrar el tiempo extra hasta que
        // el estilista pulse "Completar".

        $this->info("Citas resueltas -> canceladas: {$result['cancelled']}, no asistió: {$result['no_show']}.");
    }
}
