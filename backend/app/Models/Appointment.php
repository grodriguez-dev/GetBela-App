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
        'settlement_id',
    ];

    protected $casts = [
        // Se serializa como fecha de calendario (YYYY-MM-DD), sin hora ni zona horaria,
        // para evitar desfases de un día según la zona del navegador.
        'date' => 'date:Y-m-d',
        'total_price' => 'decimal:2',
        'overtime_seconds' => 'integer',
    ];

    /**
     * Minutos de antelación con los que se permite iniciar una cita.
     * Es estricto: solo desde exactamente esta cantidad de minutos antes del inicio.
     */
    public const START_EARLY_MINUTES = 5;

    /**
     * Instantes exactos (inicio, fin y apertura de inicio) enviados al frontend
     * para que el cálculo de hora NO dependa de la zona horaria del dispositivo,
     * sino de la zona del negocio (config('app.timezone')). El frontend solo los
     * compara con Date.now().
     */
    protected $appends = ['start_at', 'end_at', 'start_window_at'];

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
     * Liquidación en la que fue incluida esta cita (null = pendiente de pago).
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    /**
     * Construye un instante exacto (zona del negocio) a partir de la fecha de la
     * cita y una hora concreta. Devuelve null si falta algún dato.
     */
    private function buildInstant(?string $time): ?Carbon
    {
        if (!$this->date || !$time) {
            return null;
        }

        return Carbon::parse(
            $this->date->format('Y-m-d') . ' ' . $time,
            config('app.timezone')
        );
    }

    /**
     * Instante real de inicio de la cita (zona del negocio).
     */
    public function startAt(): ?Carbon
    {
        return $this->buildInstant($this->start_time);
    }

    /**
     * Instante real de finalización de la cita (zona del negocio).
     */
    public function endAt(): ?Carbon
    {
        return $this->buildInstant($this->end_time);
    }

    /**
     * Instante en que se abre la ventana para iniciar la cita
     * (START_EARLY_MINUTES minutos antes del inicio).
     */
    public function startWindowAt(): ?Carbon
    {
        return $this->startAt()?->copy()->subMinutes(self::START_EARLY_MINUTES);
    }

    /**
     * Serialización ISO 8601 (con desplazamiento de la zona del negocio) para
     * que el frontend compare con Date.now() sin importar el dispositivo.
     */
    public function getStartAtAttribute(): ?string
    {
        return $this->startAt()?->toIso8601String();
    }

    public function getEndAtAttribute(): ?string
    {
        return $this->endAt()?->toIso8601String();
    }

    public function getStartWindowAtAttribute(): ?string
    {
        return $this->startWindowAt()?->toIso8601String();
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
