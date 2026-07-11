<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'commercial_name',
    'ruc',
    'address',
    'active_agreement'
])]
class ServiceStation extends Model
{
    use HasFactory;

    protected $table = 'service_stations';

    public $timestamps = false;

    protected $casts = [
        'active_agreement' => 'boolean'
    ];

    public function fuelOrders(): HasMany
    {
        return $this->hasMany(FuelOrder::class, 'station_id');
    }
}
