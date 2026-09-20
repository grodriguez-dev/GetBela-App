<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory>*/
    use HasFactory;

    protected $fillable = [
        'business_id',
        'category_id',
        'employee_id',
        'name',
        'description',
        'image_url',
        'duration_minutes',
        'price',
        'is_active'
    ];

    /**
     * Conversión automática de tipos de datos al salir de la BD.
     */
    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * El servicio pertenece a un salón o trabajador independiente.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * El servicio requiere un empleado específico.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Los productos (insumos) que consume este servicio por defecto.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_service')
                    ->withPivot('quantity_default')
                    ->withTimestamps();
    }

    /**
     * La Categoria en cual entra ese servicio.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
