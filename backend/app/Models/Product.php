<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'brand',
        'description',
        'stock_current',
        'stock_min',
        'purchase_price',
        'sale_price',
        'type'
    ];

    protected $casts = [
        'stock_current' => 'integer',
        'stock_min' => 'integer',
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    /**
     * El producto pertenece a un negocio específico.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}