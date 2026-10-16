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
     * CIERRE AUTOMÁTICO POR VENCIMIENTO: resuelve las citas que llegaron a su
     * hora de finalización aproximada sin iniciarse.
     *
     *   - pending  -> cancelled  (nunca se confirmó)
     *   - confirmed -> no_show   (se confirmó pero el cliente no llegó a empezar)
     *
     * Es estricto: se aplica exactamente al alcanzar la hora de finalización.
     *
     * @param  int|null  $businessId  Limita la limpieza a un negocio (null = todos).
     * @return array{cancelled: int, no_show: int}  Conteo por tipo de resolución.
     */
    public static function autoResolveExpired(?int $businessId = null): array
    {
        $now = Carbon::now();

        $query = static::whereIn('status', ['pending', 'confirmed'])
            // Las citas de fechas futuras todavía no pueden vencerse.
            ->whereDate('date', '<=', $now->format('Y-m-d'));

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $cancelled = 0;
        $noShow = 0;

        $query->get()->each(function (self $appointment) use ($now, &$cancelled, &$noShow) {
            if (!$appointment->date || !$appointment->end_time) {
                return;
            }

            $end = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->end_time);

            // Estrictamente al alcanzar (o superar) la hora de finalización.
            if ($end->lte($now)) {
                if ($appointment->status === 'confirmed') {
                    $newStatus = 'no_show';
                    $note = 'Marcada automáticamente como "no asistió": llegó su hora de finalización sin iniciarse.';
                    $noShow++;
                } else {
                    $newStatus = 'cancelled';
                    $note = 'Cancelada automáticamente: no se confirmó antes de su hora de finalización.';
                    $cancelled++;
                }

                $appointment->update([
                    'status' => $newStatus,
                    'notes' => trim(
                        ($appointment->notes ? $appointment->notes . "\n" : '') . $note
                    ),
                ]);
            }
        });

        return ['cancelled' => $cancelled, 'no_show' => $noShow];
    }
}
