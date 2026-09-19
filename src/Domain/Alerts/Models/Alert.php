<?php

namespace Domain\Alerts\Models;

use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'key',
    'type',
    'severity',
    'title',
    'message',
    'route',
    'entity_id',
    'audience_role',
    'user_id',
    'detail',
])]
class Alert extends Model
{
    use HasFactory;

    protected $table = 'alerts';

    protected function casts(): array
    {
        return [
            'detail' => 'array',
        ];
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'alert_reads')->withPivot('read_at');
    }
}
