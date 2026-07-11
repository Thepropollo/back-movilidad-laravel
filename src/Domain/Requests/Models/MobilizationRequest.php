<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Domain\Auth\Models\User;

#[Fillable([
    'requester_id',
    'mobilization_type',
    'origin',
    'destination',
    'travel_reason',
    'departure_date',
    'return_date',
    'estimated_days',
    'projected_cost',
    'status',
    'rectorate_approver_id'
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
            'return_date' => 'date',
            'projected_cost' => 'decimal:2',
        ];
    }

    /**
     * Obtener el solicitante (Usuario) asociado con esta solicitud.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Obtener el autorizador del rectorado (Usuario) asociado con esta solicitud.
     */
    public function rectorateApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rectorate_approver_id');
    }

    /**
     * Obtener el manifiesto de pasajeros asociado con esta solicitud.
     */
    public function passengers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PassengerManifest::class, 'request_id');
    }

    /**
     * Obtener la hoja de ruta asociada con esta solicitud.
     */
    public function routeSheet(): HasOne
    {
        return $this->hasOne(RouteSheet::class, 'request_id');
    }
}
