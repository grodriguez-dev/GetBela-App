<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    /** @use HasFactory<UserFactory> 
    *use HasFactory;
    */

    protected $fillable = [
        'business_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Una venta pertenece a un producto del catálogo.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
