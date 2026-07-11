<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'route_sheet_id',
    'applied_rate_id',
    'allowances_amount',
    'overtime_50_amount',
    'overtime_100_amount',
    'total_payout',
    'payment_receipt_url',
    'payment_status'
])]
class DriverCompensation extends Model
{
    use HasFactory;

    protected $table = 'driver_compensations';

    public $timestamps = false;

    protected $casts = [
        'allowances_amount' => 'float',
        'overtime_50_amount' => 'float',
        'overtime_100_amount' => 'float',
        'total_payout' => 'float'
    ];

    public function routeSheet(): BelongsTo
    {
        return $this->belongsTo(RouteSheet::class, 'route_sheet_id');
    }

    public function appliedRate(): BelongsTo
    {
        return $this->belongsTo(RateConfiguration::class, 'applied_rate_id');
    }
}
