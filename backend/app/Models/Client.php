<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
    ];

    /**
     * Relación: Un cliente pertenece a un negocio (Tenant)
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Relación: Un cliente puede tener muchas citas historial
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}