<?php

namespace Domain\Requests\Models;

use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'requester_id',
    'mobilization_type',
    'origin',
    'destination',
    'destination_address',
    'destination_latitude',
    'destination_longitude',
    'travel_reason',
    'departure_date',
    'departure_time',
    'return_date',
    'return_time',
    'estimated_days',
    'projected_cost',
    'status',
    'rectorate_approver_id',
    'secretaria_approver_id',
    'secretaria_observation',
    'confirmation_deadline',
    'occupant_count',
    'communication_number',
    'activity_type',
    'academic_program',
    'public_servants_count',
])]
class MobilizationRequest extends Model
{
    use HasFactory;

    protected $table = 'mobilization_requests';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'departure_time' => 'string',
            'return_date' => 'date',
            'return_time' => 'string',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'projected_cost' => 'decimal:2',
            'confirmation_deadline' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function rectorateApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rectorate_approver_id');
    }

    public function secretariaApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretaria_approver_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(PassengerManifest::class, 'request_id');
    }

    public function routeSheet(): HasOne
    {
        return $this->hasOne(RouteSheet::class, 'request_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id')->orderBy('id');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'request_id')->orderByDesc('id');
    }
}
