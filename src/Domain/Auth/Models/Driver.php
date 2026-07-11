<?php

namespace Domain\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Domain\Requests\Models\RouteSheet;

#[Fillable([
    'user_id',
    'contract_type',
    'is_available'
])]
class Driver extends Model
{
    use HasFactory;

    protected $table = 'drivers';

    public $timestamps = false;

    /**
     * Obtener el usuario asociado con este chofer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtener las licencias asociadas con este chofer.
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(DriverLicense::class, 'driver_id');
    }

    /**
     * Obtener las asistencias diarias registradas para este chofer.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class, 'driver_id');
    }

    /**
     * Obtener las hojas de ruta en las que se ha asignado este chofer.
     */
    public function routeSheets(): HasMany
    {
        return $this->hasMany(RouteSheet::class, 'driver_id');
    }
}
