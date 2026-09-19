<?php

namespace Domain\Requests\Models;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'request_id',
    'vehicle_id',
    'driver_id',
    'transport_chief_id',
    'initial_mileage',
    'final_mileage',
    'trip_status',
    'driver_response',
    'driver_reject_reason',
    'driver_responded_at',
])]
class RouteSheet extends Model
{
    use HasFactory;

    protected $table = 'route_sheets';

    protected function casts(): array
    {
        return [
            'driver_responded_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MobilizationRequest::class, 'request_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function transportChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transport_chief_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(TripEvaluation::class, 'route_sheet_id');
    }

    public function compensation(): HasOne
    {
        return $this->hasOne(DriverCompensation::class, 'route_sheet_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteSheetStop::class, 'route_sheet_id')->orderBy('sequence');
    }

    public function fuelOrders(): HasMany
    {
        return $this->hasMany(FuelOrder::class, 'route_sheet_id');
    }

    public function deliveryActs(): HasMany
    {
        return $this->hasMany(DeliveryReceptionAct::class, 'route_sheet_id');
    }
}
