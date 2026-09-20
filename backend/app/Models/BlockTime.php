<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockTime extends Model
{
    /** @use HasFactory<UserFactory> 
    *use HasFactory;
    */

    protected $fillable = [
        'business_id',
        'service_id',
        'date',
        'start_time',
        'end_time',
        'reason'
    ];

    /**
     * Un bloqueo puede pertenecer a un empleado específico.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(service::class);
    }
}