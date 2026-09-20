<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<ApointmentFactory>*/
    use HasFactory;

    protected $fillable = [
        'business_id',
        'service_id',
        'client_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'total_price'
    ];

    protected $casts = [
        'date' => 'date',
        'total_price' => 'decimal:2',
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

}
