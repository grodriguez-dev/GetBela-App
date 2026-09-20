<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'appointment_id',
        'message',
        'read_at',
    ];

    // Relación: Una notificación pertenece a un negocio
    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
