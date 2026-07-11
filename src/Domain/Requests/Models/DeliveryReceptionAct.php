<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Domain\Auth\Models\User;

#[Fillable([
    'route_sheet_id',
    'mechanic_or_guard_id',
    'registration_type',
    'fuel_level',
    'checkpoint_mileage',
    'general_observations'
])]
class DeliveryReceptionAct extends Model
{
    use HasFactory;

    protected $table = 'delivery_reception_acts';

    public $timestamps = false;

    public function routeSheet(): BelongsTo
    {
        return $this->belongsTo(RouteSheet::class, 'route_sheet_id');
    }

    public function mechanicOrGuard(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_or_guard_id');
    }

    public function checklistDetails(): HasMany
    {
        return $this->hasMany(ActChecklistDetail::class, 'act_id');
    }

    public function tireStates(): HasMany
    {
        return $this->hasMany(ActTireState::class, 'act_id');
    }
}
