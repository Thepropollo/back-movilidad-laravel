<?php

namespace Domain\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'driver_id',
    'license_type',
    'current_points',
    'expiration_date'
])]
class DriverLicense extends Model
{
    use HasFactory;

    protected $table = 'driver_licenses';

    public $timestamps = false;

    /**
     * Obtener el chofer asociado a esta licencia.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
