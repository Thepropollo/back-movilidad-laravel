<?php

namespace Domain\Vehicles\Models;

use Domain\Requests\Models\RouteSheet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'plate',
    'brand',
    'model',
    'year',
    'color',
    'fuel_type',
    'current_mileage',
    'next_oil_change_mileage',
    'operational_status',
    'registration_number',
])]
class Vehicle extends Model
{
    use HasFactory;

    protected $table = 'vehicles';

    /**
     * Obtener los documentos legales del vehículo.
     */
    public function legalDocuments(): HasMany
    {
        return $this->hasMany(VehicleLegalDocument::class, 'vehicle_id');
    }

    /**
     * Obtener las hojas de ruta en las que se ha asignado este vehículo.
     */
    public function routeSheets(): HasMany
    {
        return $this->hasMany(RouteSheet::class, 'vehicle_id');
    }
}
