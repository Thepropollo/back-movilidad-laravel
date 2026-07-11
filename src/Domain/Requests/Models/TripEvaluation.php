<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Domain\Auth\Models\User;

#[Fillable([
    'route_sheet_id',
    'passenger_id',
    'driver_rating',
    'vehicle_rating',
    'comments'
])]
class TripEvaluation extends Model
{
    use HasFactory;

    protected $table = 'trip_evaluations';

    public $timestamps = false;

    protected $casts = [
        'driver_rating' => 'integer',
        'vehicle_rating' => 'integer',
        'created_at' => 'datetime'
    ];

    public function routeSheet(): BelongsTo
    {
        return $this->belongsTo(RouteSheet::class, 'route_sheet_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }
}
