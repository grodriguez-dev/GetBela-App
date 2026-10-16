<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Appointment extends Model
{
    /** @use HasFactory<ApointmentFactory>*/
    use HasFactory;

    protected $fillable = [
        'business_id',
        'service_id',
        'employee_id',
        'client_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'total_price',
        'overtime_seconds',
    ];

    protected $casts = [
        // Se serializa como fecha de calendario (YYYY-MM-DD), sin hora ni zona horaria,
        // para evitar desfases de un día según la zona del navegador.
        'date' => 'date:Y-m-d',
        'total_price' => 'decimal:2',
        'overtime_seconds' => 'integer',
    ];

    /**
     * Minutos de tolerancia para dar por perdida una cita que no se inició.
     */
    public const NO_SHOW_TOLERANCE_MINUTES = 20;

    /**
     * La cita pertenece a un negocio específico.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * La cita requiere un servicio específico del catálogo.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Los productos consumidos reales en esta cita específica.
     */
    public function consumedProducts()
    {
        return $this->belongsToMany(Product::class, 'appointment_product')
                    ->withPivot('quantity_used')
                    ->withTimestamps();
    }

    /**
     * El clientes real en esta cita específica.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * CANCELAR NO-SHOWS: cancela automáticamente las citas que siguen en
     * pending/confirmed una vez superado el margen de tolerancia desde su hora
     * de inicio, es decir, que nunca llegaron a empezar.
     *
     * @param  int|null  $businessId  Limita la limpieza a un negocio (null = todos).
     * @param  int       $toleranceMinutes  Minutos de tolerancia tras la hora de inicio.
     * @return int  Número de citas canceladas.
     */
    public static function cancelNoShows(?int $businessId = null, int $toleranceMinutes = self::NO_SHOW_TOLERANCE_MINUTES): int
    {
        $now = Carbon::now();

        $query = static::whereIn('status', ['pending', 'confirmed'])
            // Las citas de fechas futuras todavía no pueden ser inasistencias.
            ->whereDate('date', '<=', $now->format('Y-m-d'));

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $cancelled = 0;

        $query->get()->each(function (self $appointment) use ($now, $toleranceMinutes, &$cancelled) {
            if (!$appointment->date || !$appointment->start_time) {
                return;
            }

            $start = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->start_time);

            if ($start->copy()->addMinutes($toleranceMinutes)->lte($now)) {
                $appointment->update([
                    'status' => 'cancelled',
                    'notes' => trim(
                        ($appointment->notes ? $appointment->notes . "\n" : '')
                        . "Cancelada automáticamente: no se inició dentro de los {$toleranceMinutes} min de tolerancia."
                    ),
                ]);
                $cancelled++;
            }
        });

        return $cancelled;
    }
}
