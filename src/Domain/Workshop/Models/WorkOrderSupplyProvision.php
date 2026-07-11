<?php

namespace Domain\Workshop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'work_order_id',
    'supply_id',
    'quantity_used'
])]
class WorkOrderSupplyProvision extends Model
{
    use HasFactory;

    protected $table = 'work_order_supply_provisions';

    public $timestamps = false;

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkshopWorkOrder::class, 'work_order_id');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(SupplyInventory::class, 'supply_id');
    }
}
