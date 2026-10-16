<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cancelación automática de inasistencias: cada minuto revisa las citas
// pending/confirmed que superaron su hora de inicio + tolerancia (20 min).
// Requiere que el scheduler esté corriendo (cron: * * * * * php artisan schedule:run).
Schedule::command('appointments:check')->everyMinute()->withoutOverlapping();
