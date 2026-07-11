<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'act_id',
    'tire_position',
    'tire_brand',
    'psi',
    'wear_level_condition'
])]
class ActTireState extends Model
{
    use HasFactory;

    protected $table = 'act_tire_states';

    public $timestamps = false;

    public function act(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceptionAct::class, 'act_id');
    }
}
