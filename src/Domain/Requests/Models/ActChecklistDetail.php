<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'act_id',
    'component_id',
    'physical_condition'
])]
class ActChecklistDetail extends Model
{
    use HasFactory;

    protected $table = 'act_checklist_details';

    public $timestamps = false;

    public function act(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceptionAct::class, 'act_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(ChecklistInventoryComponent::class, 'component_id');
    }
}
