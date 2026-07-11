<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Domain\Auth\Models\User;

#[Fillable([
    'order_code',
    'route_sheet_id',
    'station_id',
    'transport_chief_id',
    'dispatched_fuel_type',
    'authorized_gallons',
    'actual_dispatched_gallons',
    'total_amount_paid',
    'order_status',
    'dispatch_date'
])]
class FuelOrder extends Model
{
    use HasFactory;

    protected $table = 'fuel_orders';

    public $timestamps = false;

    protected $casts = [
        'authorized_gallons' => 'float',
        'actual_dispatched_gallons' => 'float',
        'total_amount_paid' => 'float',
        'dispatch_date' => 'datetime',
        'created_at' => 'datetime'
    ];

    public function routeSheet(): BelongsTo
    {
        return $this->belongsTo(RouteSheet::class, 'route_sheet_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(ServiceStation::class, 'station_id');
    }

    public function transportChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transport_chief_id');
    }
}
