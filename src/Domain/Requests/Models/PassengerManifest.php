<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Domain\Auth\Models\User;

#[Fillable([
    'request_id',
    'user_id',
    'attended'
])]
class PassengerManifest extends Model
{
    use HasFactory;

    protected $table = 'passenger_manifests';

    public $timestamps = false;

    protected $casts = [
        'attended' => 'boolean'
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MobilizationRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
