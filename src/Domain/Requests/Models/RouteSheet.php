<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Domain\Vehicles\Models\Vehicle;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;

#[Fillable([
    'request_id',
    'vehicle_id',
    'driver_id',
    'transport_chief_id',
    'initial_mileage',
    'final_mileage',
    'trip_status'
])]
class RouteSheet extends Model
{
    use HasFactory;

    protected $table = 'route_sheets';

    /**
     * Obtener la solicitud asociada con esta hoja de ruta.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(MobilizationRequest::class, 'request_id');
    }

    /**
     * Obtener el vehículo asignado a esta hoja de ruta.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Obtener el chofer asignado a esta hoja de ruta.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * Obtener el jefe de transporte (Usuario) que autorizó esta hoja de ruta.
     */
    public function transportChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transport_chief_id');
    }

    /**
     * Obtener las evaluaciones de viaje asociadas con esta hoja de ruta.
     */
    public function evaluations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TripEvaluation::class, 'route_sheet_id');
    }

    /**
     * Obtener la liquidación/compensación de chofer asociada con esta hoja de ruta.
     */
    public function compensation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DriverCompensation::class, 'route_sheet_id');
    }
}
