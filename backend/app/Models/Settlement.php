<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'period_type',
        'period_start',
        'period_end',
        'appointments_count',
        'total_gross',
        'total_commission',
        'business_net',
        'notes',
        'created_by',
        'paid_at',
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end' => 'date:Y-m-d',
        'appointments_count' => 'integer',
        'total_gross' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'business_net' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Detalle de la liquidación por trabajador.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    /**
     * Usuario que registró el pago.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Citas incluidas en esta liquidación.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
