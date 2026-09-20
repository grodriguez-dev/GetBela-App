<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'slug',
        'type',
        'modality',
        'phone',
        'address',
        'logo',
        'description'
    ];

    /**
     * Un negocio tiene muchos usuarios (dueños y empleados).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Un negocio ofrece múltiples servicios en su menú.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Un negocio tiene muchas citas agendadas.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Un negocio tiene configurados sus horarios de atención semanal.
     */
    public function hours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    /**
     * Un negocio posee un inventario de productos.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * obtener los empleados.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
