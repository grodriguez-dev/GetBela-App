<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'name',
        'specialty',
        'phone',
        'commission_percentage',
        'is_active'
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * Obtener todas las citas asociadas a este especialista.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Un empleado puede tener un usuario asignado para iniciar sesión.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un empleado tiene un negocio asignado.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
