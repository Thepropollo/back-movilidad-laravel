<?php

namespace Domain\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'driver_id',
    'date',
    'check_in_time',
    'check_out_time',
    'notes'
])]
class DailyAttendance extends Model
{
    use HasFactory;

    protected $table = 'daily_attendances';

    public $timestamps = false;

    /**
     * Obtener el chofer asociado a esta asistencia diaria.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
