<?php

namespace Domain\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'affected_table',
    'record_id',
    'ip_address'
])]
class SystemLog extends Model
{
    use HasFactory;

    protected $table = 'system_logs';

    const UPDATED_AT = null;

    /**
     * Obtener el usuario asociado con el log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
