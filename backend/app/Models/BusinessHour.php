<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessHour extends Model
{
    /** @use HasFactory<UserFactory> 
    *use HasFactory;
    */

    protected $fillable = [
        'business_id',
        'day_of_week',
        'opening_time',
        'closing_time',
        'is_closed'
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];
}
