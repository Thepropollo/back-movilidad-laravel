<?php

namespace Domain\Workshop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Domain\Vehicles\Models\Vehicle;
use Domain\Auth\Models\User;

#[Fillable([
    'issue_log_id',
    'vehicle_id',
    'responsible_mechanic_id',
    'supervisor_id',
    'maintenance_type',
    'work_details',
    'entry_date',
    'exit_date'
])]
class WorkshopWorkOrder extends Model
{
    use HasFactory;

    protected $table = 'workshop_work_orders';

    public $timestamps = false;

    protected $casts = [
        'entry_date' => 'datetime',
        'exit_date' => 'datetime'
    ];

    public function issueLog(): BelongsTo
    {
        return $this->belongsTo(IssueLog::class, 'issue_log_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function responsibleMechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_mechanic_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function supplyProvisions(): HasMany
    {
        return $this->hasMany(WorkOrderSupplyProvision::class, 'work_order_id');
    }
}
