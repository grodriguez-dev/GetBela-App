<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'employee_id',
        'employee_name',
        'commission_percentage',
        'appointments_count',
        'gross_generated',
        'commission_payout',
        'business_net',
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:2',
        'appointments_count' => 'integer',
        'gross_generated' => 'decimal:2',
        'commission_payout' => 'decimal:2',
        'business_net' => 'decimal:2',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
